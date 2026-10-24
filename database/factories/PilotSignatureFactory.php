<?php

namespace Database\Factories;

use App\Domain\Users\Enums\UserRole;
use App\Models\PilotSignature;
use App\Models\ProfileUpdateRequest;
use App\Models\User;
use App\Services\ProfileUpdates\SignatureImageService;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * @extends Factory<PilotSignature>
 */
class PilotSignatureFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory()->state(['role' => UserRole::Pilot, 'is_active' => true]),
            'profile_update_request_id' => fn (array $attributes): int => ProfileUpdateRequest::query()->create([
                'user_id' => $attributes['user_id'], 'status' => 'pending', 'requested_changes' => [], 'submitted_at' => now(),
            ])->getKey(),
            'version' => 1,
            'status' => 'pending',
            'stored_path' => 'pilot-signatures/synthetic/'.Str::uuid().'.png',
            'sha256' => hash('sha256', self::sampleImage()),
            'source' => 'upload',
            'width' => getimagesizefromstring(self::sampleImage())[0],
            'height' => getimagesizefromstring(self::sampleImage())[1],
            'file_size' => strlen(self::sampleImage()),
            'submitted_at' => now(),
        ];
    }

    public function configure(): static
    {
        return $this->afterCreating(function (PilotSignature $signature): void {
            Storage::disk('local')->put($signature->stored_path, self::sampleImage(), ['visibility' => 'private']);
            $signature->request->update(['requested_changes' => ['pilot_signature' => [
                'label' => 'Signature', 'old' => null, 'new' => $signature->getKey(), 'sha256' => $signature->sha256,
            ]]]);
        });
    }

    public static function sampleImage(): string
    {
        $image = imagecreatetruecolor(500, 150);
        imagefill($image, 0, 0, imagecolorallocate($image, 255, 255, 255));
        imagestring($image, 5, 30, 50, 'SYNTHETIC SIGNATURE - DEMO ONLY', imagecolorallocate($image, 0, 0, 0));
        ob_start();
        imagepng($image);
        $bytes = ob_get_clean();
        imagedestroy($image);

        return app(SignatureImageService::class)->prepare($bytes, 'upload')['bytes'];
    }
}
