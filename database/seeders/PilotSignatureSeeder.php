<?php

namespace Database\Seeders;

use App\Domain\Users\Enums\UserRole;
use App\Models\User;
use App\Services\ProfileUpdates\ProfileUpdateRequestService;
use Database\Factories\PilotSignatureFactory;
use Illuminate\Database\Seeder;

class PilotSignatureSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $pilot = User::factory()->create(['name' => 'Synthetic Signature Demo', 'role' => UserRole::Pilot, 'is_active' => true]);
        app(ProfileUpdateRequestService::class)->submit($pilot, [], 'Synthetic demonstration for KYC review.', signatureSubmission: json_encode([
            'source' => 'upload', 'data_url' => 'data:image/png;base64,'.base64_encode(PilotSignatureFactory::sampleImage()),
        ], JSON_THROW_ON_ERROR));
    }
}
