<?php

namespace App\Filament\Panels\Pilot\Pages;

use App\Filament\Panels\Pilot\Pages\Concerns\ResolvesPilotPanelProfileUser;
use App\Models\User;
use App\Models\UserKycDocument;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Carbon;

class MyProfilePage extends Page
{
    use ResolvesPilotPanelProfileUser;

    protected static ?string $title = 'View Profile';

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $slug = 'profile';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserCircle;

    protected string $view = 'filament.pages.my-profile';

    public array $profileData = [];

    protected function getHeaderActions(): array
    {
        return [
            Action::make('requestProfileUpdate')
                ->label('Request Profile Update')
                ->icon(Heroicon::OutlinedPencilSquare)
                ->url(EditMyProfilePage::getUrl(panel: 'pilot')),
        ];
    }

    public function getHeading(): string|Htmlable|null
    {
        return null;
    }

    public function mount(): void
    {
        $user = $this->getProfileUser()
            ->loadMissing([
                'createdBy',
                'kycDocuments.verifiedBy',
                'operator',
                'pilotProfile',
                'pilotProfile.qualifications',
            ]);

        $latestAuditLog = $user->auditLogs()
            ->with('performedBy')
            ->latest('created_at')
            ->latest('id')
            ->first();

        $licenseStatus = $this->credentialStatus($user->pilotProfile?->license_expiry_date);
        $medicalStatus = $this->credentialStatus($user->pilotProfile?->medical_expiry_date);
        $credentialStatus = $this->overallCredentialStatus($licenseStatus, $medicalStatus);
        $kycStatus = $this->kycStatus($user);

        $personalDetails = $this->filledFields([
            ['label' => 'First name', 'value' => $user->first_name],
            ['label' => 'Middle name', 'value' => $user->middle_name],
            ['label' => 'Last name', 'value' => $user->last_name],
            ['label' => 'Suffix', 'value' => $user->suffix],
            ['label' => 'Display name', 'value' => $user->display_name],
            ['label' => 'Email', 'value' => $user->email],
        ]);

        $licence = $this->filledFields([
            ['label' => 'Pilot licence', 'value' => $user->pilotProfile?->formattedLicense()],
            [
                'label' => 'Licence expiry',
                'value' => $this->formatDate($user->pilotProfile?->license_expiry_date),
                'status' => $licenseStatus,
            ],
        ]);

        $medical = $this->filledFields([
            [
                'label' => 'Medical expiry',
                'value' => $this->formatDate($user->pilotProfile?->medical_expiry_date),
                'status' => $medicalStatus,
            ],
        ]);

        $operatorAssignment = $this->filledFields([
            ['label' => 'Operator', 'value' => $user->operator?->name],
        ]);

        $verificationRecord = $this->filledFields([
            ['label' => 'Verification / KYC status', 'value' => $kycStatus['label'], 'status' => $kycStatus],
            ['label' => 'Created by', 'value' => $user->createdBy?->name ?? 'Legacy / unknown source'],
            ['label' => 'Creation date', 'value' => $this->formatDateTime($user->created_at)],
            ['label' => 'Last modified by', 'value' => $latestAuditLog?->performedBy?->name],
            ['label' => 'Last modification date', 'value' => $this->formatDateTime($latestAuditLog?->created_at)],
        ]);

        $this->profileData = [
            'identity' => [
                'name' => filled($user->fullName()) ? $user->fullName() : ($user->display_name ?: $user->name),
                'operator' => $user->operator?->name,
                'badges' => array_values(array_filter([
                    $user->is_active
                        ? ['label' => 'Active account', 'color' => 'success']
                        : ['label' => 'Inactive account', 'color' => 'danger'],
                    $credentialStatus,
                    $kycStatus['show_in_header'] ? $kycStatus : null,
                ])),
            ],
            'personal_details' => $personalDetails,
            'licence' => $licence,
            'medical' => $medical,
            'qualifications' => $this->qualifications($user),
            'operator_assignment' => $operatorAssignment,
            'verification_record' => $verificationRecord,
            'kyc_documents' => $this->kycDocuments($user),
            'remarks' => trim((string) ($user->pilotProfile?->remarks ?? '')),
        ];

        $this->profileData['update_requests'] = $user->profileUpdateRequests()
            ->latest('submitted_at')
            ->latest('id')
            ->limit(10)
            ->get()
            ->map(fn ($request): array => [
                'submitted_at' => $request->submitted_at?->timezone(config('app.timezone'))->format('d M Y H:i') ?? '',
                'status' => $request->status?->label() ?? '',
                'reason' => $request->reason ?: '',
                'reviewer_remarks' => $request->reviewer_remarks ?: $request->rejection_reason ?: '',
            ])
            ->all();
    }

