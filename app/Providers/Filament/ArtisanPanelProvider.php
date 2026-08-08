<?php

namespace App\Providers\Filament;

use App\Filament\Panels\Atmo\Pages\Alpha;
use App\Filament\Shared\Pages\ImportScanQr;
use App\Filament\Shared\Resources\AcceptedFlights\AcceptedFlightResource;
use App\Filament\Shared\Resources\ActiveFlights\ActiveFlightResource;
use App\Filament\Shared\Resources\AirborneFlights\AirborneFlightResource;
use App\Filament\Shared\Resources\AllFlightPlans\AllFlightResource;
use App\Filament\Shared\Resources\AtcProfiles\AtcProfileResource;
use App\Filament\Shared\Resources\AvsecProfiles\AvsecProfileResource;
use App\Filament\Shared\Resources\CompletedFlights\CompletedFlightResource;
use App\Filament\Shared\Resources\DispatchProfiles\DispatchProfileResource;
use App\Filament\Shared\Resources\ExpiredFlights\ExpiredFlightResource;
use App\Filament\Shared\Resources\Flights\FlightResource;
use App\Filament\Shared\Resources\LandedFlights\LandedFlightResource;
use App\Filament\Shared\Resources\Operators\OperatorResource;
use App\Filament\Shared\Resources\PilotProfiles\PilotProfileResource;
use App\Filament\Shared\Resources\RejectedFlights\RejectedFlightResource;
use App\Filament\Shared\Resources\Reports\AbbreviatedFlightReportResource;
use App\Filament\Shared\Resources\Reports\ActiveFlightDataResource;
use App\Filament\Shared\Resources\Reports\PostOpsLogResource;
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
