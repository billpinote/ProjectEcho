<?php

namespace App\Providers\Filament;

use App\Filament\Panels\Dispatch\Resources\AcceptedFlights\AcceptedFlightResource;
use App\Filament\Panels\Dispatch\Resources\ActiveFlights\ActiveFlightResource;
use App\Filament\Panels\Dispatch\Resources\CompletedFlights\CompletedFlightResource;
use App\Filament\Panels\Dispatch\Resources\LandedFlights\LandedFlightResource;
use App\Providers\Filament\Concerns\ConfiguresEchoPanel;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;

class DispatchPanelProvider extends PanelProvider
{
    use ConfiguresEchoPanel;

    public function panel(Panel $panel): Panel
    {
        return $this->configureEchoPanel($panel, 'dispatch', 'dispatch')
            ->pages([
                Dashboard::class,
            ])
            ->resources([
                AcceptedFlightResource::class,
                ActiveFlightResource::class,
                LandedFlightResource::class,
                CompletedFlightResource::class,
            ]);
    }
}
