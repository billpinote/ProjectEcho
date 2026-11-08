<?php

namespace Tests\Feature;

use App\Domain\FlightPlans\Services\PicAuthorizationService;
use App\Domain\FlightPlans\Support\FlightPlanPreparerContext;
use App\Domain\FlightPlans\Support\PilotFlightPlanCredentials;
use App\Domain\Pilots\Enums\PilotLicenseType;
use App\Domain\ProfileUpdateRequests\Enums\ProfileUpdateRequestStatus;
use App\Domain\Users\Enums\UserRole;
use App\Filament\Panels\Pilot\Pages\MyProfilePage;
use App\Models\AuthAccount;
use App\Models\Flight;
use App\Models\Operator;
use App\Models\PilotProfile;
use App\Models\PilotSignature;
use App\Models\ProfileUpdateRequest;
use App\Models\User;
use App\Models\UserAuditLog;
use App\Models\UserKycDocument;
use App\Services\ProfileUpdates\PilotSignatureService;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\EchoDemoSeeder;
use Database\Seeders\Support\EchoDemoDocumentImage;
use Filament\Auth\Pages\Login;
use Filament\Facades\Filament;
use Filament\Panel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

class EchoDemoSeederTest extends TestCase
{
    use RefreshDatabase;

    private const PASSWORD = 'Synthetic-test-password-42!';

    protected function setUp(): void
    {
        parent::setUp();

        config(['echo-demo.password' => self::PASSWORD, 'echo-demo.enabled' => false]);
        Storage::fake('local');
        Storage::fake('public');
    }

    public function test_creates_exactly_the_expected_roster_operators_roles_and_profiles(): void
    {
        $this->seed(EchoDemoSeeder::class);

        $this->assertDatabaseCount('users', 15);
        $this->assertDatabaseCount('operators', 2);
        $this->assertDatabaseCount('auth_accounts', 15);
        $this->assertDatabaseCount('pilot_profiles', 6);
        $this->assertDatabaseCount('pilot_qualifications', 8);
        $this->assertDatabaseCount('dispatch_profiles', 2);
        $this->assertDatabaseCount('operator_staff_profiles', 2);
        $this->assertDatabaseCount('atc_profiles', 2);
        $this->assertDatabaseCount('avsec_profiles', 2);

        foreach (self::expectedUsers() as [$email, $name, $role, $academy, $license]) {
            $user = User::query()->where('email', $email)->firstOrFail();
            $this->assertSame($name, $user->fullName());
            $this->assertSame($name, $user->name);
            $this->assertSame($name, $user->display_name);
            $this->assertSame($role, $user->role);
            $this->assertTrue($user->is_active);
            $this->assertNotNull($user->email_verified_at);
            $this->assertTrue(Hash::check(self::PASSWORD, $user->password));
            $authAccount = $user->authAccounts()->sole();
            $this->assertSame('password', $authAccount->provider);
            $this->assertSame($email, $authAccount->identifier);
            $this->assertTrue(Hash::check(self::PASSWORD, $authAccount->password_hash));
            $this->assertSame($academy, $user->operator?->short_name);
            if ($role === UserRole::Atmo) {
                $this->assertSame('RPUS', $user->station);
                $this->assertSame('RPUS TWR', $user->atcProfile->facility);
            }
            if ($role === UserRole::OperatorStaff) {
                $this->assertTrue($user->operatorStaffProfile->operator->is($user->operator));
                $this->assertTrue($user->operatorStaffProfile->is_authorized);
            }

            $expectedPanel = self::panelFor($role);
            foreach (['pilot', 'dispatch', 'admin', 'avsec', 'atmo', 'ats', 'artisan'] as $panel) {
                $this->assertSame($expectedPanel === $panel, $user->canAccessPanel(Panel::make()->id($panel)), $email.' / '.$panel);
            }

            if ($license === null) {
                $this->assertNull($user->pilotProfile);

                continue;
            }
            $profile = $user->pilotProfile;
            $this->assertSame($license, $profile->license_type);
            $this->assertMatchesRegularExpression('/^'.$license->value.'-99\d{4}$/', $profile->formattedLicense());
            $this->assertTrue($profile->license_expiry_date->isFuture());
            $this->assertTrue($profile->medical_expiry_date->isFuture());
            $this->assertNull($profile->operator);
            $codes = $profile->qualifications()->orderBy('code')->pluck('code')->all();
            $this->assertSame(match ($license) {
                PilotLicenseType::CommercialPilot => ['ASEL', 'IR'],
                PilotLicenseType::PrivatePilot => ['ASEL'],
                default => ['SPL'],
            }, $codes);
            $credentials = PilotFlightPlanCredentials::forUser($user);
            $this->assertTrue($credentials['license_valid']);
            $this->assertSame($profile->ratings, $credentials['ratings']);
        }

        $this->assertSame(5, Operator::query()->where('short_name', 'LEIAA')->sole()->users()->count());
        $this->assertSame(5, Operator::query()->where('short_name', 'AAG')->sole()->users()->count());
    }

