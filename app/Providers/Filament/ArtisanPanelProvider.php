<?php

namespace App\Providers\Filament;

use App\Filament\Panels\Artisan\Pages\Alpha;
use App\Filament\Panels\Artisan\Pages\ImportScanQr;
use App\Filament\Panels\Artisan\Resources\AcceptedFlights\AcceptedFlightResource;
use App\Filament\Panels\Artisan\Resources\ActiveFlights\ActiveFlightResource;
use App\Filament\Panels\Artisan\Resources\AirborneFlights\AirborneFlightResource;
use App\Filament\Panels\Artisan\Resources\AllFlightPlans\AllFlightResource;
use App\Filament\Panels\Artisan\Resources\AtcProfiles\AtcProfileResource;
use App\Filament\Panels\Artisan\Resources\AvsecProfiles\AvsecProfileResource;
use App\Filament\Panels\Artisan\Resources\CompletedFlights\CompletedFlightResource;
use App\Filament\Panels\Artisan\Resources\DispatchProfiles\DispatchProfileResource;
use App\Filament\Panels\Artisan\Resources\ExpiredFlights\ExpiredFlightResource;
use App\Filament\Panels\Artisan\Resources\Flights\FlightResource;
use App\Filament\Panels\Artisan\Resources\LandedFlights\LandedFlightResource;
use App\Filament\Panels\Artisan\Resources\Operators\OperatorResource;
use App\Filament\Panels\Artisan\Resources\PilotProfiles\PilotProfileResource;
use App\Filament\Panels\Artisan\Resources\RejectedFlights\RejectedFlightResource;
use App\Filament\Panels\Artisan\Resources\Reports\AbbreviatedFlightReports\AbbreviatedFlightReportResource;
use App\Filament\Panels\Artisan\Resources\Reports\ActiveFlightData\ActiveFlightDataResource;
use App\Filament\Panels\Artisan\Resources\Reports\PostOpsLogs\PostOpsLogResource;
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
