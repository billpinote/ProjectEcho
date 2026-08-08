<?php

namespace App\Filament\Panels\Pilot\Pages;

use Filament\Pages\Page;

class HelpPage extends Page
{
    protected static ?string $title = 'Help';

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $slug = 'help';

    protected string $view = 'filament.pages.profile-placeholder';

    protected function getViewData(): array
    {
        return [
            'message' => 'Help resources are coming soon.',
        ];
    }
}
