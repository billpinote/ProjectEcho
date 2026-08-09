<?php

namespace App\Filament\Panels\Pilot\Pages\Concerns;

use App\Domain\Pilots\Enums\PilotLicenseType;
use App\Domain\Pilots\Enums\PilotQualificationCategory;
use App\Models\PilotProfile;
use App\Services\ProfileUpdates\ProfileUpdateRequestService;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Html;
use Filament\Schemas\Components\Section;

trait InteractsWithPilotProfileForm
{
    use ResolvesPilotPanelProfileUser;

    /**
     * @return array<int, Section>
     */
    protected function getPilotProfileFormComponents(): array
    {
        return [
            Section::make('Request Profile Update')
                ->description('Update any information that has changed. Your current verified profile will remain active until the request is reviewed.')
                ->columnSpanFull()
                ->schema([]),
            Section::make('Update Name')
                ->description(fn (): string => 'Current: '.$this->currentLegalName())
                ->columns(2)
                ->columnSpanFull()
                ->schema([
                    TextInput::make('first_name')
                        ->label('First Name')
                        ->live(onBlur: true)
                        ->maxLength(255),
                    TextInput::make('middle_name')
                        ->label('Middle Name')
                        ->live(onBlur: true)
                        ->maxLength(255),
                    TextInput::make('last_name')
                        ->label('Last Name')
                        ->live(onBlur: true)
                        ->maxLength(255),
                    TextInput::make('suffix')
                        ->label('Suffix')
                        ->live(onBlur: true)
                        ->maxLength(255),
                ]),
            Section::make('Update Licence & Medical')
                ->description(fn (): string => implode("\n", [
                    'Current licence: '.$this->currentLicenceDisplay(),
                    'Current medical: '.$this->currentMedicalDisplay(),
                ]))
                ->columns(2)
                ->columnSpanFull()
                ->schema([
                    Select::make('license_type')
                        ->label('Licence Type')
                        ->options(PilotLicenseType::options())
                        ->live()
                        ->native(false),
                    TextInput::make('license_number')
                        ->label('Licence Number')
                        ->live(onBlur: true)
                        ->maxLength(255),
                    DatePicker::make('license_expiry_date')
                        ->label('Licence Expiry Date')
                        ->live()
                        ->native(false)
                        ->displayFormat('F j, Y'),
                    DatePicker::make('medical_expiry_date')
                        ->label('Medical Expiry Date')
                        ->live()
                        ->native(false)
                        ->displayFormat('F j, Y'),
                ]),
            Section::make('Update Ratings & Qualifications')
                ->description('Currently verified')
                ->columns(1)
                ->columnSpanFull()
                ->schema([
                    Html::make(fn (): string => $this->qualificationBadgesHtml()),
                    Repeater::make('qualification_updates')
                        ->label('Verified Qualifications')
                        ->schema($this->qualificationFormSchema(includeControls: true))
                        ->columns(2)
                        ->addable(false)
                        ->deletable(false)
                        ->reorderable(false)
                        ->default([])
                        ->columnSpanFull(),
                    Repeater::make('qualification_additions')
                        ->label('Add Qualification')
                        ->schema($this->qualificationFormSchema())
                        ->columns(2)
                        ->addActionLabel('Add Qualification')
                        ->reorderable(false)
                        ->default([])
                        ->columnSpanFull(),
                ]),
            Section::make('About This Update')
                ->columns(1)
                ->columnSpanFull()
                ->schema([
                    Textarea::make('reason')
                        ->label('Why are you requesting these changes?')
                        ->required()
                        ->rows(4)
                        ->helperText('Briefly explain what changed.'),
                    FileUpload::make('supporting_documents')
                        ->label('Supporting Documents')
                        ->disk('local')
                        ->directory('profile-update-request-documents')
                        ->visibility('private')
                        ->multiple()
                        ->acceptedFileTypes(['application/pdf', 'image/jpeg', 'image/png'])
                        ->maxSize(5120)
                        ->helperText('Attach your updated licence, medical certificate, rating documentation, or other supporting record where applicable.'),
                    Html::make(fn (): string => $this->changeSummaryHtml()),
                ]),
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
            'suffix' => $user->suffix,
            'license_type' => $user->pilotProfile?->license_type?->value,
            'license_number' => $user->pilotProfile?->license_number,
            'license_expiry_date' => $user->pilotProfile?->license_expiry_date?->toDateString(),
            'medical_expiry_date' => $user->pilotProfile?->medical_expiry_date?->toDateString(),
            'qualification_updates' => $this->currentQualificationFormData(),
            'qualification_additions' => [],
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
            'user.suffix' => $data['suffix'] ?? null,
            'pilot_profile.license_type' => $data['license_type'] ?? null,
            'pilot_profile.license_number' => $data['license_number'] ?? null,
            'pilot_profile.license_expiry_date' => $data['license_expiry_date'] ?? null,
            'pilot_profile.medical_expiry_date' => $data['medical_expiry_date'] ?? null,
        ], $data['reason'] ?? null, $data['supporting_documents'] ?? [], $this->qualificationOperationsFromFormData($data));
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