    public function test_documents_are_linked_readable_private_pngs_and_verified_by_demo_admin(): void
    {
        $this->seed(EchoDemoSeeder::class);

        $this->assertDatabaseCount('user_kyc_documents', 24);
        $this->assertCount(24, Storage::disk('local')->allFiles('kyc-documents'));
        $reviewer = User::query()->where('email', 'admin1.echo@example.com')->sole();
        $this->assertSame([], Storage::disk('public')->allFiles());
        foreach (PilotProfile::with('user.kycDocuments')->get() as $profile) {
            $documents = $profile->user->kycDocuments;
            $this->assertCount(4, $documents);
            $this->assertSame(['company_id', 'other', 'other', 'pilot_license'], $documents->pluck('document_type')->sort()->values()->all());
            $this->assertSame($profile->formattedLicense(), $documents->firstWhere('document_type', 'pilot_license')->document_identifier);
            foreach ($documents as $document) {
                $this->assertNotNull($document->verified_at);
                $this->assertSame($reviewer->id, $document->verified_by_user_id);
                $this->assertStringStartsWith('kyc-documents/echo-demo/'.$profile->user_id.'/', $document->file_path);
                // Windows reports POSIX permissions differently; the security boundary
                // is the private disk root plus the authorized download route.
                if (DIRECTORY_SEPARATOR !== '\\') {
                    $this->assertSame('private', Storage::disk('local')->getVisibility($document->file_path));
                }
                $bytes = Storage::disk('local')->get($document->file_path);
                $this->assertSame('image/png', getimagesizefromstring($bytes)['mime']);
                $image = imagecreatefromstring($bytes);
                $this->assertNotFalse($image);
                $this->assertSame(1100, imagesx($image));
                $this->assertSame(680, imagesy($image));
                imagedestroy($image);
            }
        }
    }

