<?php

namespace App\Filament\Panels\Pilot\Pages\Concerns;

use App\Domain\Pilots\Enums\PilotLicenseType;
use App\Services\ProfileUpdates\ProfileUpdateRequestService;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;

trait InteractsWithPilotProfileForm
{
    use ResolvesPilotPanelProfileUser;

    /**
     * @return array<int, TextInput|Select|DatePicker|Textarea|FileUpload>
     */
    protected function getPilotProfileFormComponents(): array
    {
        return [
            TextInput::make('first_name')
                ->label('First Name')
                ->maxLength(255),
            TextInput::make('middle_name')
                ->label('Middle Name')
                ->maxLength(255),
            TextInput::make('last_name')
                ->label('Last Name')
                ->maxLength(255),
            Select::make('license_type')
                ->label('Licence Type')
                ->options(PilotLicenseType::options())
                ->native(false),
            TextInput::make('license_number')
                ->label('License Number')
                ->maxLength(255),
            DatePicker::make('license_expiry_date')
                ->label('License Expiry Date')
                ->native(false)
                ->displayFormat('F j, Y'),
            DatePicker::make('medical_expiry_date')
                ->label('Medical Expiry Date')
                ->native(false)
                ->displayFormat('F j, Y'),
            Textarea::make('remarks')
                ->label('Remarks')
                ->rows(4)
                ->columnSpanFull(),
            Textarea::make('reason')
                ->label('Reason / Explanation')
                ->required()
                ->rows(4)
                ->columnSpanFull(),
            FileUpload::make('supporting_documents')
                ->label('Supporting Documents')
                ->disk('local')
                ->directory('profile-update-request-documents')
                ->visibility('private')
                ->multiple()
                ->acceptedFileTypes(['application/pdf', 'image/jpeg', 'image/png'])
                ->maxSize(5120)
                ->columnSpanFull(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function getPilotProfileFormData(): array
    {
        $user = $this->getProfileUser();

        return [
            'first_name' => $user->first_name,
            'middle_name' => $user->middle_name,
            'last_name' => $user->last_name,
            'license_type' => $user->pilotProfile?->license_type?->value,
            'license_number' => $user->pilotProfile?->license_number,
            'license_expiry_date' => $user->pilotProfile?->license_expiry_date?->toDateString(),
            'medical_expiry_date' => $user->pilotProfile?->medical_expiry_date?->toDateString(),
            'remarks' => $user->pilotProfile?->remarks,
            'reason' => null,
            'supporting_documents' => [],
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function submitPilotProfileUpdateRequest(array $data): void
    {
        $user = $this->getProfileUser();

        app(ProfileUpdateRequestService::class)->submit($user, [
            'user.first_name' => $data['first_name'] ?? null,
            'user.middle_name' => $data['middle_name'] ?? null,
            'user.last_name' => $data['last_name'] ?? null,
            'pilot_profile.license_type' => $data['license_type'] ?? null,
            'pilot_profile.license_number' => $data['license_number'] ?? null,
            'pilot_profile.license_expiry_date' => $data['license_expiry_date'] ?? null,
            'pilot_profile.medical_expiry_date' => $data['medical_expiry_date'] ?? null,
            'pilot_profile.remarks' => $data['remarks'] ?? null,
        ], $data['reason'] ?? null, $data['supporting_documents'] ?? []);
    }

    protected function sendProfileUpdateRequestedNotification(): void
    {
        Notification::make()
            ->title('Profile update request submitted.')
            ->success()
            ->send();
    }

    protected function buildFullName(
        mixed $firstName,
        mixed $middleName,
        mixed $lastName,
        mixed $suffix = null,
    ): string {
        return trim(implode(' ', array_filter([
            $this->normalizeNullableString($firstName),
            $this->normalizeNullableString($middleName),
            $this->normalizeNullableString($lastName),
            $this->normalizeNullableString($suffix),
        ])));
    }

    protected function normalizeNullableString(mixed $value): ?string
    {
        $value = trim((string) ($value ?? ''));

        return $value === '' ? null : $value;
    }
}
