<?php

namespace App\Filament\Resources\Flights\Pages;

use App\Filament\Resources\Flights\FlightResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\CreateRecord;
use Filament\Support\Enums\Alignment;
use Filament\Support\Enums\Width;
use Illuminate\Support\Facades\Log;

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

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        Log::info('normalizeOtherInformation reached', [
            'date_of_flight' => $data['date_of_flight'] ?? null,
            'other_information' => $data['other_information'] ?? null,
        ]);
        $user = auth()->user();

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

    protected function getCreateFormAction(): Action
    {
        return parent::getCreateFormAction()
            ->label('Create Flight Plan');
    }
}
