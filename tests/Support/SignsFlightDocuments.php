<?php

namespace Tests\Support;

use App\Domain\FlightPlans\Enums\FlightPlanStatus;
use App\Domain\FlightPlans\Services\AvsecVerificationService;
use App\Domain\FlightPlans\Services\FilingAuthorizationService;
use App\Domain\FlightPlans\Services\FlightPlanQrPayloadService;
use App\Domain\Users\Enums\UserRole;
use App\Models\Flight;
use App\Models\PilotSignature;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

trait SignsFlightDocuments
{
    protected function confirmFlightAvsec(Flight $flight): void
    {
        $officer = User::factory()->create(['role' => UserRole::Avsec, 'station' => 'RPUS']);
        app(AvsecVerificationService::class)->confirm(
            app(FlightPlanQrPayloadService::class)->buildPayload($flight->fresh()),
            $officer, true,
        );
    }

    protected function setUpSignsFlightDocuments(): void
    {
        Storage::fake('local');
    }

    /** Establish signed evidence for fixtures whose subject is downstream behavior. */
    protected function signFlightDocuments(Flight $flight): void
    {
        $pilot = $flight->pilot_in_command_user_id !== null
            ? User::findOrFail($flight->pilot_in_command_user_id) : $flight->filedBy;
        if ($pilot === null || ! $pilot->isPilot()) {
            $pilot = User::factory()->create(['role' => UserRole::Pilot]);
        }
        app(FilingAuthorizationService::class)->recordingDecision($flight,
            fn () => $flight->forceFill(['pilot_in_command_user_id' => $pilot->id])->saveQuietly());
        $signature = PilotSignature::factory()->create(['user_id' => $pilot->id, 'status' => 'approved']);
        $packages = app(FilingAuthorizationService::class);
        $package = $packages->request($flight);
        $packages->decide($package, $flight, $pilot, true, null, $signature);
        if ($flight->status === FlightPlanStatus::AwaitingPic) {
            $flight->forceFill(['status' => FlightPlanStatus::Pending])->saveQuietly();
        }
        $flight->refresh();
    }
}
