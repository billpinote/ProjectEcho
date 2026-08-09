<?php

namespace Tests\Feature;

use App\Domain\Users\Enums\UserRole;
use App\Filament\Panels\Pilot\Pages\EditMyProfilePage;
use App\Filament\Panels\Pilot\Pages\HelpPage;
use App\Filament\Panels\Pilot\Pages\MyProfilePage;
use App\Filament\Panels\Pilot\Pages\PreferencesPage;
use App\Filament\Panels\Pilot\Pages\SecurityPage;
use App\Models\Operator;
use App\Models\User;
use App\Models\UserAuditLog;
use App\Models\UserKycDocument;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class PilotProfilePageTest extends TestCase
{
    use RefreshDatabase;

    public function test_active_pilot_can_access_pilot_panel(): void
    {
        $pilot = $this->pilot();

        $this->actingAs($pilot)
            ->get('/pilot')
            ->assertOk();
    }

    public function test_active_artisan_can_access_pilot_panel(): void
    {
        $artisan = $this->artisanUser();

        $this->actingAs($artisan)
            ->get('/pilot')
            ->assertOk();
    }

    public function test_profile_pages_require_authentication(): void
    {
        foreach ([
            MyProfilePage::getUrl(panel: 'pilot'),
            EditMyProfilePage::getUrl(panel: 'pilot'),
            PreferencesPage::getUrl(panel: 'pilot'),
            SecurityPage::getUrl(panel: 'pilot'),
            HelpPage::getUrl(panel: 'pilot'),
        ] as $url) {
            $this->get($url)->assertRedirect(route('filament.pilot.auth.login'));
        }
    }

    public function test_pilot_sees_their_own_profile_information(): void
    {
        $pilot = $this->pilot([
            'first_name' => 'Bill',
            'middle_name' => 'Q',
            'last_name' => 'Pilot',
            'email' => 'bill@example.test',
            'station' => 'RPUS',
            'operator_id' => Operator::factory()->create(['name' => 'Canonical Air'])->id,
        ]);

        $pilot->pilotProfile()->create([
            'license_number' => 'LIC-7788',
            'ratings' => 'IR, ME',
            'license_expiry_date' => '2026-11-15',
            'medical_expiry_date' => '2026-10-20',
            'operator' => 'Legacy OPR',
            'remarks' => 'Ready for review.',
        ]);

        $this->actingAs($pilot)
            ->get(MyProfilePage::getUrl(panel: 'pilot'))
            ->assertOk()
            ->assertSeeText('Pilot Record')
            ->assertSeeText('Personal Details')
            ->assertSeeText('Pilot Credentials')
            ->assertSeeText('Operator Assignment')
            ->assertSeeText('Verification Record')
            ->assertSeeText('Account / Administration')
            ->assertSeeText('Bill Q Pilot')
            ->assertSeeText('bill@example.test')
            ->assertSeeText('LIC-7788')
            ->assertSeeText('IR, ME')
            ->assertSeeText('November 15, 2026')
            ->assertSeeText('October 20, 2026')
            ->assertSeeText('Valid')
            ->assertSeeText('Credentials valid')
            ->assertSeeText('Canonical Air')
            ->assertDontSeeText('Home base')
            ->assertDontSeeText('RPUS')
            ->assertDontSeeText('Legacy OPR')
            ->assertSeeText('Ready for review.');
    }

    public function test_profile_view_hides_empty_optional_pilot_fields_and_admin_or_atc_metadata(): void
    {
        $pilot = $this->pilot([
            'first_name' => 'Sparse',
            'middle_name' => null,
            'last_name' => 'Pilot',
            'email' => 'sparse@example.test',
            'display_name' => null,
            'employee_id' => 'PIL-SECRET',
            'wiresign' => 'WS',
            'station' => null,
        ]);

        $pilot->pilotProfile()->create([
            'license_number' => null,
            'ratings' => null,
            'license_expiry_date' => null,
            'medical_expiry_date' => null,
            'remarks' => null,
        ]);

        $this->actingAs($pilot)
            ->get(MyProfilePage::getUrl(panel: 'pilot'))
            ->assertOk()
            ->assertSeeText('Sparse Pilot')
            ->assertSeeText('sparse@example.test')
            ->assertSeeText('No pilot credentials are recorded.')
            ->assertSeeText('No operator assignment is recorded.')
            ->assertDontSeeText('Not provided')
            ->assertDontSeeText('Home base')
            ->assertDontSeeText('Station')
            ->assertDontSeeText('Employee ID')
            ->assertDontSeeText('PIL-SECRET')
            ->assertDontSeeText('Wiresign')
            ->assertDontSeeText('WS')
            ->assertDontSeeText('role = pilot')
            ->assertDontSeeText('Last login');
    }

    public function test_profile_view_displays_existing_kyc_documents_and_audit_record(): void
    {
        $creator = $this->user(UserRole::Admin, ['name' => 'Creator Admin']);
        $verifier = $this->user(UserRole::Admin, ['name' => 'Verifier Admin']);
        $modifier = $this->user(UserRole::Admin, ['name' => 'Modifier Admin']);
        $pilot = $this->pilot([
            'first_name' => 'Verified',
            'last_name' => 'Pilot',
            'created_by_user_id' => $creator->id,
        ]);

        UserKycDocument::query()->create([
            'user_id' => $pilot->id,
            'document_type' => 'pilot_license',
            'document_identifier' => 'LIC-123456',
            'file_path' => 'kyc-documents/license.pdf',
            'original_file_name' => 'license.pdf',
            'verified_by_user_id' => $verifier->id,
            'verified_at' => now()->subDay(),
            'remarks' => 'License copy checked.',
        ]);

        UserAuditLog::query()->create([
            'user_id' => $pilot->id,
            'performed_by_user_id' => $modifier->id,
            'action' => 'profile_updated',
            'field' => 'pilot_profile.license_number',
            'created_at' => now(),
        ]);

        $this->actingAs($pilot)
            ->get(MyProfilePage::getUrl(panel: 'pilot'))
            ->assertOk()
            ->assertSeeText('KYC documents verified')
            ->assertSeeText('Created by')
            ->assertSeeText('Creator Admin')
            ->assertSeeText('Last modified by')
            ->assertSeeText('Modifier Admin')
            ->assertSeeText('KYC / Supporting Documents')
            ->assertSeeText('Pilot License')
            ->assertSeeText('Verifier Admin')
            ->assertSeeText('License copy checked.')
            ->assertSee('user-kyc-documents', escape: false)
            ->assertDontSeeText('LIC-123456');
    }

    public function test_profile_view_marks_expired_and_expiring_credentials(): void
    {
        $expiredPilot = $this->pilot([
            'first_name' => 'Expired',
            'last_name' => 'Pilot',
            'email' => 'expired@example.test',
        ]);
        $expiredPilot->pilotProfile()->create([
            'license_number' => 'EXP-1',
            'license_expiry_date' => now()->subDay()->toDateString(),
            'medical_expiry_date' => now()->addMonths(3)->toDateString(),
        ]);

        $this->actingAs($expiredPilot)
            ->get(MyProfilePage::getUrl(panel: 'pilot'))
            ->assertOk()
            ->assertSeeText('Credential expired')
            ->assertSeeText('Expired');

        $expiringPilot = $this->pilot([
            'first_name' => 'Soon',
            'last_name' => 'Pilot',
            'email' => 'soon@example.test',
        ]);
        $expiringPilot->pilotProfile()->create([
            'license_number' => 'SOON-1',
            'license_expiry_date' => now()->addDays(10)->toDateString(),
            'medical_expiry_date' => now()->addMonths(3)->toDateString(),
        ]);

        $this->actingAs($expiringPilot)
            ->get(MyProfilePage::getUrl(panel: 'pilot'))
            ->assertOk()
            ->assertSeeText('Credential expiring soon')
            ->assertSeeText('Expiring soon');
    }

    public function test_profile_view_does_not_mark_distant_future_credentials_as_expiring_soon(): void
    {
        $pilot = $this->pilot([
            'first_name' => 'Future',
            'last_name' => 'Pilot',
            'email' => 'future@example.test',
        ]);

        $pilot->pilotProfile()->create([
            'license_number' => 'FUT-1',
            'license_expiry_date' => '2026-12-10',
            'medical_expiry_date' => '2027-12-21',
        ]);

        $this->actingAs($pilot)
            ->get(MyProfilePage::getUrl(panel: 'pilot'))
            ->assertOk()
            ->assertSeeText('December 10, 2026')
            ->assertSeeText('December 21, 2027')
            ->assertSeeText('Credentials valid')
            ->assertSeeText('Valid')
            ->assertDontSeeText('Expiring soon');
    }

    public function test_artisan_cannot_view_or_edit_pilot_profile_pages(): void
    {
        $artisan = $this->artisanUser();

        $this->actingAs($artisan)
            ->get(MyProfilePage::getUrl(panel: 'pilot'))
            ->assertForbidden();

        $this->actingAs($artisan)
            ->get(EditMyProfilePage::getUrl(panel: 'pilot'))
            ->assertForbidden();
    }

    public function test_artisan_profile_access_attempt_does_not_create_pilot_profile(): void
    {
        $artisan = $this->artisanUser();

        $this->assertDatabaseCount('pilot_profiles', 0);

        $this->actingAs($artisan)
            ->get(EditMyProfilePage::getUrl(panel: 'pilot'))
            ->assertForbidden();

        $this->assertDatabaseCount('pilot_profiles', 0);
        $this->assertFalse($artisan->pilotProfile()->exists());
    }

    public function test_view_profile_page_links_to_the_request_page(): void
    {
        $pilot = $this->pilot();

        $this->actingAs($pilot)
            ->get(MyProfilePage::getUrl(panel: 'pilot'))
            ->assertOk()
            ->assertSee(EditMyProfilePage::getUrl(panel: 'pilot'), escape: false)
            ->assertSeeText('Request Profile Update');
    }

    public function test_requesting_profile_update_does_not_immediately_change_profile(): void
    {
        $pilot = $this->pilot([
            'first_name' => 'Old',
            'middle_name' => null,
            'last_name' => 'Name',
            'email' => 'old@example.test',
            'suffix' => null,
        ]);

        $pilot->pilotProfile()->create([
            'license_number' => 'OLD-100',
        ]);

        Livewire::actingAs($pilot)
            ->test(EditMyProfilePage::class)
            ->fillForm([
                'first_name' => 'New',
                'middle_name' => 'M',
                'last_name' => 'Pilot',
                'license_number' => 'NEW-900',
                'ratings' => 'ATPL',
                'license_expiry_date' => '2027-01-04',
                'medical_expiry_date' => '2027-02-05',
                'remarks' => 'Updated profile',
                'reason' => 'KYC details have changed.',
            ])
            ->call('save')
            ->assertRedirect(MyProfilePage::getUrl(panel: 'pilot'));

        $pilot->refresh();
        $pilot->load('pilotProfile');

        $this->assertSame('Old', $pilot->first_name);
        $this->assertNull($pilot->middle_name);
        $this->assertSame('Name', $pilot->last_name);
        $this->assertSame('old@example.test', $pilot->email);
        $this->assertSame('OLD-100', $pilot->pilotProfile?->license_number);
        $this->assertNull($pilot->pilotProfile?->ratings);
        $this->assertNull($pilot->pilotProfile?->license_expiry_date);
        $this->assertNull($pilot->pilotProfile?->medical_expiry_date);
        $this->assertNull($pilot->pilotProfile?->operator);
        $this->assertNull($pilot->pilotProfile?->remarks);

        $request = $pilot->profileUpdateRequests()->firstOrFail();

        $this->assertSame('pending', $request->status->value);
        $this->assertSame('KYC details have changed.', $request->reason);
        $this->assertSame('Old', $request->requested_changes['user.first_name']['old']);
        $this->assertSame('New', $request->requested_changes['user.first_name']['new']);
        $this->assertSame('OLD-100', $request->requested_changes['pilot_profile.license_number']['old']);
        $this->assertSame('NEW-900', $request->requested_changes['pilot_profile.license_number']['new']);
    }

    public function test_request_profile_page_renders_a_livewire_submission_form(): void
    {
        $pilot = $this->pilot();

        $this->actingAs($pilot)
            ->get(EditMyProfilePage::getUrl(panel: 'pilot'))
            ->assertOk()
            ->assertSee('id="profile-form"', escape: false)
            ->assertSee('wire:submit="save"', escape: false)
            ->assertSeeText('Submit Request');
    }

    public function test_profile_and_placeholder_pages_do_not_register_in_main_navigation(): void
    {
        $this->assertFalse(MyProfilePage::shouldRegisterNavigation());
        $this->assertFalse(EditMyProfilePage::shouldRegisterNavigation());
        $this->assertFalse(PreferencesPage::shouldRegisterNavigation());
        $this->assertFalse(SecurityPage::shouldRegisterNavigation());
        $this->assertFalse(HelpPage::shouldRegisterNavigation());
    }

    public function test_pilot_panel_user_menu_contains_profile_preferences_security_and_help_items(): void
    {
        $pilot = $this->pilot();

        $this->actingAs($pilot);
        Filament::setCurrentPanel('pilot');

        $items = Filament::getUserMenuItems();
        $labels = array_map(fn ($item): string => (string) $item->getLabel(), $items);
        $urls = array_map(fn ($item): ?string => $item->getUrl(), $items);

        $this->assertContains('View Profile', $labels);
        $this->assertContains('Preferences', $labels);
        $this->assertContains('Security', $labels);
        $this->assertContains('Help', $labels);
        $this->assertContains('Sign out', $labels);
        $this->assertContains(MyProfilePage::getUrl(panel: 'pilot'), $urls);
        $this->assertContains(PreferencesPage::getUrl(panel: 'pilot'), $urls);
        $this->assertContains(SecurityPage::getUrl(panel: 'pilot'), $urls);
        $this->assertContains(HelpPage::getUrl(panel: 'pilot'), $urls);
    }

    public function test_pilot_panel_user_menu_hides_profile_item_for_artisan(): void
    {
        $artisan = $this->artisanUser();

        $this->actingAs($artisan);
        Filament::setCurrentPanel('pilot');

        $items = Filament::getUserMenuItems();
        $labels = array_map(fn ($item): string => (string) $item->getLabel(), $items);
        $urls = array_map(fn ($item): ?string => $item->getUrl(), $items);

        $this->assertNotContains('View Profile', $labels);
        $this->assertNotContains(MyProfilePage::getUrl(panel: 'pilot'), $urls);
        $this->assertContains('Preferences', $labels);
        $this->assertContains('Security', $labels);
        $this->assertContains('Help', $labels);
    }

    public function test_active_artisan_can_access_generic_pilot_support_pages(): void
    {
        $artisan = $this->artisanUser();

        foreach ([
            PreferencesPage::getUrl(panel: 'pilot'),
            SecurityPage::getUrl(panel: 'pilot'),
            HelpPage::getUrl(panel: 'pilot'),
        ] as $url) {
            $this->actingAs($artisan)
                ->get($url)
                ->assertOk();
        }
    }

    public function test_normal_role_restrictions_for_pilot_panel_remain_unchanged(): void
    {
        foreach ([
            UserRole::Admin,
            UserRole::Atmo,
            UserRole::Dispatch,
            UserRole::Avsec,
            UserRole::AtsHq,
        ] as $role) {
            $user = $this->user($role, [
                'station' => $role === UserRole::Atmo ? 'RPUS' : null,
            ]);

            $this->actingAs($user)
                ->get('/pilot')
                ->assertForbidden();
        }
    }

    public function test_artisan_can_still_access_other_authorized_panels(): void
    {
        $artisan = $this->artisanUser();

        foreach (['/admin', '/atmo', '/ats', '/dispatch', '/avsec', '/pilot', '/artisan'] as $path) {
            $this->actingAs($artisan)
                ->get($path)
                ->assertOk();
        }
    }

    public function test_users_cannot_view_or_modify_another_users_profile(): void
    {
        $owner = $this->pilot([
            'first_name' => 'Owner',
            'middle_name' => null,
            'last_name' => 'Pilot',
            'email' => 'owner@example.test',
        ]);
        $owner->pilotProfile()->create([
            'license_number' => 'OWNER-1',
            'remarks' => 'Owner only',
        ]);

        $intruder = $this->pilot([
            'first_name' => 'Intruder',
            'middle_name' => null,
            'last_name' => 'Pilot',
            'email' => 'intruder@example.test',
        ]);
        $intruder->pilotProfile()->create([
            'license_number' => 'INTRUDER-1',
            'remarks' => 'Intruder only',
        ]);

        $this->actingAs($intruder)
            ->get(MyProfilePage::getUrl(panel: 'pilot'))
            ->assertOk()
            ->assertSeeText('Intruder Pilot')
            ->assertSeeText('intruder@example.test')
            ->assertSeeText('INTRUDER-1')
            ->assertDontSeeText('Owner Pilot')
            ->assertDontSeeText('owner@example.test')
            ->assertDontSeeText('OWNER-1');

        Livewire::actingAs($intruder)
            ->test(EditMyProfilePage::class)
            ->fillForm([
                'first_name' => 'Still',
                'middle_name' => null,
                'last_name' => 'Intruder',
                'license_number' => 'INTRUDER-2',
                'ratings' => 'IR',
                'license_expiry_date' => null,
                'medical_expiry_date' => null,
                'remarks' => 'Changed intruder only',
                'reason' => 'Correct my own profile.',
            ])
            ->call('save')
            ->assertRedirect(MyProfilePage::getUrl(panel: 'pilot'));

        $owner->refresh();
        $owner->load('pilotProfile');
        $intruder->refresh();
        $intruder->load('pilotProfile');

        $this->assertSame('Owner', $owner->first_name);
        $this->assertSame('owner@example.test', $owner->email);
        $this->assertSame('OWNER-1', $owner->pilotProfile?->license_number);

        $this->assertSame('Intruder', $intruder->first_name);
        $this->assertSame('intruder@example.test', $intruder->email);
        $this->assertSame('INTRUDER-1', $intruder->pilotProfile?->license_number);
        $this->assertSame(1, $intruder->profileUpdateRequests()->count());
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function pilot(array $attributes = []): User
    {
        return $this->user(UserRole::Pilot, $attributes);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function artisanUser(array $attributes = []): User
    {
        return $this->user(UserRole::Artisan, $attributes);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function user(UserRole $role, array $attributes = []): User
    {
        return User::factory()->create([
            'role' => $role,
            'is_active' => true,
            ...$attributes,
        ]);
    }
}
