<?php

namespace App\Providers\Filament;

use App\Filament\Pages\Alpha;
use App\Filament\Pages\ImportScanQr;
use App\Filament\Resources\AcceptedFlights\AcceptedFlightResource;
use App\Filament\Resources\ActiveFlights\ActiveFlightResource;
use App\Filament\Resources\AirborneFlights\AirborneFlightResource;
use App\Filament\Resources\AllFlightPlans\AllFlightResource;
use App\Filament\Resources\AtcProfiles\AtcProfileResource;
use App\Filament\Resources\AvsecProfiles\AvsecProfileResource;
use App\Filament\Resources\CompletedFlights\CompletedFlightResource;
use App\Filament\Resources\DispatchProfiles\DispatchProfileResource;
use App\Filament\Resources\ExpiredFlights\ExpiredFlightResource;
use App\Filament\Resources\Flights\FlightResource;
use App\Filament\Resources\LandedFlights\LandedFlightResource;
use App\Filament\Resources\Operators\OperatorResource;
use App\Filament\Resources\PilotProfiles\PilotProfileResource;
use App\Filament\Resources\RejectedFlights\RejectedFlightResource;
use App\Filament\Resources\Reports\AbbreviatedFlightReportResource;
use App\Filament\Resources\Reports\ActiveFlightDataResource;
use App\Filament\Resources\Reports\PostOpsLogResource;
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
                Alpha::class,
                ImportScanQr::class,
            ])
            ->resources([
                OperatorResource::class,
                PilotProfileResource::class,
                AtcProfileResource::class,
                DispatchProfileResource::class,
                AvsecProfileResource::class,
                FlightResource::class,
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
