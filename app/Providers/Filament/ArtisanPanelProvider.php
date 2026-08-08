<?php

namespace App\Providers\Filament;

use App\Filament\Panels\Artisan\Pages\SystemHealth;
use App\Providers\Filament\Concerns\ConfiguresEchoPanel;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;

class ArtisanPanelProvider extends PanelProvider
{
    use ConfiguresEchoPanel;

    public function panel(Panel $panel): Panel
    {
        return $this->configureEchoPanel($panel, 'artisan', 'artisan')
            ->pages([
                Dashboard::class,
                SystemHealth::class,
            ]);
    }
}