    /**
     * @param  array<int, array{label: string, value: mixed, status?: array<string, string|bool>}>  $fields
     * @return array<int, array{label: string, value: string, status?: array<string, string|bool>}>
     */
    private function filledFields(array $fields): array
    {
        return collect($fields)
            ->map(function (array $field): ?array {
                $value = trim((string) ($field['value'] ?? ''));

                if ($value === '') {
                    return null;
                }

                return [
                    ...$field,
                    'value' => $value,
                ];
            })
            ->filter()
            ->values()
            ->all();
    }

    /**
     * @return array{label: string, color: string}
     */
    private function credentialStatus(mixed $date): array
    {
        if ($date === null) {
            return ['label' => 'No expiry on file', 'color' => 'gray'];
        }

        $expiry = Carbon::parse($date)->startOfDay();
        $today = now()->startOfDay();

        if ($expiry->lt($today)) {
            return ['label' => 'Expired', 'color' => 'danger'];
        }

        if ($today->diffInDays($expiry) <= 30) {
            return ['label' => 'Expiring soon', 'color' => 'warning'];
        }

        return ['label' => 'Valid', 'color' => 'success'];
    }

    /**
     * @param  array{label: string, color: string}  ...$statuses
     * @return array{label: string, color: string}
     */
    private function overallCredentialStatus(array ...$statuses): array
    {
        $labels = array_column($statuses, 'label');

        if (in_array('Expired', $labels, true)) {
            return ['label' => 'Credential expired', 'color' => 'danger'];
        }

        if (in_array('Expiring soon', $labels, true)) {
            return ['label' => 'Credential expiring soon', 'color' => 'warning'];
        }

        if (count(array_filter($labels, fn (string $label): bool => $label === 'Valid')) > 0) {
            return ['label' => 'Credentials valid', 'color' => 'success'];
        }

        return ['label' => 'Credentials incomplete', 'color' => 'gray'];
    }

    /**
     * @return array<int, array<string, string|array<string, string>>>
     */
    private function qualifications(User $user): array
    {
        return $user->pilotProfile?->qualifications
            ->sortBy(fn ($qualification): string => ($qualification->category?->value ?? '').'|'.$qualification->code)
            ->map(fn ($qualification): array => [
                'category' => $qualification->category?->label() ?? '',
                'code' => $qualification->code,
                'description' => $qualification->description,
                'expiry' => $this->formatDate($qualification->expiry_date) ?? 'No expiry',
                'status' => $qualification->expiry_date === null
                    ? ['label' => 'No Expiry', 'color' => 'gray']
                    : $this->credentialStatus($qualification->expiry_date),
            ])
            ->values()
            ->all() ?? [];
    }

    /**
     * @return array{label: string, color: string, show_in_header: bool}
     */
    private function kycStatus(User $user): array
    {
        if ($user->kycDocuments->isEmpty()) {
            return ['label' => 'No KYC documents on file', 'color' => 'gray', 'show_in_header' => false];
        }

        $verifiedCount = $user->kycDocuments->whereNotNull('verified_at')->count();

        if ($verifiedCount === $user->kycDocuments->count()) {
            return ['label' => 'KYC documents verified', 'color' => 'success', 'show_in_header' => true];
        }

        if ($verifiedCount > 0) {
            return ['label' => 'KYC partially verified', 'color' => 'warning', 'show_in_header' => true];
        }

        return ['label' => 'KYC documents pending', 'color' => 'warning', 'show_in_header' => true];
    }

    /**
     * @return array<int, array<string, string|null>>
     */
    private function kycDocuments(User $user): array
    {
        return $user->kycDocuments
            ->sortByDesc(fn (UserKycDocument $document): string => $document->verified_at?->toDateTimeString() ?? $document->created_at?->toDateTimeString() ?? '')
            ->map(fn (UserKycDocument $document): array => [
                'type' => $document->documentTypeLabel(),
                'reference' => $document->maskedIdentifier(),
                'verified_by' => $document->verifiedBy?->name,
                'verified_at' => $this->formatDateTime($document->verified_at),
                'remarks' => trim((string) ($document->remarks ?? '')) ?: null,
                'attachment_url' => filled($document->file_path) ? route('user-kyc-documents.download', $document) : null,
            ])
            ->values()
            ->all();
    }

    private function formatDate(mixed $date): ?string
    {
        return $date === null ? null : Carbon::parse($date)->format('F j, Y');
    }

    private function formatDateTime(mixed $date): ?string
    {
        return $date === null
            ? null
            : Carbon::parse($date)->timezone(config('app.timezone'))->format('d M Y H:i');
    }
}