    public function test_all_six_pilots_have_approved_private_synthetic_signature_versions(): void
    {
        $this->seed(EchoDemoSeeder::class);
        $reviewer = User::query()->where('email', 'admin1.echo@example.com')->sole();
        $this->assertDatabaseCount('pilot_signatures', 6);
        $this->assertDatabaseCount('profile_update_requests', 6);
        $hashes = [];
        foreach (PilotProfile::with('user.approvedSignature')->get() as $profile) {
            $signature = $profile->user->approvedSignature;
            $this->assertNotNull($signature);
            $this->assertSame('approved', $signature->status);
            $this->assertSame(1, $signature->version);
            $this->assertSame($profile->user_id, $signature->active_user_id);
            $this->assertSame($reviewer->id, $signature->reviewed_by_user_id);
            $this->assertNotNull($signature->reviewed_at);
            $this->assertSame(ProfileUpdateRequestStatus::Approved, $signature->request->status);
            $this->assertStringContainsString('synthetic signature fixture', $signature->request->reason);
            $bytes = app(PilotSignatureService::class)->verifiedBytes($signature);
            $this->assertSame('image/png', getimagesizefromstring($bytes)['mime']);
            $this->assertSame(strlen($bytes), $signature->file_size);
            $this->assertSame(hash('sha256', $bytes), $signature->sha256);
            $hashes[] = $signature->sha256;
            Storage::disk('public')->assertMissing($signature->stored_path);
        }
        $this->assertCount(6, array_unique($hashes));
        $owner = User::query()->where('email', 'pilot1.leiaa@example.com')->sole();
        $url = route('pilot-signatures.show', $owner->approvedSignature);
        $this->get($url)->assertRedirect();
        $this->actingAs($owner)->get($url)->assertOk()->assertHeader('Content-Type', 'image/png');
        Filament::setCurrentPanel('pilot');
        $this->get(MyProfilePage::getUrl(panel: 'pilot'))->assertOk()->assertSee('KYC documents verified');
        $other = User::query()->where('email', 'pilot2.leiaa@example.com')->sole();
        $this->actingAs($other)->get($url)->assertForbidden();
    }

    public function test_rerun_upgrades_old_unverified_fixture_documents_without_creating_duplicates(): void
    {
        $this->seed(EchoDemoSeeder::class);
        UserKycDocument::query()->update(['verified_at' => null, 'verified_by_user_id' => null]);
        $ids = UserKycDocument::query()->pluck('id')->all();
        $hashes = $this->fileHashes();

        $this->seed(EchoDemoSeeder::class);

        $this->assertSame($ids, UserKycDocument::query()->pluck('id')->all());
        $this->assertSame($hashes, $this->fileHashes());
        $this->assertSame(24, UserKycDocument::query()->whereNotNull('verified_at')->whereNotNull('verified_by_user_id')->count());
        $this->assertDatabaseCount('pilot_signatures', 6);
    }

    public function test_rerun_preserves_existing_signature_versions_and_pending_review(): void
    {
        $this->seed(EchoDemoSeeder::class);
        $user = User::query()->where('email', 'pilot1.leiaa@example.com')->sole();
        $approvedId = $user->approvedSignature->id;
        $pending = PilotSignature::factory()->create(['user_id' => $user->id, 'version' => 2]);
        $before = PilotSignature::all()->map->getRawOriginal()->all();
        $files = $this->fileHashes();

        $this->seed(EchoDemoSeeder::class);

        $this->assertSame($before, PilotSignature::all()->map->getRawOriginal()->all());
        $this->assertSame($files, $this->fileHashes());
        $this->assertSame($approvedId, $user->fresh()->approvedSignature->id);
        $this->assertSame('pending', $pending->fresh()->status);
    }

