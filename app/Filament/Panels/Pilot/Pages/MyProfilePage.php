<?php

namespace App\Filament\Panels\Pilot\Pages;

use App\Filament\Panels\Pilot\Pages\Concerns\ResolvesPilotPanelProfileUser;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;

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

    public function mount(): void
    {
        $user = $this->getProfileUser();

        $this->profileData = [
            'full_name' => filled($user->fullName()) ? $user->fullName() : ($user->name ?: 'Not provided'),
            'email' => $user->email ?: 'Not provided',
            'pilot_license_number' => $user->pilotProfile?->license_number ?: 'Not provided',
            'ratings' => $user->pilotProfile?->ratings ?: 'Not provided',
            'license_expiry_date' => $user->pilotProfile?->license_expiry_date?->format('F j, Y') ?? 'Not provided',
            'medical_expiry_date' => $user->pilotProfile?->medical_expiry_date?->format('F j, Y') ?? 'Not provided',
            'home_base' => $user->station ?: 'Not provided',
            'operator' => $user->operator?->name ?: 'Not provided',
            'remarks' => $user->pilotProfile?->remarks ?: 'Not provided',
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
}
