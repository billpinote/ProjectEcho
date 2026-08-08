<?php

namespace App\Filament\Panels\Pilot\Pages;

use Filament\Pages\Page;

class PreferencesPage extends Page
{
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
