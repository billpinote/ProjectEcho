<?php

namespace App\Providers\Filament;

use App\Filament\Panels\Ats\Resources\AcceptedFlights\AcceptedFlightResource;
use App\Filament\Panels\Ats\Resources\ActiveFlights\ActiveFlightResource;
use App\Filament\Panels\Ats\Resources\AirborneFlights\AirborneFlightResource;
use App\Filament\Panels\Ats\Resources\AllFlightPlans\AllFlightResource;
use App\Filament\Panels\Ats\Resources\CompletedFlights\CompletedFlightResource;
use App\Filament\Panels\Ats\Resources\ExpiredFlights\ExpiredFlightResource;
use App\Filament\Panels\Ats\Resources\LandedFlights\LandedFlightResource;
use App\Filament\Panels\Ats\Resources\RejectedFlights\RejectedFlightResource;
use App\Filament\Panels\Ats\Resources\Reports\AbbreviatedFlightReports\AbbreviatedFlightReportResource;
use App\Filament\Panels\Ats\Resources\Reports\ActiveFlightData\ActiveFlightDataResource;
use App\Filament\Panels\Ats\Resources\Reports\PostOpsLogs\PostOpsLogResource;
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
