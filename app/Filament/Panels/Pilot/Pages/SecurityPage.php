<?php

namespace App\Filament\Panels\Pilot\Pages;

use Filament\Pages\Page;

class SecurityPage extends Page
{
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
