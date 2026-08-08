<?php

namespace App\Filament\Shared\Resources\Flights\Pages;

use App\Domain\FlightPlans\Services\FlightPlanMutationService;
use App\Filament\Panels\Pilot\Resources\MyCurrentFlights\MyCurrentFlightResource;
use App\Filament\Shared\Resources\Flights\FlightResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\CreateRecord;
use Filament\Support\Enums\Alignment;
use Filament\Support\Enums\Width;
use Illuminate\Database\Eloquent\Model;

class CreateFlight extends CreateRecord
{
    protected static string $resource = FlightResource::class;

    protected Width|string|null $maxContentWidth = Width::Full;

    protected static bool $canCreateAnother = false;

    public static bool $formActionsAreSticky = false;

    public static string|Alignment $formActionsAlignment = Alignment::End;

    public function getTitle(): string
    {
        return 'Create New Flight Plan';
    }

    public static function authorizeResourceAccess(): void
    {
        abort_unless(static::getResource()::canCreate(), 403);
    }

    public static function canAccess(array $parameters = []): bool
    {
        return static::getResource()::canCreate();
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $user = auth()->user();

        if ($user !== null) {
            $data['filed_by_user_id'] = $user->id;
        }

        if ($user?->isPilot()) {
            $pilotProfile = $user->pilotProfile()->first();
            $operator = trim((string) ($pilotProfile?->operator ?? ''));
            $otherInformation = trim((string) ($data['other_information'] ?? ''));

            if ($operator !== '') {
                $otherInformation = trim($otherInformation === '' ? 'OPR/'.$operator : $otherInformation.' OPR/'.$operator);
            }

            $data['user_id'] = $user->id;
            $data['pilot_id'] = $user->id;
            $data['pilot_in_command'] = $user->fullName();
            $data['pilot_license_no'] = $pilotProfile?->license_number ?: $data['pilot_license_no'] ?? null;
            $data['pilot_ratings'] = $pilotProfile?->ratings ?: $data['pilot_ratings'] ?? null;
            $data['license_expiry_date'] = $pilotProfile?->license_expiry_date?->toDateString() ?: $data['license_expiry_date'] ?? null;
            $data['other_information'] = $otherInformation;
        }

        return FlightResource::normalizeFormData($data);
    }

    protected function handleRecordCreation(array $data): Model
    {
        $flight = static::getModel()::create($data);

        app(FlightPlanMutationService::class)->recordSubmission($flight, auth()->user());

        return $flight;
    }

    protected function getCreateFormAction(): Action
    {
        return parent::getCreateFormAction()
            ->label('Create Flight Plan');
    }

    protected function getRedirectUrl(): string
    {
        if (auth()->user()?->isPilot()) {
            return MyCurrentFlightResource::getUrl('index', panel: 'pilot');
        }

        return parent::getRedirectUrl();
    }
}
