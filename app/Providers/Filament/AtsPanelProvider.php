<?php

namespace App\Providers\Filament;

use App\Filament\Shared\Resources\AcceptedFlights\AcceptedFlightResource;
use App\Filament\Shared\Resources\ActiveFlights\ActiveFlightResource;
use App\Filament\Shared\Resources\AirborneFlights\AirborneFlightResource;
use App\Filament\Shared\Resources\AllFlightPlans\AllFlightResource;
use App\Filament\Shared\Resources\CompletedFlights\CompletedFlightResource;
use App\Filament\Shared\Resources\ExpiredFlights\ExpiredFlightResource;
use App\Filament\Shared\Resources\LandedFlights\LandedFlightResource;
use App\Filament\Shared\Resources\RejectedFlights\RejectedFlightResource;
use App\Filament\Shared\Resources\Reports\AbbreviatedFlightReportResource;
use App\Filament\Shared\Resources\Reports\ActiveFlightDataResource;
use App\Filament\Shared\Resources\Reports\PostOpsLogResource;
use App\Providers\Filament\Concerns\ConfiguresEchoPanel;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;

class AtsPanelProvider extends PanelProvider
{
    use ConfiguresEchoPanel;

    public function panel(Panel $panel): Panel
    {
        return $this->configureEchoPanel($panel, 'ats', 'ats')
            ->pages([
                Dashboard::class,
            ])
            ->resources([
                AllFlightResource::class,
                AcceptedFlightResource::class,
                ActiveFlightResource::class,
                AirborneFlightResource::class,
                LandedFlightResource::class,
                CompletedFlightResource::class,
                ExpiredFlightResource::class,
                RejectedFlightResource::class,
                ActiveFlightDataResource::class,
                AbbreviatedFlightReportResource::class,
                PostOpsLogResource::class,
            ]);
    }
}
