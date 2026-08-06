<?php

namespace App\Filament\Pages;

use App\Filament\Pages\Concerns\ResolvesPilotPanelProfileUser;
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
            Action::make('updateProfile')
                ->label('Update Profile')
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
            'operator' => $user->pilotProfile?->operator ?: 'Not provided',
            'remarks' => $user->pilotProfile?->remarks ?: 'Not provided',
        ];
    }
}
