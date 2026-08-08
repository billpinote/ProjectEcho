<?php

namespace App\Filament\Panels\Pilot\Pages\Concerns;

use App\Models\User;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\DB;

trait InteractsWithPilotProfileForm
{
    use ResolvesPilotPanelProfileUser;

    /**
     * @return array<int, TextInput|DatePicker|Textarea>
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
            TextInput::make('email')
                ->label('Email')
                ->email()
                ->required()
                ->maxLength(255)
                ->unique(User::class, 'email', ignoreRecord: true),
            TextInput::make('ratings')
                ->label('Ratings')
                ->maxLength(255),
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
            'email' => $user->email,
            'license_number' => $user->pilotProfile?->license_number,
            'ratings' => $user->pilotProfile?->ratings,
            'license_expiry_date' => $user->pilotProfile?->license_expiry_date?->toDateString(),
            'medical_expiry_date' => $user->pilotProfile?->medical_expiry_date?->toDateString(),
            'remarks' => $user->pilotProfile?->remarks,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function persistPilotProfileFormData(array $data): void
    {
        $user = $this->getProfileUser();

        DB::transaction(function () use ($data, $user): void {
            $user->forceFill([
                'first_name' => $this->normalizeNullableString($data['first_name'] ?? null),
                'middle_name' => $this->normalizeNullableString($data['middle_name'] ?? null),
                'last_name' => $this->normalizeNullableString($data['last_name'] ?? null),
                'email' => trim((string) ($data['email'] ?? '')),
                'name' => $this->buildFullName(
                    $data['first_name'] ?? null,
                    $data['middle_name'] ?? null,
                    $data['last_name'] ?? null,
                    $user->suffix,
                ),
            ])->save();

            $profile = $user->pilotProfile()->firstOrCreate([]);

            $profile->forceFill([
                'license_number' => $this->normalizeNullableString($data['license_number'] ?? null),
                'ratings' => $this->normalizeNullableString($data['ratings'] ?? null),
                'license_expiry_date' => $data['license_expiry_date'] ?: null,
                'medical_expiry_date' => $data['medical_expiry_date'] ?: null,
                'remarks' => $this->normalizeNullableString($data['remarks'] ?? null),
            ])->save();
        });
    }

    protected function sendProfileUpdatedNotification(): void
    {
        Notification::make()
            ->title('Profile updated successfully.')
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