    public function test_non_demo_admin_collision_blocks_fixture_verification_without_modifying_existing_account(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin, 'email' => 'admin1.echo@example.com']);
        $before = $admin->refresh()->getRawOriginal();
        try {
            $this->seed(EchoDemoSeeder::class);
            $this->fail('Expected a demo reviewer collision.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('seeder-owned demo admin', $exception->getMessage());
            $this->assertSame($before, $admin->fresh()->getRawOriginal());
            $this->assertDatabaseCount('users', 1);
            $this->assertDatabaseCount('pilot_signatures', 0);
            $this->assertSame([], Storage::disk('local')->allFiles());
        }
    }

    public function test_documents_enforce_existing_owner_reviewer_and_guest_access_rules(): void
    {
        $this->seed(EchoDemoSeeder::class);
        $owner = User::query()->where('email', 'pilot1.leiaa@example.com')->sole();
        $document = $owner->kycDocuments()->firstOrFail();
        $url = route('user-kyc-documents.download', $document);

        $this->get($url)->assertRedirect();
        $this->actingAs($owner)->get($url)->assertOk()->assertDownload($document->original_file_name);
        foreach (['pilot2.leiaa@example.com', 'pilot1.aag@example.com', 'dispatch1.leiaa@example.com', 'atmo1.rpus@example.com'] as $email) {
            $this->actingAs(User::query()->where('email', $email)->sole())->get($url)->assertForbidden();
        }
        $this->actingAs(User::query()->where('email', 'admin1.echo@example.com')->sole())->get($url)->assertOk();
        $this->actingAs(User::factory()->create(['role' => UserRole::Artisan]))->get($url)->assertOk();
        $owner->update(['is_active' => false]);
        $this->actingAs($owner)->get($url)->assertForbidden();
        $this->get('/storage/'.$document->file_path)->assertForbidden();
    }

    public function test_repeated_seeding_preserves_ids_passwords_file_bytes_and_added_documents(): void
    {
        $this->seed(EchoDemoSeeder::class);
        $user = User::query()->where('email', 'pilot1.leiaa@example.com')->sole();
        $user->update(['password' => 'User-changed-password!']);
        $account = $user->authAccounts()->sole();
        $account->update(['password_hash' => Hash::make('Account-changed-password!')]);
        Storage::disk('local')->put('kyc-documents/user-upload.png', 'existing uploaded data');
        $user->kycDocuments()->create(['document_type' => 'other', 'file_path' => 'kyc-documents/user-upload.png']);
        $beforeUsers = User::all()->map->getRawOriginal()->all();
        $beforeDocuments = UserKycDocument::all()->map->getRawOriginal()->all();
        $beforeFiles = $this->fileHashes();
        $beforeSignatures = PilotSignature::all()->map->getRawOriginal()->all();
        $beforeRequests = ProfileUpdateRequest::all()->map->getRawOriginal()->all();
        $beforeAudits = UserAuditLog::count();
        config(['echo-demo.password' => 'New-config-does-not-reset-passwords!']);

        $this->seed(EchoDemoSeeder::class);
        $this->seed(EchoDemoSeeder::class);

        $this->assertSame($beforeUsers, User::all()->map->getRawOriginal()->all());
        $this->assertSame($beforeDocuments, UserKycDocument::all()->map->getRawOriginal()->all());
        $this->assertSame($beforeFiles, $this->fileHashes());
        $this->assertSame($account->password_hash, $account->fresh()->password_hash);
        $this->assertDatabaseCount('operators', 2);
        $this->assertDatabaseCount('pilot_profiles', 6);
        $this->assertDatabaseCount('pilot_qualifications', 8);
        $this->assertDatabaseCount('auth_accounts', 15);
        $this->assertSame($beforeSignatures, PilotSignature::all()->map->getRawOriginal()->all());
        $this->assertSame($beforeRequests, ProfileUpdateRequest::all()->map->getRawOriginal()->all());
        $this->assertDatabaseCount('user_audit_logs', $beforeAudits);
    }

    public function test_existing_matching_account_and_unrelated_records_are_not_claimed_or_changed(): void
    {
        $operator = Operator::factory()->create(['name' => 'Alpha Aviation Group', 'short_name' => 'KEEP', 'certificate_number' => 'KEEP-ORIGINAL']);
        $user = User::factory()->create(['email' => 'pilot1.leiaa@example.com', 'operator_id' => $operator->id, 'is_active' => false]);
        $profile = $user->pilotProfile()->create(['license_type' => PilotLicenseType::PrivatePilot, 'license_number' => 'EXISTING']);
        $qualification = $profile->qualifications()->create(['category' => 'other', 'code' => 'KEEP']);
        $account = $user->authAccounts()->create(['provider' => 'password', 'email' => $user->email, 'password_hash' => Hash::make('Preserve-existing!')]);
        Storage::disk('local')->put('kyc-documents/existing.png', 'preserve uploaded file');
        $document = $user->kycDocuments()->create(['document_type' => 'pilot_license', 'file_path' => 'kyc-documents/existing.png', 'verified_at' => now()]);
        $unrelated = User::factory()->create();
        $flight = Flight::factory()->create(['filed_by_user_id' => $unrelated->id]);
        $models = [$operator, $user, $profile, $qualification, $account, $document, $unrelated, $flight];
        $before = array_map(fn ($model) => $model->refresh()->getRawOriginal(), $models);

        $this->seed(EchoDemoSeeder::class);
        $this->seed(EchoDemoSeeder::class);

        $this->assertSame($before, array_map(fn ($model) => $model->fresh()->getRawOriginal(), $models));
        $this->assertSame('preserve uploaded file', Storage::disk('local')->get('kyc-documents/existing.png'));
        $this->assertSame(1, $user->kycDocuments()->count());
        $this->assertSame(0, $user->auditLogs()->count());
        $this->assertDatabaseCount('users', 16);
        $this->assertDatabaseCount('operators', 2);
        $this->assertDatabaseCount('flights', 1);
        $this->assertSame(20, UserKycDocument::query()->where('user_id', '!=', $user->id)->count());
    }

    public function test_rerun_restores_missing_fixture_files_but_preserves_replaced_or_reviewed_attachments(): void
    {
        $this->seed(EchoDemoSeeder::class);
        $user = User::query()->where('email', 'pilot1.leiaa@example.com')->sole();
        $license = $user->kycDocuments()->where('document_type', 'pilot_license')->sole();
        $originalBytes = Storage::disk('local')->get($license->file_path);
        $license->update(['verified_at' => null, 'verified_by_user_id' => null]);
        Storage::disk('local')->delete($license->file_path);
        $identity = $user->kycDocuments()->where('document_type', 'company_id')->sole();
        Storage::disk('local')->put('kyc-documents/replacement.png', 'user replacement');
        $identity->update(['file_path' => 'kyc-documents/replacement.png']);
        $identity->refresh();
        $medical = $user->kycDocuments()->where('document_identifier', 'like', 'TEST-MED-%')->sole();
        $medical->update(['verified_at' => now()]);
        Storage::disk('local')->delete($medical->file_path);

        $this->seed(EchoDemoSeeder::class);

        $this->assertSame($originalBytes, Storage::disk('local')->get($license->file_path));
        $this->assertSame($identity->getRawOriginal(), $identity->fresh()->getRawOriginal());
        $this->assertSame('user replacement', Storage::disk('local')->get($identity->fresh()->file_path));
        $this->assertNotNull($medical->fresh()->verified_at);
        Storage::disk('local')->assertMissing($medical->file_path);
        $this->assertDatabaseCount('user_kyc_documents', 24);
    }

    public function test_authentication_email_collision_fails_without_claiming_another_identity(): void
    {
        $user = User::factory()->create();
        $account = $user->authAccounts()->create([
            'provider' => 'password', 'email' => $user->email,
            'identifier' => 'pilot1.leiaa@example.com', 'password_hash' => Hash::make('Keep-existing!'),
        ]);
        $account->refresh();
        try {
            $this->seed(EchoDemoSeeder::class);
            $this->fail('Expected an authentication alias collision.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('already used by another authentication account', $exception->getMessage());
            $this->assertDatabaseCount('users', 1);
            $this->assertDatabaseCount('operators', 0);
            $this->assertSame($account->getRawOriginal(), AuthAccount::findOrFail($account->id)->getRawOriginal());
            $this->assertSame([], Storage::disk('local')->allFiles());
        }
    }

    public function test_occupied_file_path_is_not_overwritten_and_new_records_roll_back(): void
    {
        $this->seed(EchoDemoSeeder::class);
        $document = UserKycDocument::query()->firstOrFail();
        $path = $document->file_path;
        $document->delete();
        Storage::disk('local')->put($path, 'preserved untracked file');

        try {
            $this->seed(EchoDemoSeeder::class);
            $this->fail('Expected a file-path collision.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('already occupied', $exception->getMessage());
            $this->assertSame('preserved untracked file', Storage::disk('local')->get($path));
            $this->assertDatabaseCount('user_kyc_documents', 23);
            $this->assertDatabaseCount('users', 15);
        }
    }

    public function test_main_pipeline_with_demo_opt_in_also_fails_safely_in_production(): void
    {
        config(['echo-demo.enabled' => true]);
        $this->app->instance('env', 'production');
        try {
            $this->artisan('db:seed', ['--class' => DatabaseSeeder::class, '--force' => true, '--no-interaction' => true])->run();
            $this->fail('Expected production restriction.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('restricted to local and testing', $exception->getMessage());
            $this->assertDatabaseCount('users', 0);
            $this->assertDatabaseCount('operators', 0);
        } finally {
            $this->app->instance('env', 'testing');
        }
    }

    #[DataProvider('blockedEnvironments')]
    public function test_seeding_is_blocked_outside_local_and_testing(string $environment): void
    {
        $this->app->instance('env', $environment);
        try {
            $this->artisan('db:seed', ['--class' => EchoDemoSeeder::class, '--force' => true, '--no-interaction' => true])->run();
            $this->fail('Expected an environment restriction.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('restricted to local and testing', $exception->getMessage());
            $this->assertDatabaseCount('users', 0);
            $this->assertDatabaseCount('operators', 0);
            $this->assertSame([], Storage::disk('local')->allFiles());
        } finally {
            $this->app->instance('env', 'testing');
        }
    }

    public static function blockedEnvironments(): array
    {
        return [['production'], ['staging']];
    }

    public function test_missing_password_fails_before_writing_records_or_files(): void
    {
        config(['echo-demo.password' => null]);
        try {
            $this->seed(EchoDemoSeeder::class);
            $this->fail('Expected a missing-password error.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('ECHO_DEMO_PASSWORD', $exception->getMessage());
            $this->assertDatabaseCount('users', 0);
            $this->assertDatabaseCount('operators', 0);
            $this->assertSame([], Storage::disk('local')->allFiles());
        }
    }

    public function test_database_seeder_calls_the_unified_demo_seeder_only_when_enabled(): void
    {
        $emails = array_column(self::expectedUsers(), 0);
        $this->seed(DatabaseSeeder::class);
        $this->assertSame(0, User::query()->whereIn('email', $emails)->count());
        $this->assertSame([], Storage::disk('local')->allFiles());

        config(['echo-demo.enabled' => true]);
        $this->seed(DatabaseSeeder::class);
        $this->assertSame(15, User::query()->whereIn('email', $emails)->count());
        $this->assertCount(24, Storage::disk('local')->allFiles('kyc-documents'));
        foreach (['admin@echo.example.com', 'miguel.santos@atc.example.com', 'pilot@example.com', 'juan.delacruz@example.com', 'pedro.santos@example.com'] as $legacy) {
            $this->assertDatabaseMissing('users', ['email' => $legacy]);
        }
    }

    public function test_render_failure_rolls_back_records_and_cleans_up_only_new_files(): void
    {
        Storage::disk('local')->put('unrelated.png', 'keep');
        $renderer = $this->partialMock(EchoDemoDocumentImage::class);
        $renderer->shouldReceive('render')->times(4)->passthru();
        $renderer->shouldReceive('render')->once()->andThrow(new RuntimeException('Synthetic renderer failure'));
        try {
            $this->seed(EchoDemoSeeder::class);
            $this->fail('Expected renderer failure.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Synthetic renderer failure', $exception->getMessage());
            $this->assertDatabaseCount('users', 0);
            $this->assertDatabaseCount('operators', 0);
            $this->assertDatabaseCount('user_kyc_documents', 0);
            $this->assertDatabaseCount('pilot_signatures', 0);
            $this->assertDatabaseCount('profile_update_requests', 0);
            $this->assertSame(['unrelated.png'], Storage::disk('local')->allFiles());
        }
    }

    public function test_students_keep_existing_non_pic_capacity_and_cannot_authorize_as_pic(): void
    {
        $this->seed(EchoDemoSeeder::class);
        foreach (['student1.leiaa@example.com', 'student1.aag@example.com'] as $email) {
            $student = User::query()->where('email', $email)->sole();
            $context = FlightPlanPreparerContext::for($student, ['filing_capacity' => 'self_pic']);
            $this->assertSame(FlightPlanPreparerContext::CAPACITY_FOR_ANOTHER_PIC, $context->capacity());
            $this->assertFalse($context->preparerActsAsPic());
            $this->assertTrue($context->shouldRequirePicAuthorization());
            try {
                app(PicAuthorizationService::class)->guardEligiblePic($student);
                $this->fail('SPL must not be eligible as PIC.');
            } catch (ValidationException $exception) {
                $this->assertNotEmpty($exception->errors());
            }
        }
    }

    #[DataProvider('loginAccounts')]
    public function test_demo_accounts_log_in_to_their_intended_panels(string $email, string $panel): void
    {
        $this->seed(EchoDemoSeeder::class);
        Filament::setCurrentPanel(Filament::getPanel($panel));

        Livewire::test(Login::class)->fillForm(['email' => $email, 'password' => self::PASSWORD])
            ->call('authenticate')->assertHasNoFormErrors();

        $user = User::query()->where('email', $email)->sole();
        $this->assertAuthenticatedAs($user);
        $this->get('/'.$panel)->assertOk();
    }

    public static function loginAccounts(): array
    {
        return array_map(fn (array $account): array => [$account[0], self::panelFor($account[2])], self::expectedUsers());
    }

    private static function panelFor(UserRole $role): string
    {
        return match ($role) {
            UserRole::OperatorStaff, UserRole::Dispatch => 'dispatch',
            default => strtolower($role->value),
        };
    }

    private static function expectedUsers(): array
    {
        return [
            ['pilot1.leiaa@example.com', 'Adrian Mendoza', UserRole::Pilot, 'LEIAA', PilotLicenseType::CommercialPilot],
            ['pilot2.leiaa@example.com', 'Bianca Reyes', UserRole::Pilot, 'LEIAA', PilotLicenseType::PrivatePilot],
            ['dispatch1.leiaa@example.com', 'Carlo Navarro', UserRole::Dispatch, 'LEIAA', null],
            ['operator1.leiaa@example.com', 'Diana Santos', UserRole::OperatorStaff, 'LEIAA', null],
            ['student1.leiaa@example.com', 'Ethan Villanueva', UserRole::Pilot, 'LEIAA', PilotLicenseType::StudentPilot],
            ['pilot1.aag@example.com', 'Gabriel Cruz', UserRole::Pilot, 'AAG', PilotLicenseType::CommercialPilot],
            ['pilot2.aag@example.com', 'Hannah Flores', UserRole::Pilot, 'AAG', PilotLicenseType::PrivatePilot],
            ['dispatch1.aag@example.com', 'Isaac Ramos', UserRole::Dispatch, 'AAG', null],
            ['operator1.aag@example.com', 'Julia Mercado', UserRole::OperatorStaff, 'AAG', null],
            ['student1.aag@example.com', 'Kyle Bautista', UserRole::Pilot, 'AAG', PilotLicenseType::StudentPilot],
            ['atmo1.rpus@example.com', 'Lorenzo Garcia', UserRole::Atmo, null, null],
            ['atmo2.rpus@example.com', 'Maria Castillo', UserRole::Atmo, null, null],
            ['admin1.echo@example.com', 'Nathan Rivera', UserRole::Admin, null, null],
            ['avsec1.rpus@example.com', 'Olivia Torres', UserRole::Avsec, null, null],
            ['avsec2.rpus@example.com', 'Paolo Fernandez', UserRole::Avsec, null, null],
        ];
    }

    private function fileHashes(): array
    {
        return collect(Storage::disk('local')->allFiles())
            ->mapWithKeys(fn (string $path): array => [$path => hash('sha256', Storage::disk('local')->get($path))])->all();
    }
}
