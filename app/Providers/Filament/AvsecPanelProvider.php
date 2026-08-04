<?php

namespace App\Providers\Filament;

use App\Filament\Pages\ImportScanQr;
use App\Filament\Resources\AllFlightPlans\AllFlightResource;
use App\Providers\Filament\Concerns\ConfiguresEchoPanel;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;

class AvsecPanelProvider extends PanelProvider
{
    use ConfiguresEchoPanel;

    public function panel(Panel $panel): Panel
    {
        return $this->configureEchoPanel($panel, 'avsec', 'avsec')
            ->pages([
                Dashboard::class,
                ImportScanQr::class,
            ])
            ->resources([
                AllFlightResource::class,
            ]);
    }
}