    protected function currentLegalName(): string
    {
        $user = $this->getProfileUser();

        return $this->buildFullName($user->first_name, $user->middle_name, $user->last_name, $user->suffix) ?: 'Not recorded';
    }

    protected function currentLicenceDisplay(): string
    {
        $profile = $this->getProfileUser()->pilotProfile;
        $licence = PilotProfile::formatLicense($profile?->license_type, $profile?->license_number);

        if ($licence === null) {
            return 'Not recorded';
        }

        $expiry = $this->formatDateForDisplay($profile?->license_expiry_date);

        return $expiry === null ? $licence : $licence.' · Expires '.$expiry;
    }

    protected function currentMedicalDisplay(): string
    {
        $date = $this->formatDateForDisplay($this->getProfileUser()->pilotProfile?->medical_expiry_date);

        return $date === null ? 'Not recorded' : 'Expires '.$date;
    }

    protected function formatDateForDisplay(mixed $date): ?string
    {
        if ($date === null) {
            return null;
        }

        return $date instanceof \DateTimeInterface
            ? $date->format('F j, Y')
            : (string) $date;
    }

    /**
     * @return array<int, Hidden|Toggle|Select|TextInput|DatePicker|Textarea>
     */
    protected function qualificationFormSchema(bool $includeControls = false): array
    {
        return [
            Hidden::make('id')
                ->dehydrated($includeControls),
            Toggle::make('request_update')
                ->label('Update qualification')
                ->live()
                ->visible($includeControls)
                ->default(false),
            Toggle::make('request_removal')
                ->label('Remove qualification')
                ->live()
                ->visible($includeControls)
                ->default(false),
            Select::make('category')
                ->label('Category')
                ->options(PilotQualificationCategory::options())
                ->live()
                ->native(false)
                ->required(),
            TextInput::make('code')
                ->label('Code')
                ->live(onBlur: true)
                ->required()
                ->maxLength(255),
            TextInput::make('description')
                ->label('Description')
                ->live(onBlur: true)
                ->maxLength(255),
            DatePicker::make('expiry_date')
                ->label('Expiry Date')
                ->live()
                ->native(false)
                ->displayFormat('F j, Y'),
            Textarea::make('remarks')
                ->label('Qualification Remarks')
                ->live(onBlur: true)
                ->rows(2)
                ->columnSpanFull(),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function currentQualificationFormData(): array
    {
        return $this->getProfileUser()
            ->pilotProfile?->qualifications()
            ->orderBy('category')
            ->orderBy('code')
            ->get()
            ->map(fn ($qualification): array => [
                'id' => $qualification->id,
                'request_update' => false,
                'request_removal' => false,
                'category' => $qualification->category?->value,
                'code' => $qualification->code,
                'description' => $qualification->description,
                'expiry_date' => $qualification->expiry_date?->toDateString(),
                'remarks' => $qualification->remarks,
            ])
            ->all() ?? [];
    }

    protected function qualificationBadgesHtml(): string
    {
        $qualifications = $this->getProfileUser()->pilotProfile?->qualifications()
            ->orderBy('category')
            ->orderBy('code')
            ->get() ?? collect();

        if ($qualifications->isEmpty()) {
            return '<p class="text-sm text-gray-600">No ratings or qualifications are recorded.</p>';
        }

        $badges = $qualifications
            ->map(fn ($qualification): string => sprintf(
                '<span class="fi-badge fi-color fi-color-primary">%s</span>',
                e($qualification->code),
            ))
            ->implode(' ');

        return '<div class="flex flex-wrap gap-2">'.$badges.'</div>';
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<int, array<string, mixed>>
     */
    protected function qualificationOperationsFromFormData(array $data): array
    {
        $operations = [];

        foreach (($data['qualification_updates'] ?? []) as $qualification) {
            if (! is_array($qualification)) {
                continue;
            }

            if ((bool) ($qualification['request_removal'] ?? false)) {
                $operations[] = [
                    'operation' => 'remove',
                    'qualification_id' => $qualification['id'] ?? null,
                ];

                continue;
            }

            if (! (bool) ($qualification['request_update'] ?? false)) {
                continue;
            }

            $operations[] = [
                'operation' => 'update',
                'qualification_id' => $qualification['id'] ?? null,
                'values' => $this->qualificationValues($qualification),
            ];
        }

        foreach (($data['qualification_additions'] ?? []) as $qualification) {
            if (! is_array($qualification)) {
                continue;
            }

            $values = $this->qualificationValues($qualification);

            if (blank($values['category'] ?? null) && blank($values['code'] ?? null)) {
                continue;
            }

            $operations[] = [
                'operation' => 'add',
                'values' => $values,
            ];
        }

        return $operations;
    }

    /**
     * @param  array<string, mixed>  $qualification
     * @return array<string, mixed>
     */
    protected function qualificationValues(array $qualification): array
    {
        return [
            'category' => $this->normalizeNullableString($qualification['category'] ?? null),
            'code' => $this->normalizeNullableString($qualification['code'] ?? null),
            'description' => $this->normalizeNullableString($qualification['description'] ?? null),
            'expiry_date' => $this->normalizeNullableString($qualification['expiry_date'] ?? null),
            'remarks' => $this->normalizeNullableString($qualification['remarks'] ?? null),
        ];
    }

    protected function changeSummaryHtml(): string
    {
        $user = $this->getProfileUser();
        $data = $this->data ?? [];
        $ordinaryChanges = \App\Services\ProfileUpdates\ProfileFieldRegistry::changesForRequest($user, [
            'user.first_name' => $data['first_name'] ?? null,
            'user.middle_name' => $data['middle_name'] ?? null,
            'user.last_name' => $data['last_name'] ?? null,
            'user.suffix' => $data['suffix'] ?? null,
            'pilot_profile.license_type' => $data['license_type'] ?? null,
            'pilot_profile.license_number' => $data['license_number'] ?? null,
            'pilot_profile.license_expiry_date' => $data['license_expiry_date'] ?? null,
            'pilot_profile.medical_expiry_date' => $data['medical_expiry_date'] ?? null,
        ]);

        $items = collect($ordinaryChanges)
            ->map(fn (array $change): string => (string) ($change['label'] ?? 'Profile field'))
            ->values();

        foreach ($this->qualificationOperationsFromFormData($data) as $operation) {
            if (($operation['operation'] ?? null) === 'add') {
                $values = $operation['values'] ?? [];
                $items->push('Add '.trim(implode(' ', array_filter([
                    PilotQualificationCategory::tryFrom((string) ($values['category'] ?? ''))?->label(),
                    $values['code'] ?? null,
                ]))));

                continue;
            }

            $qualification = $user->pilotProfile?->qualifications()->whereKey($operation['qualification_id'] ?? null)->first();

            if ($qualification === null) {
                continue;
            }

            $label = trim(implode(' ', array_filter([
                $qualification->category?->label(),
                filled($qualification->code) ? '('.$qualification->code.')' : null,
            ])));

            $items->push((($operation['operation'] ?? null) === 'remove' ? 'Remove ' : 'Update ').$label);
        }

        $items = $items->filter()->unique()->values();

        if ($items->isEmpty()) {
            return '<div class="rounded-lg border border-dashed border-gray-300 px-4 py-3 text-sm text-gray-600">No changes yet.</div>';
        }

        $rows = $items
            ->map(fn (string $item): string => '<li>'.e($item).'</li>')
            ->implode('');

        return sprintf(
            '<div class="rounded-lg border border-gray-200 px-4 py-3 text-sm"><p class="font-semibold text-gray-950">%d %s will be submitted</p><ul class="mt-2 list-disc space-y-1 pl-5 text-gray-600">%s</ul></div>',
            $items->count(),
            str('change')->plural($items->count()),
            $rows,
        );
    }
}
