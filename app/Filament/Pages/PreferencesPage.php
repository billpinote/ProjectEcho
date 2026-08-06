<?php

namespace App\Filament\Pages;

use App\Filament\Pages\Concerns\ResolvesPilotPanelProfileUser;
use Filament\Pages\Page;

class PreferencesPage extends Page
{
    use ResolvesPilotPanelProfileUser;

    protected static ?string $title = 'Preferences';

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $slug = 'preferences';

    protected string $view = 'filament.pages.profile-placeholder';

    protected function getViewData(): array
    {
        return [
            'message' => 'Preferences is coming soon.',
        ];
    }
}
