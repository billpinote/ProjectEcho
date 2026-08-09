<?php

namespace Tests\Feature;

use App\Domain\Pilots\Enums\PilotQualificationCategory;
use App\Domain\Users\Enums\UserRole;
use App\Filament\Panels\Admin\Resources\ProfileUpdateRequests\Pages\EditProfileUpdateRequest;
use App\Filament\Panels\Admin\Resources\ProfileUpdateRequests\Pages\ListProfileUpdateRequests;
use App\Filament\Panels\Admin\Resources\Users\Pages\EditUser;
use App\Models\User;
use App\Services\ProfileUpdates\ArtisanProfileOverrideService;
use App\Services\ProfileUpdates\ProfileUpdateRequestService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class ProfileUpdateRequestWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_pilot_cannot_directly_modify_verified_profile_fields(): void
    {
        $pilot = $this->user(UserRole::Pilot, ['first_name' => 'Old']);
        $pilot->pilotProfile()->create(['license_number' => 'OLD-LIC']);

        $this->actingAs($pilot)
            ->get(route('filament.admin.resources.users.edit', ['record' => $pilot]))
            ->assertForbidden();

        $pilot->refresh()->load('pilotProfile');

        $this->assertSame('Old', $pilot->first_name);
        $this->assertSame('OLD-LIC', $pilot->pilotProfile?->license_number);
    }

    public function test_other_ordinary_roles_cannot_directly_modify_verified_profile_fields(): void
    {
        foreach ([UserRole::Dispatch, UserRole::Avsec, UserRole::Atmo, UserRole::AtsHq] as $role) {
            $user = $this->user($role, ['station' => $role === UserRole::Atmo ? 'RPUS' : null]);

            $this->actingAs($user)
                ->get(route('filament.admin.resources.users.edit', ['record' => $user]))
                ->assertForbidden();
        }
    }

    public function test_user_can_submit_request_without_changing_actual_profile(): void
    {
        $pilot = $this->user(UserRole::Pilot, ['first_name' => 'Old']);
        $pilot->pilotProfile()->create(['license_number' => 'OLD-LIC']);

        $request = app(ProfileUpdateRequestService::class)->submit($pilot, [
            'user.first_name' => 'New',
            'pilot_profile.license_number' => 'NEW-LIC',
        ], 'Updated documents.');

        $pilot->refresh()->load('pilotProfile');

        $this->assertSame($pilot->id, $request->user_id);
        $this->assertSame('pending', $request->status->value);
        $this->assertSame('Old', $pilot->first_name);
        $this->assertSame('OLD-LIC', $pilot->pilotProfile?->license_number);
    }

    public function test_user_cannot_request_changes_for_another_user(): void
    {
        $owner = $this->user(UserRole::Pilot, ['first_name' => 'Owner']);
        $intruder = $this->user(UserRole::Pilot, ['first_name' => 'Intruder']);

        $request = app(ProfileUpdateRequestService::class)->submit($intruder, [
            'user.first_name' => 'Changed',
        ], 'My own change.');

        $this->assertSame($intruder->id, $request->user_id);
        $this->assertSame('Owner', $owner->refresh()->first_name);
    }

    public function test_pilot_request_ignores_forbidden_profile_fields(): void
    {
        $pilot = $this->user(UserRole::Pilot, ['first_name' => 'Jesse']);
        $pilot->pilotProfile()->create(['remarks' => 'Internal note.']);

        $this->expectException(\InvalidArgumentException::class);

        app(ProfileUpdateRequestService::class)->submit($pilot, [
            'user.operator_id' => 123,
            'user.station' => 'RPUS',
            'pilot_profile.operator' => 'Legacy OPR',
            'pilot_profile.remarks' => 'Pilot changed note.',
        ], 'Unsupported fields.');
    }

    public function test_user_cannot_access_another_users_supporting_documents(): void
    {
        Storage::fake('local');

        $owner = $this->user(UserRole::Pilot);
        $intruder = $this->user(UserRole::Pilot);
        Storage::disk('local')->put('profile-update-request-documents/proof.pdf', 'proof');

        $request = app(ProfileUpdateRequestService::class)->submit($owner, [
            'user.first_name' => 'Changed',
        ], 'Proof attached.', [[
            'stored_path' => 'profile-update-request-documents/proof.pdf',
            'original_filename' => 'proof.pdf',
            'mime_type' => 'application/pdf',
            'file_size' => 5,
        ]]);

        $document = $request->documents()->firstOrFail();

        $this->actingAs($intruder)
            ->get(route('profile-update-request-documents.download', $document))
            ->assertForbidden();

        $this->actingAs($owner)
            ->get(route('profile-update-request-documents.download', $document))
            ->assertOk();
    }

    public function test_admin_can_review_request_and_approval_applies_changes_with_audit(): void
    {
        $admin = $this->user(UserRole::Admin);
        $pilot = $this->user(UserRole::Pilot, ['first_name' => 'Old', 'operator_id' => null]);
        $pilot->pilotProfile()->create(['license_number' => 'OLD-LIC']);

        $request = app(ProfileUpdateRequestService::class)->submit($pilot, [
            'user.first_name' => 'New',
            'pilot_profile.license_number' => 'NEW-LIC',
        ], 'Approved docs.');

        $this->actingAs($admin)
            ->get(route('filament.admin.resources.profile-update-requests.edit', ['record' => $request]))
            ->assertOk();

        Livewire::actingAs($admin)
            ->test(ListProfileUpdateRequests::class)
            ->assertSuccessful();

        Livewire::actingAs($admin)
            ->test(EditProfileUpdateRequest::class, ['record' => $request->getKey()])
            ->callAction('approve', ['remarks' => 'Verified against documents.'])
            ->assertHasNoActionErrors();

        $pilot->refresh()->load('pilotProfile');
        $request->refresh();

        $this->assertSame('approved', $request->status->value);
        $this->assertSame('New', $pilot->first_name);
        $this->assertNull($pilot->operator_id);
        $this->assertSame('NEW-LIC', $pilot->pilotProfile?->license_number);
        $this->assertDatabaseHas('user_audit_logs', [
            'user_id' => $pilot->id,
            'performed_by_user_id' => $admin->id,
            'action' => 'profile_change_approved',
            'source' => 'profile_update_request',
            'field' => 'pilot_profile.license_number',
            'old_value' => 'OLD-LIC',
            'new_value' => 'NEW-LIC',
            'profile_update_request_id' => $request->id,
        ]);
    }

    public function test_admin_rejection_stores_reason_and_does_not_modify_profile(): void
    {
        $admin = $this->user(UserRole::Admin);
        $pilot = $this->user(UserRole::Pilot, ['first_name' => 'Old']);
        $request = app(ProfileUpdateRequestService::class)->submit($pilot, [
            'user.first_name' => 'New',
        ], 'Please change.');

        app(ProfileUpdateRequestService::class)->reject($request, $admin, 'Document mismatch.');

        $request->refresh();

        $this->assertSame('rejected', $request->status->value);
        $this->assertSame('Document mismatch.', $request->rejection_reason);
        $this->assertSame('Old', $pilot->refresh()->first_name);
    }

    public function test_pilot_can_request_qualification_addition_and_approval_applies_it_with_audit(): void
    {
        $admin = $this->user(UserRole::Admin);
        $pilot = $this->user(UserRole::Pilot);
        $pilot->pilotProfile()->create();

        $request = app(ProfileUpdateRequestService::class)->submit($pilot, [], 'New type rating.', [], [[
            'operation' => 'add',
            'values' => [
                'category' => PilotQualificationCategory::AircraftRating->value,
                'code' => 'B737',
                'description' => 'Boeing 737',
                'expiry_date' => '2027-12-31',
            ],
        ]]);

        $this->assertSame(0, $pilot->pilotProfile->qualifications()->count());
        $this->assertSame('add', $request->requested_changes[ProfileUpdateRequestService::QUALIFICATION_CHANGES_KEY]['operations'][0]['operation']);

        app(ProfileUpdateRequestService::class)->approve($request, $admin, 'Verified rating.');

        $qualification = $pilot->pilotProfile()->first()->qualifications()->firstOrFail();

        $this->assertSame('B737', $qualification->code);
        $this->assertSame(PilotQualificationCategory::AircraftRating, $qualification->category);
        $this->assertDatabaseHas('user_audit_logs', [
            'user_id' => $pilot->id,
            'field' => 'pilot_qualification.add',
            'new_value' => 'Aircraft Rating B737',
        ]);
    }

    public function test_pilot_can_request_qualification_update_and_rejection_applies_nothing(): void
    {
        $admin = $this->user(UserRole::Admin);
        $pilot = $this->user(UserRole::Pilot);
        $profile = $pilot->pilotProfile()->create();
        $qualification = $profile->qualifications()->create([
            'category' => PilotQualificationCategory::InstrumentRating,
            'code' => 'IR',
            'description' => 'Instrument Rating',
            'expiry_date' => '2027-12-31',
        ]);

        $request = app(ProfileUpdateRequestService::class)->submit($pilot, [], 'Renewed rating.', [], [[
            'operation' => 'update',
            'qualification_id' => $qualification->id,
            'values' => [
                'category' => PilotQualificationCategory::InstrumentRating->value,
                'code' => 'IR',
                'description' => 'Instrument Rating',
                'expiry_date' => '2028-12-31',
            ],
        ]]);

        $this->assertSame('2027-12-31', $qualification->refresh()->expiry_date?->toDateString());
        $this->assertSame(['expiry_date'], array_keys($request->requested_changes[ProfileUpdateRequestService::QUALIFICATION_CHANGES_KEY]['operations'][0]['changes']));

        app(ProfileUpdateRequestService::class)->reject($request, $admin, 'Need clearer document.');

        $this->assertSame('2027-12-31', $qualification->refresh()->expiry_date?->toDateString());
    }

    public function test_admin_approval_applies_qualification_update_and_removal_with_audit(): void
    {
        $admin = $this->user(UserRole::Admin);
        $pilot = $this->user(UserRole::Pilot);
        $profile = $pilot->pilotProfile()->create();
        $instrument = $profile->qualifications()->create([
            'category' => PilotQualificationCategory::InstrumentRating,
            'code' => 'IR',
            'description' => 'Instrument Rating',
            'expiry_date' => '2027-12-31',
        ]);
        $aircraft = $profile->qualifications()->create([
            'category' => PilotQualificationCategory::AircraftRating,
            'code' => 'C172',
            'description' => 'Cessna 172',
        ]);

        $request = app(ProfileUpdateRequestService::class)->submit($pilot, [], 'Credential cleanup.', [], [
            [
                'operation' => 'update',
                'qualification_id' => $instrument->id,
                'values' => [
                    'category' => PilotQualificationCategory::InstrumentRating->value,
                    'code' => 'IR',
                    'description' => 'Instrument Rating',
                    'expiry_date' => '2028-12-31',
                ],
            ],
            [
                'operation' => 'remove',
                'qualification_id' => $aircraft->id,
            ],
        ]);

        app(ProfileUpdateRequestService::class)->approve($request, $admin, 'Verified.');

        $this->assertSame('2028-12-31', $instrument->refresh()->expiry_date?->toDateString());
        $this->assertDatabaseMissing('pilot_qualifications', ['id' => $aircraft->id]);
        $this->assertDatabaseHas('user_audit_logs', [
            'user_id' => $pilot->id,
            'field' => 'pilot_qualification.update',
        ]);
        $this->assertDatabaseHas('user_audit_logs', [
            'user_id' => $pilot->id,
            'field' => 'pilot_qualification.remove',
        ]);
    }

    public function test_one_pilot_cannot_target_another_pilots_qualification(): void
    {
        $owner = $this->user(UserRole::Pilot);
        $ownerProfile = $owner->pilotProfile()->create();
        $qualification = $ownerProfile->qualifications()->create([
            'category' => PilotQualificationCategory::AircraftRating,
            'code' => 'C172',
        ]);

        $intruder = $this->user(UserRole::Pilot);
        $intruder->pilotProfile()->create();

        $this->expectException(\InvalidArgumentException::class);

        app(ProfileUpdateRequestService::class)->submit($intruder, [], 'Trying cross-profile update.', [], [[
            'operation' => 'update',
            'qualification_id' => $qualification->id,
            'values' => [
                'category' => PilotQualificationCategory::AircraftRating->value,
                'code' => 'C208',
            ],
        ]]);
    }

    public function test_artisan_override_requires_reason_and_creates_clear_audit_entry(): void
    {
        $artisan = $this->user(UserRole::Artisan);
        $pilot = $this->user(UserRole::Pilot, ['first_name' => 'Old']);

        $this->expectException(\InvalidArgumentException::class);
        app(ArtisanProfileOverrideService::class)->override($pilot, $artisan, [
            'user.first_name' => 'New',
        ], '');
    }

    public function test_artisan_override_succeeds_with_reason(): void
    {
        $artisan = $this->user(UserRole::Artisan);
        $pilot = $this->user(UserRole::Pilot, ['first_name' => 'Old']);

        app(ArtisanProfileOverrideService::class)->override($pilot, $artisan, [
            'user.first_name' => 'New',
        ], 'Emergency identity correction.');

        $this->assertSame('New', $pilot->refresh()->first_name);
        $this->assertDatabaseHas('user_audit_logs', [
            'user_id' => $pilot->id,
            'performed_by_user_id' => $artisan->id,
            'action' => 'artisan_override',
            'source' => 'artisan_override',
            'field' => 'user.first_name',
            'old_value' => 'Old',
            'new_value' => 'New',
        ]);
    }

    public function test_non_artisan_cannot_use_override_action(): void
    {
        $admin = $this->user(UserRole::Admin);
        $pilot = $this->user(UserRole::Pilot, ['first_name' => 'Old']);

        Livewire::actingAs($admin)
            ->test(EditUser::class, ['record' => $pilot->getKey()])
            ->assertActionHidden('artisanOverride');
    }

    public function test_existing_flight_plan_permissions_remain_unchanged(): void
    {
        $pilot = $this->user(UserRole::Pilot);

        $this->assertTrue($pilot->canCreateFlightPlans());
        $this->assertFalse($pilot->canUpdateFlightPlans());
        $this->assertTrue($this->user(UserRole::Admin)->canUpdateFlightPlans());
        $this->assertTrue($this->user(UserRole::Artisan)->canUpdateFlightPlans());
    }

    private function user(UserRole $role, array $attributes = []): User
    {
        return User::factory()->create([
            'role' => $role,
            'is_active' => true,
            ...$attributes,
        ]);
    }
}
