<?php

namespace Tests\Feature;

use App\Domain\Users\Enums\UserRole;
use App\Filament\Panels\Admin\Resources\Users\Pages\CreateUser;
use App\Filament\Panels\Admin\Resources\Users\Pages\EditUser;
use App\Filament\Panels\Admin\Resources\Users\Pages\ListUsers;
use App\Models\Operator;
use App\Models\UserAuditLog;
use App\Models\UserKycDocument;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;
use Livewire\Livewire;
use Tests\TestCase;

class AdminUserResourceTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_core_user_account(): void
    {
        $admin = $this->user(UserRole::Admin);
        $operator = Operator::factory()->create(['name' => 'Core Operator']);

        Livewire::actingAs($admin)
            ->test(CreateUser::class)
            ->fillForm([
                'first_name' => 'Ava',
                'middle_name' => 'L',
                'last_name' => 'Mendoza',
                'suffix' => 'Jr',
                'display_name' => 'Ava M.',
                'email' => 'ava@example.test',
                'username' => 'ava.mendoza',
                'employee_id' => 'EMP-100',
                'wiresign' => 'AV',
                'role' => UserRole::Admin->value,
                'station' => 'RPUS',
                'operator_id' => $operator->id,
                'is_active' => true,
                'password' => 'StrongPass123!',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $user = User::where('email', 'ava@example.test')->firstOrFail();

        $this->assertSame('Ava L Mendoza Jr', $user->name);
        $this->assertSame('Ava M.', $user->display_name);
        $this->assertSame('ava.mendoza', $user->username);
        $this->assertSame('EMP-100', $user->employee_id);
        $this->assertSame('AV', $user->wiresign);
        $this->assertSame(UserRole::Admin, $user->role);
        $this->assertSame('RPUS', $user->station);
        $this->assertSame($operator->id, $user->operator_id);
        $this->assertTrue($user->is_active);
        $this->assertTrue(Hash::check('StrongPass123!', $user->password));
        $this->assertTrue(Hash::check('StrongPass123!', $user->authAccounts()->where('provider', 'password')->firstOrFail()->password_hash));
        $this->assertSame($admin->id, $user->created_by_user_id);
        $this->assertDatabaseHas('user_audit_logs', [
            'user_id' => $user->id,
            'performed_by_user_id' => $admin->id,
            'action' => 'user_created',
            'source' => 'user_created',
        ]);
    }

    public function test_non_privileged_users_cannot_access_user_management(): void
    {
        foreach ([UserRole::Pilot, UserRole::Dispatch, UserRole::Avsec, UserRole::Atmo, UserRole::AtsHq] as $role) {
            $this->actingAs($this->user($role, ['station' => $role === UserRole::Atmo ? 'RPUS' : null]))
                ->get(route('filament.admin.resources.users.index'))
                ->assertForbidden();
        }
    }

    public function test_admin_and_artisan_can_access_user_creation(): void
    {
        foreach ([UserRole::Admin, UserRole::Artisan] as $role) {
            $actor = $this->user($role);

            $this->actingAs($actor)
                ->get(route('filament.admin.resources.users.index'))
                ->assertOk()
                ->assertSeeText('Create User')
                ->assertSee(route('filament.admin.resources.users.create'), false);

            $this->actingAs($actor)
                ->get(route('filament.admin.resources.users.create'))
                ->assertOk();

            Livewire::actingAs($actor)
                ->test(CreateUser::class)
                ->assertSuccessful();
        }
    }

    public function test_artisan_can_create_core_user_account(): void
    {
        $artisan = $this->user(UserRole::Artisan);

        Livewire::actingAs($artisan)
            ->test(CreateUser::class)
            ->fillForm([
                'first_name' => 'Tech',
                'last_name' => 'Created',
                'email' => 'tech-created@example.test',
                'role' => UserRole::Admin->value,
                'is_active' => true,
                'password' => 'StrongPass123!',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $created = User::where('email', 'tech-created@example.test')->firstOrFail();

        $this->assertSame($artisan->id, $created->created_by_user_id);
        $this->assertDatabaseHas('user_audit_logs', [
            'user_id' => $created->id,
            'performed_by_user_id' => $artisan->id,
            'action' => 'user_created',
            'source' => 'user_created',
        ]);
    }

    public function test_creating_pilot_user_creates_pilot_profile_and_canonical_operator_membership(): void
    {
        $admin = $this->user(UserRole::Admin);
        $operator = Operator::factory()->create(['name' => 'Pilot Operator']);

        Livewire::actingAs($admin)
            ->test(CreateUser::class)
            ->fillForm([
                'first_name' => 'Paolo',
                'last_name' => 'Pilot',
                'email' => 'paolo@example.test',
                'username' => 'paolo',
                'employee_id' => 'PIL-001',
                'wiresign' => null,
                'role' => UserRole::Pilot->value,
                'operator_id' => $operator->id,
                'is_active' => true,
                'password' => 'StrongPass123!',
                'pilot_license_number' => 'LIC-777',
                'pilot_ratings' => 'IR, ME',
                'pilot_license_expiry_date' => '2027-01-15',
                'pilot_medical_expiry_date' => '2027-02-20',
                'pilot_remarks' => 'Line checked',
                'kyc_documents' => [
                    [
                        'document_type' => 'company_id',
                        'document_identifier' => 'COMPANY-2916',
                        'verified_by_user_id' => User::factory()->create()->id,
                        'remarks' => 'Presented during onboarding',
                    ],
                ],
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $pilot = User::where('email', 'paolo@example.test')->firstOrFail();
        $pilot->load('pilotProfile');

        $this->assertSame(UserRole::Pilot, $pilot->role);
        $this->assertSame($operator->id, $pilot->operator_id);
        $this->assertNull($pilot->wiresign);
        $this->assertNotNull($pilot->pilotProfile);
        $this->assertSame($pilot->id, $pilot->pilotProfile->user_id);
        $this->assertSame('LIC-777', $pilot->pilotProfile->license_number);
        $this->assertSame('IR, ME', $pilot->pilotProfile->ratings);
        $this->assertSame('2027-01-15', $pilot->pilotProfile->license_expiry_date?->toDateString());
        $this->assertSame('2027-02-20', $pilot->pilotProfile->medical_expiry_date?->toDateString());
        $this->assertSame('Line checked', $pilot->pilotProfile->remarks);
        $this->assertNull($pilot->pilotProfile->operator);

        $document = $pilot->kycDocuments()->firstOrFail();

        $this->assertSame('company_id', $document->document_type);
        $this->assertSame('COMPANY-2916', $document->document_identifier);
        $this->assertSame($admin->id, $document->verified_by_user_id);
        $this->assertNotNull($document->verified_at);
        $this->assertSame('Presented during onboarding', $document->remarks);
        $this->assertDatabaseHas('user_audit_logs', [
            'user_id' => $pilot->id,
            'performed_by_user_id' => $admin->id,
            'action' => 'kyc_recorded',
            'auditable_type' => UserKycDocument::class,
            'auditable_id' => $document->id,
        ]);
    }

    public function test_creating_dispatch_user_creates_dispatch_profile_and_canonical_operator_membership(): void
    {
        $admin = $this->user(UserRole::Admin);
        $operator = Operator::factory()->create(['name' => 'Dispatch Operator']);

        Livewire::actingAs($admin)
            ->test(CreateUser::class)
            ->fillForm([
                'first_name' => 'Dina',
                'last_name' => 'Dispatcher',
                'email' => 'dina@example.test',
                'role' => UserRole::Dispatch->value,
                'operator_id' => $operator->id,
                'password' => 'StrongPass123!',
                'dispatch_dispatcher_license_number' => 'DISP-200',
                'dispatch_dispatcher_certificate' => 'CERT-200',
                'dispatch_department' => 'Operations',
                'dispatch_position' => 'Dispatcher',
                'dispatch_office_phone' => '555-0100',
                'dispatch_mobile_number' => '555-0101',
                'dispatch_shift' => 'Morning',
                'dispatch_remarks' => 'New desk lead',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $dispatch = User::where('email', 'dina@example.test')->firstOrFail();
        $dispatch->load('dispatchProfile');

        $this->assertSame(UserRole::Dispatch, $dispatch->role);
        $this->assertSame($operator->id, $dispatch->operator_id);
        $this->assertNotNull($dispatch->dispatchProfile);
        $this->assertSame($dispatch->id, $dispatch->dispatchProfile->user_id);
        $this->assertSame('DISP-200', $dispatch->dispatchProfile->dispatcher_license_number);
        $this->assertSame('CERT-200', $dispatch->dispatchProfile->dispatcher_certificate);
        $this->assertSame('Operations', $dispatch->dispatchProfile->department);
        $this->assertSame('Dispatcher', $dispatch->dispatchProfile->position);
        $this->assertSame('555-0100', $dispatch->dispatchProfile->office_phone);
        $this->assertSame('555-0101', $dispatch->dispatchProfile->mobile_number);
        $this->assertSame('Morning', $dispatch->dispatchProfile->shift);
        $this->assertSame('New desk lead', $dispatch->dispatchProfile->remarks);
    }

    public function test_role_specific_sections_do_not_require_unrelated_profile_fields(): void
    {
        $admin = $this->user(UserRole::Admin);

        Livewire::actingAs($admin)
            ->test(CreateUser::class)
            ->fillForm([
                'first_name' => 'Dispatch',
                'last_name' => 'Only',
                'email' => 'dispatch-only@example.test',
                'role' => UserRole::Dispatch->value,
                'password' => 'StrongPass123!',
                'dispatch_dispatcher_license_number' => 'D-ONLY',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        Livewire::actingAs($admin)
            ->test(CreateUser::class)
            ->fillForm([
                'first_name' => 'Pilot',
                'last_name' => 'Only',
                'email' => 'pilot-only@example.test',
                'role' => UserRole::Pilot->value,
                'password' => 'StrongPass123!',
                'pilot_license_number' => 'P-ONLY',
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $this->assertDatabaseHas('dispatch_profiles', ['dispatcher_license_number' => 'D-ONLY']);
        $this->assertDatabaseHas('pilot_profiles', ['license_number' => 'P-ONLY']);
    }

    public function test_invalid_create_submission_does_not_leave_partial_user(): void
    {
        $admin = $this->user(UserRole::Admin);

        Livewire::actingAs($admin)
            ->test(CreateUser::class)
            ->fillForm([
                'first_name' => 'Broken',
                'last_name' => 'Pilot',
                'email' => 'broken@example.test',
                'role' => UserRole::Pilot->value,
                'password' => 'short',
                'pilot_license_number' => 'BROKEN',
            ])
            ->call('create')
            ->assertHasFormErrors(['password']);

        $this->assertDatabaseMissing('users', ['email' => 'broken@example.test']);
        $this->assertDatabaseMissing('pilot_profiles', ['license_number' => 'BROKEN']);
    }

    public function test_editing_user_updates_matching_profile_without_creating_duplicates(): void
    {
        $admin = $this->user(UserRole::Admin);
        $pilot = $this->user(UserRole::Pilot, ['email' => 'edit-pilot@example.test']);
        $pilot->pilotProfile()->create(['license_number' => 'OLD']);

        Livewire::actingAs($admin)
            ->test(EditUser::class, ['record' => $pilot->getKey()])
            ->fillForm([
                'first_name' => 'Edited',
                'middle_name' => null,
                'last_name' => 'Pilot',
                'suffix' => null,
                'email' => 'edit-pilot@example.test',
                'role' => UserRole::Pilot->value,
                'pilot_license_number' => 'NEW',
                'pilot_ratings' => 'ATPL',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $pilot->refresh();

        $this->assertSame('Edited Pilot', $pilot->name);
        $this->assertSame(1, $pilot->pilotProfile()->count());
        $this->assertSame('NEW', $pilot->pilotProfile()->first()?->license_number);
        $this->assertSame('ATPL', $pilot->pilotProfile()->first()?->ratings);

        $profileAudit = $pilot->auditLogs()
            ->where('auditable_type', $pilot->pilotProfile()->first()?->getMorphClass())
            ->where('action', 'updated')
            ->latest('id')
            ->firstOrFail();

        $this->assertArrayHasKey('license_number', $profileAudit->changes);
        $this->assertSame('OLD', $profileAudit->changes['license_number']['old']);
        $this->assertSame('NEW', $profileAudit->changes['license_number']['new']);
    }

    public function test_role_change_creates_new_role_profile_without_deleting_existing_profile(): void
    {
        $admin = $this->user(UserRole::Admin);
        $pilot = $this->user(UserRole::Pilot, ['email' => 'role-change@example.test']);
        $pilot->pilotProfile()->create(['license_number' => 'KEEP-ME']);

        Livewire::actingAs($admin)
            ->test(EditUser::class, ['record' => $pilot->getKey()])
            ->fillForm([
                'first_name' => 'Role',
                'last_name' => 'Changed',
                'email' => 'role-change@example.test',
                'role' => UserRole::Dispatch->value,
                'dispatch_dispatcher_license_number' => 'NEW-DISP',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $pilot->refresh();

        $this->assertSame(UserRole::Dispatch, $pilot->role);
        $this->assertSame('KEEP-ME', $pilot->pilotProfile()->first()?->license_number);
        $this->assertSame('NEW-DISP', $pilot->dispatchProfile()->first()?->dispatcher_license_number);
        $this->assertDatabaseHas('user_audit_logs', [
            'user_id' => $pilot->id,
            'action' => 'role_changed',
        ]);
    }

    public function test_updating_user_fields_creates_structured_audit_without_unchanged_fields(): void
    {
        $admin = $this->user(UserRole::Admin);
        $oldOperator = Operator::factory()->create(['name' => 'Old Operator']);
        $newOperator = Operator::factory()->create(['name' => 'New Operator']);
        $dispatch = $this->user(UserRole::Dispatch, [
            'email' => 'audit-dispatch@example.test',
            'station' => 'RPUS',
            'operator_id' => $oldOperator->id,
        ]);
        $dispatch->dispatchProfile()->create(['dispatcher_license_number' => 'OLD-DISP']);

        Livewire::actingAs($admin)
            ->test(EditUser::class, ['record' => $dispatch->getKey()])
            ->fillForm([
                'email' => 'audit-dispatch@example.test',
                'role' => UserRole::Dispatch->value,
                'station' => 'RPLL',
                'operator_id' => $newOperator->id,
                'dispatch_dispatcher_license_number' => 'NEW-DISP',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $userAudit = $dispatch->auditLogs()->where('action', 'operator_changed')->latest('id')->firstOrFail();

        $this->assertArrayHasKey('station', $userAudit->changes);
        $this->assertArrayHasKey('operator_id', $userAudit->changes);
        $this->assertArrayNotHasKey('email', $userAudit->changes);
        $this->assertSame($oldOperator->id, $userAudit->changes['operator_id']['old']);
        $this->assertSame($newOperator->id, $userAudit->changes['operator_id']['new']);
        $this->assertSame('Old Operator', $userAudit->changes['operator_id']['old_label']);
        $this->assertSame('New Operator', $userAudit->changes['operator_id']['new_label']);

        $profileAudit = $dispatch->auditLogs()
            ->where('auditable_type', $dispatch->dispatchProfile()->first()?->getMorphClass())
            ->where('action', 'updated')
            ->latest('id')
            ->firstOrFail();

        $this->assertSame('OLD-DISP', $profileAudit->changes['dispatcher_license_number']['old']);
        $this->assertSame('NEW-DISP', $profileAudit->changes['dispatcher_license_number']['new']);
    }

    public function test_password_change_audit_never_stores_password_values(): void
    {
        $admin = $this->user(UserRole::Admin);
        $user = $this->user(UserRole::Admin, ['email' => 'password-audit@example.test']);

        Livewire::actingAs($admin)
            ->test(EditUser::class, ['record' => $user->getKey()])
            ->fillForm([
                'email' => 'password-audit@example.test',
                'role' => UserRole::Admin->value,
                'password' => 'AnotherStrongPass123!',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $audit = $user->auditLogs()->where('description', 'Password changed.')->firstOrFail();
        $encodedChanges = json_encode($audit->changes);

        $this->assertStringNotContainsString('AnotherStrongPass123!', (string) $encodedChanges);
        $this->assertStringNotContainsString('password-audit', (string) $encodedChanges);
        $this->assertSame(['password' => ['changed' => true]], $audit->changes);
    }

    public function test_kyc_tables_are_admin_only_and_audit_logs_have_no_crud_surface(): void
    {
        $pilot = $this->user(UserRole::Pilot);
        $target = $this->user(UserRole::Dispatch);
        $target->kycDocuments()->create([
            'document_type' => 'passport',
            'document_identifier' => 'P1234567',
            'file_path' => 'kyc-documents/private.pdf',
            'verified_by_user_id' => $this->user(UserRole::Admin)->id,
            'verified_at' => now(),
        ]);

        $this->actingAs($pilot)
            ->get(route('filament.admin.resources.users.edit', ['record' => $target]))
            ->assertForbidden();

        $this->assertFalse(Route::has('filament.admin.resources.user-audit-logs.index'));
        $this->assertFalse(Route::has('filament.admin.resources.user-audit-logs.edit'));
        $this->assertFalse(Route::has('filament.admin.resources.user-kyc-documents.index'));
        $this->assertFalse(Route::has('filament.admin.resources.user-kyc-documents.edit'));
    }

    public function test_standalone_profile_create_route_is_no_longer_the_primary_creation_path(): void
    {
        $this->actingAs($this->user(UserRole::Admin))
            ->get(route('filament.admin.resources.pilot-profiles.create'))
            ->assertForbidden();
    }

    public function test_user_management_routes_are_registered(): void
    {
        $admin = $this->user(UserRole::Admin);

        $this->actingAs($admin)
            ->get(route('filament.admin.resources.users.index'))
            ->assertOk();

        Livewire::actingAs($admin)
            ->test(ListUsers::class)
            ->assertSuccessful();
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
