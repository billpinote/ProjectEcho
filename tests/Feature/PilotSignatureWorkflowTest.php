<?php

namespace Tests\Feature;

use App\Domain\Users\Enums\UserRole;
use App\Filament\Panels\Admin\Resources\ProfileUpdateRequests\Pages\EditProfileUpdateRequest;
use App\Filament\Panels\Pilot\Pages\EditMyProfilePage;
use App\Filament\Panels\Pilot\Pages\MyProfilePage;
use App\Models\PilotSignature;
use App\Models\ProfileUpdateRequest;
use App\Models\User;
use App\Services\ProfileUpdates\ProfileAuditRecorder;
use App\Services\ProfileUpdates\ProfileUpdateRequestService;
use App\Services\ProfileUpdates\SignatureImageService;
use Database\Seeders\PilotSignatureSeeder;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class PilotSignatureWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    public function test_signature_only_submission_approval_and_audit(): void
    {
        $pilot = $this->user();
        $request = $this->submit($pilot);
        $signature = $request->signature;
        $this->assertNull($pilot->approvedSignature()->first());
        $this->assertSame('pending', $signature->status);
        $this->assertSame(1, $signature->version);
        Storage::disk('local')->assertExists($signature->stored_path);
        $this->assertSame($signature->sha256, hash('sha256', Storage::disk('local')->get($signature->stored_path)));
        $reviewer = $this->user(UserRole::Admin);
        app(ProfileUpdateRequestService::class)->approve($request, $reviewer, 'Compared with KYC.');
        $this->assertTrue($pilot->approvedSignature()->first()->is($signature));
        $this->assertSame($reviewer->id, $signature->fresh()->reviewed_by_user_id);
        $this->assertNotNull($signature->fresh()->reviewed_at);
        $this->assertSame('approved', $request->fresh()->status->value);
        $this->assertSame(['signature_submitted', 'signature_approved'], $pilot->auditLogs()->orderBy('id')->pluck('action')->all());
        $audit = $pilot->auditLogs()->get()->toJson();
        $this->assertStringContainsString($signature->sha256, $audit);
        $this->assertStringNotContainsString('data:image', $audit);
        $this->assertStringNotContainsString('pilot-signatures/', $audit);
        $this->assertStringNotContainsString('base64', $request->fresh()->toJson());
    }

    public function test_pending_rejection_and_replacement_preserve_original_version_and_bytes(): void
    {
        $pilot = $this->user();
        $reviewer = $this->user(UserRole::Artisan);
        $first = $this->submit($pilot);
        app(ProfileUpdateRequestService::class)->approve($first, $reviewer);
        $original = $first->signature->fresh();
        $bytes = Storage::disk('local')->get($original->stored_path);
        $replacement = $this->submit($pilot, 1);
        $this->assertTrue($pilot->approvedSignature()->first()->is($original));
        $this->actingAs($pilot)->get(MyProfilePage::getUrl(panel: 'pilot'))->assertOk()
            ->assertSee('Currently approved signature')->assertSee('Pending signature submission')
            ->assertSee('Pending review—not available for signing');
        app(ProfileUpdateRequestService::class)->reject($replacement, $reviewer, 'Please provide a clearer signature.');
        $this->assertTrue($pilot->approvedSignature()->first()->is($original));
        $this->assertSame('rejected', $replacement->signature->fresh()->status);
        $third = $this->submit($pilot, 2);
        app(ProfileUpdateRequestService::class)->approve($third, $reviewer);
        $this->assertSame(3, $third->signature->version);
        $this->assertTrue($pilot->approvedSignature()->first()->is($third->signature));
        $this->assertSame('superseded', $original->fresh()->status);
        $this->assertSame($bytes, Storage::disk('local')->get($original->stored_path));
        $this->assertSame($reviewer->id, $original->reviewed_by_user_id);
        $this->assertDatabaseCount('pilot_signatures', 3);
        $this->assertSame(1, $pilot->auditLogs()->where('action', 'signature_replaced')->count());
    }

    public function test_private_previews_require_owner_or_active_reviewer(): void
    {
        $pilot = $this->user();
        $signature = $this->submit($pilot)->signature;
        $url = route('pilot-signatures.show', $signature);
        $this->get($url)->assertRedirect();
        foreach ([UserRole::Pilot, UserRole::Dispatch, UserRole::Avsec, UserRole::Atmo] as $role) {
            $this->actingAs($this->user($role))->get($url)->assertForbidden();
        }
        foreach ([UserRole::Admin, UserRole::Artisan] as $role) {
            $this->actingAs($this->user($role))->get($url)->assertOk()->assertHeader('Content-Type', 'image/png');
            $this->actingAs($this->user($role, false))->get($url)->assertForbidden();
        }
        $this->actingAs($pilot)->get($url)->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');
        $pilot->update(['is_active' => false]);
        $this->get($url)->assertForbidden();
    }

    public function test_pilots_cannot_review_or_submit_on_another_pilots_behalf(): void
    {
        $pilot = $this->user();
        $request = $this->submit($pilot);
        foreach (['approve', 'reject'] as $operation) {
            try {
                app(ProfileUpdateRequestService::class)->{$operation}($request, $pilot, 'Unauthorized');
                $this->fail('Pilot review must fail.');
            } catch (HttpException $exception) {
                $this->assertSame(403, $exception->getStatusCode());
            }
        }
        $this->actingAs($this->user());
        $this->expectException(HttpException::class);
        $this->submit($pilot, 1);
    }

    public function test_duplicate_pending_submissions_and_repeat_reviews_are_rejected(): void
    {
        $pilot = $this->user();
        $request = $this->submit($pilot);
        try {
            $this->submit($pilot, 1);
            $this->fail('Only one pending signature is allowed.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('data.signature_submission', $exception->errors());
        }
        $reviewer = $this->user(UserRole::Admin);
        app(ProfileUpdateRequestService::class)->approve($request, $reviewer);
        foreach (['approve', 'reject'] as $operation) {
            try {
                app(ProfileUpdateRequestService::class)->{$operation}($request, $reviewer, 'Repeat');
                $this->fail('Reviewed requests cannot be reviewed again.');
            } catch (\RuntimeException $exception) {
                $this->assertStringContainsString('Only pending', $exception->getMessage());
            }
        }
        $this->assertDatabaseCount('pilot_signatures', 1);
        $this->assertDatabaseCount('profile_update_requests', 1);
        $this->assertCount(1, Storage::disk('local')->allFiles());
        $this->assertSame(2, $pilot->auditLogs()->count());
    }

    public function test_cleanup_is_idempotent_and_drawing_is_black_on_transparency(): void
    {
        $images = app(SignatureImageService::class);
        $prepared = $images->prepare($this->image(), 'draw');
        $this->assertSame($prepared['sha256'], $images->prepare($prepared['bytes'], 'draw')['sha256']);
        $this->assertLessThan(600, $prepared['width']);
        $image = imagecreatefromstring($prepared['bytes']);
        $this->assertSame(127, (imagecolorat($image, 0, 0) >> 24) & 127);
        $coloredPixels = 0;
        for ($y = 0; $y < imagesy($image); $y++) {
            for ($x = 0; $x < imagesx($image); $x++) {
                $color = imagecolorat($image, $x, $y);
                if ((($color >> 24) & 127) < 120 && ($color & 0xFFFFFF) !== 0) {
                    $coloredPixels++;
                }
            }
        }
        imagedestroy($image);
        $this->assertSame(0, $coloredPixels);
    }

    public function test_crop_padding_does_not_make_a_maximum_size_image_invalid(): void
    {
        $image = imagecreatetruecolor(4096, 1024);
        imagefill($image, 0, 0, imagecolorallocate($image, 255, 255, 255));
        imagerectangle($image, 0, 0, 4095, 1023, imagecolorallocate($image, 0, 0, 0));
        ob_start();
        imagepng($image);
        $bytes = ob_get_clean();
        imagedestroy($image);
        unset($image);
        $images = app(SignatureImageService::class);
        $prepared = $images->prepare($bytes, 'upload');
        $this->assertSame(4096, $prepared['width']);
        $this->assertSame(1024, $prepared['height']);
        $this->assertSame($prepared['sha256'], $images->prepare($prepared['bytes'], 'upload')['sha256']);
    }

    public function test_identical_approved_signature_is_not_submitted_again(): void
    {
        $pilot = $this->user();
        $request = $this->submit($pilot);
        app(ProfileUpdateRequestService::class)->approve($request, $this->user(UserRole::Admin));
        try {
            $this->submit($pilot);
            $this->fail('Unchanged signatures must not create a new request.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('data.signature_submission', $exception->errors());
        }
        $this->assertDatabaseCount('pilot_signatures', 1);
        $this->assertDatabaseCount('profile_update_requests', 1);
        $this->assertSame(2, $pilot->auditLogs()->count());
    }

    public function test_prepare_validates_actual_image_dimensions_size_and_ink_without_persisting(): void
    {
        $url = route('pilot-signatures.prepare');
        $this->actingAs($this->user())->postJson($url, ['source' => 'upload', 'image' => UploadedFile::fake()->createWithContent('signature.png', 'not an image')])->assertUnprocessable();
        $this->postJson($url, ['source' => 'upload', 'image' => UploadedFile::fake()->image('wide.png', 4097, 40)])->assertUnprocessable();
        $this->postJson($url, ['source' => 'draw', 'image' => UploadedFile::fake()->createWithContent('blank.png', $this->image(-1))])->assertUnprocessable();
        $this->postJson($url, ['source' => 'upload', 'image' => UploadedFile::fake()->createWithContent('big.png', $this->image().str_repeat('a', 2097152))])->assertUnprocessable();
        $this->postJson($url, ['source' => 'upload', 'image' => UploadedFile::fake()->createWithContent('signature.png', $this->image())])->assertOk()->assertJsonStructure(['data_url', 'width', 'height']);
        $this->assertSame([], Storage::disk('local')->allFiles());
        $this->assertDatabaseCount('pilot_signatures', 0);
        $this->actingAs($this->user(UserRole::Dispatch))->postJson($url, [])->assertForbidden();
    }

    public function test_changed_bytes_block_approval_and_roll_back_profile_changes(): void
    {
        $pilot = $this->user();
        $originalName = $pilot->first_name;
        $request = app(ProfileUpdateRequestService::class)->submit($pilot, ['user.first_name' => 'Updated'], null, signatureSubmission: $this->submission());
        Storage::disk('local')->put($request->signature->stored_path, 'tampered');
        try {
            app(ProfileUpdateRequestService::class)->approve($request, $this->user(UserRole::Admin));
            $this->fail('Integrity failure must stop approval.');
        } catch (HttpException $exception) {
            $this->assertSame(409, $exception->getStatusCode());
        }
        $this->assertSame($originalName, $pilot->fresh()->first_name);
        $this->assertNull($pilot->approvedSignature()->first());
        $this->assertSame('pending', $request->fresh()->status->value);
        $this->assertSame(1, $pilot->auditLogs()->count());
    }

    public function test_profile_placement_signature_only_form_and_reviewer_preview(): void
    {
        $pilot = $this->user();
        $this->actingAs($pilot)->get(MyProfilePage::getUrl(panel: 'pilot'))->assertOk()
            ->assertSeeInOrder(['Operator Assignment', 'Signature', 'No approved signature', 'Submit Signature for Verification', 'Verification Record']);
        Filament::setCurrentPanel(Filament::getPanel('pilot'));
        Livewire::actingAs($pilot)->test(EditMyProfilePage::class)
            ->assertSee('Draw Signature')->assertSee('Upload Signature')
            ->fillForm(['signature_submission' => $this->submission(), 'reason' => 'Signature verification.'])
            ->call('save')->assertHasNoFormErrors()->assertRedirect();
        $request = $pilot->profileUpdateRequests()->firstOrFail();
        $this->assertSame(['pilot_signature'], array_keys($request->requested_changes));
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        Livewire::actingAs($this->user(UserRole::Admin))->test(EditProfileUpdateRequest::class, ['record' => $request->getKey()])
            ->assertSee('Signature Verification')->assertSee('Inspect full-resolution signature')->assertSee($request->signature->sha256)
            ->callAction('approve', ['remarks' => 'Signature compared with KYC evidence.'])->assertNotified();
        $this->assertTrue($pilot->approvedSignature()->first()->is($request->signature));
    }

    public function test_version_contents_and_history_cannot_be_edited_or_deleted(): void
    {
        $signature = $this->submit($this->user())->signature;
        foreach (['change', 'delete'] as $operation) {
            try {
                if ($operation === 'change') {
                    $signature->forceFill(['sha256' => str_repeat('0', 64)])->save();
                } else {
                    $signature->fresh()->delete();
                }
                $this->fail('Signature history must remain immutable.');
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey('signature', $exception->errors());
            }
        }
        $this->assertDatabaseCount('pilot_signatures', 1);
    }

    public function test_invalid_submission_and_audit_failure_leave_no_request_or_file(): void
    {
        $pilot = $this->user();
        try {
            app(ProfileUpdateRequestService::class)->submit($pilot, [], null, signatureSubmission: '{"source":"draw","data_url":"invalid"}');
            $this->fail('Malformed image submission must fail.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('data.signature_submission', $exception->errors());
        }
        $this->mock(ProfileAuditRecorder::class)
            ->shouldReceive('recordFieldChange')->andThrow(new \RuntimeException('Audit unavailable'));
        try {
            $this->submit($pilot);
            $this->fail('Audit failure must roll back the signature.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Audit unavailable', $exception->getMessage());
        }
        $this->assertDatabaseCount('pilot_signatures', 0);
        $this->assertDatabaseCount('profile_update_requests', 0);
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_approval_refuses_a_different_version_reference(): void
    {
        $pilot = $this->user();
        $request = $this->submit($pilot);
        $changes = $request->requested_changes;
        $changes['pilot_signature']['new']++;
        $request->update(['requested_changes' => $changes]);
        try {
            app(ProfileUpdateRequestService::class)->approve($request, $this->user(UserRole::Admin));
            $this->fail('Review must match the submitted version.');
        } catch (HttpException $exception) {
            $this->assertSame(409, $exception->getStatusCode());
        }
        $this->assertNull($pilot->approvedSignature()->first());
        $this->assertSame('pending', $request->fresh()->status->value);
    }

    public function test_synthetic_factory_and_seeder_produce_pending_versions_only(): void
    {
        $signature = PilotSignature::factory()->create();
        $this->assertSame($signature->sha256, hash('sha256', Storage::disk('local')->get($signature->stored_path)));
        $this->assertNull($signature->active_user_id);
        $this->seed(PilotSignatureSeeder::class);
        $this->assertDatabaseCount('pilot_signatures', 2);
        $this->assertSame(0, PilotSignature::query()->whereNotNull('active_user_id')->count());
    }

    private function user(UserRole $role = UserRole::Pilot, bool $active = true): User
    {
        return User::factory()->create(['role' => $role, 'is_active' => $active, 'station' => $role === UserRole::Atmo ? 'RPUS' : null]);
    }

    private function submit(User $pilot, int $variant = 0): ProfileUpdateRequest
    {
        return app(ProfileUpdateRequestService::class)->submit($pilot, [], 'Signature for KYC.', signatureSubmission: $this->submission($variant));
    }

    private function submission(int $variant = 0): string
    {
        return json_encode(['source' => 'upload', 'data_url' => 'data:image/png;base64,'.base64_encode($this->image($variant))], JSON_THROW_ON_ERROR);
    }

    private function image(int $variant = 0): string
    {
        $image = imagecreatetruecolor(600, 200);
        imagefill($image, 0, 0, imagecolorallocate($image, 255, 255, 255));
        $color = imagecolorallocate($image, 25, 30, 50);
        imagesetthickness($image, 3);
        for ($x = 40; $variant >= 0 && $x < 500; $x++) {
            imageline($image, $x, (int) (90 + sin($x / (20 + $variant)) * (25 + $variant)), $x + 1, (int) (90 + sin(($x + 1) / (20 + $variant)) * (25 + $variant)), $color);
        }
        ob_start();
        imagepng($image);
        $bytes = ob_get_clean();
        imagedestroy($image);

        return $bytes;
    }
}
