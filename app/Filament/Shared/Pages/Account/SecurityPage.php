<?php

namespace App\Filament\Shared\Pages\Account;

use App\Filament\Shared\Pages\Account\Concerns\ResolvesAccountUser;
use Filament\Pages\Page;

class SecurityPage extends Page
{
    use ResolvesAccountUser;

    protected static ?string $title = 'Security';

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $slug = 'security';

    protected string $view = 'filament.pages.profile-placeholder';

    protected function getViewData(): array
    {
        return [
            'message' => 'Security controls are coming soon.',
        ];
    }
}
