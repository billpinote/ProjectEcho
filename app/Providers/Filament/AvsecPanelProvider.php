<?php

namespace App\Providers\Filament;

use App\Filament\Panels\Avsec\Pages\Dashboard;
use App\Filament\Panels\Avsec\Pages\ImportScanQr;
use App\Filament\Panels\Avsec\Resources\AllFlightPlans\AllFlightResource;
use App\Filament\Panels\Avsec\Resources\AvsecVerifications\AvsecVerificationResource;
use App\Filament\Panels\Avsec\Resources\FiledRpusDepartures\FiledRpusDepartureResource;
use App\Filament\Panels\Avsec\Widgets\AllFlights;
use App\Providers\Filament\Concerns\ConfiguresEchoPanel;
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
                FiledRpusDepartureResource::class,
                AvsecVerificationResource::class,
                AllFlightResource::class,
            ])->widgets([AllFlights::class]);
    }
}
