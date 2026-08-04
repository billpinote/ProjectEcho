<?php

namespace App\Providers\Filament;

use App\Filament\Pages\Alpha;
use App\Filament\Pages\Coordinator;
use App\Filament\Pages\ImportScanQr;
use App\Filament\Resources\AcceptedFlights\AcceptedFlightResource;
use App\Filament\Resources\ActiveFlights\ActiveFlightResource;
use App\Filament\Resources\AirborneFlights\AirborneFlightResource;
use App\Filament\Resources\AllFlightPlans\AllFlightResource;
use App\Filament\Resources\CompletedFlights\CompletedFlightResource;
use App\Filament\Resources\ExpiredFlights\ExpiredFlightResource;
use App\Filament\Resources\Flights\FlightResource;
use App\Filament\Resources\LandedFlights\LandedFlightResource;
use App\Filament\Resources\RejectedFlights\RejectedFlightResource;
use App\Filament\Resources\Reports\AbbreviatedFlightReportResource;
use App\Filament\Resources\Reports\ActiveFlightDataResource;
use App\Filament\Resources\Reports\PostOpsLogResource;
use App\Providers\Filament\Concerns\ConfiguresEchoPanel;
use Filament\Navigation\NavigationBuilder;
use Filament\Navigation\NavigationItem;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Resources\Resource as FilamentResource;

use function Filament\Support\original_request;

class AtmoPanelProvider extends PanelProvider
{
    use ConfiguresEchoPanel;

    public function panel(Panel $panel): Panel
    {
        return $this->configureEchoPanel($panel, 'atmo', 'atmo')
            ->pages([
                Dashboard::class,
                Alpha::class,
                Coordinator::class,
                ImportScanQr::class,
            ])
            ->resources([
                FlightResource::class,
                AcceptedFlightResource::class,
                ActiveFlightResource::class,
                AirborneFlightResource::class,
                LandedFlightResource::class,
                CompletedFlightResource::class,
                ExpiredFlightResource::class,
                RejectedFlightResource::class,
                AllFlightResource::class,
                AbbreviatedFlightReportResource::class,
                ActiveFlightDataResource::class,
                PostOpsLogResource::class,
            ])
            ->navigation(function (NavigationBuilder $builder): NavigationBuilder {
                return $builder
                    ->items([
                        self::atmoPageNavigationItem(Dashboard::class, 'filament.atmo.pages.dashboard', -2),
                        self::atmoPageNavigationItem(Alpha::class, 'filament.atmo.pages.alpha', 1),
                    ])
                    ->group('Coordinator', [
                        self::atmoPageNavigationItem(Coordinator::class, 'filament.atmo.pages.coordinator', 1, 'Overview'),
                        self::atmoResourceNavigationItem(
                            AbbreviatedFlightReportResource::class,
                            'filament.atmo.resources.reports.abbreviated-flight-reports.*',
                        ),
                        self::atmoResourceNavigationItem(
                            ActiveFlightDataResource::class,
                            'filament.atmo.resources.reports.active-flight-data.*',
                        ),
                        self::atmoResourceNavigationItem(
                            PostOpsLogResource::class,
                            'filament.atmo.resources.reports.post-ops-logs.*',
                        ),
                    ], collapsible: true)
                    ->group('Flight Operations', [
                        self::atmoResourceNavigationItem(FlightResource::class, 'filament.atmo.resources.flights.*'),
                        self::atmoResourceNavigationItem(AcceptedFlightResource::class, 'filament.atmo.resources.accepted-flights.*'),
                        self::atmoResourceNavigationItem(ActiveFlightResource::class, 'filament.atmo.resources.active-flights.*'),
                        self::atmoResourceNavigationItem(AirborneFlightResource::class, 'filament.atmo.resources.airborne-flights.*'),
                        self::atmoResourceNavigationItem(LandedFlightResource::class, 'filament.atmo.resources.landed-flights.*'),
                        self::atmoResourceNavigationItem(CompletedFlightResource::class, 'filament.atmo.resources.completed-flights.*'),
                        self::atmoResourceNavigationItem(ExpiredFlightResource::class, 'filament.atmo.resources.expired-flights.*'),
                        self::atmoResourceNavigationItem(RejectedFlightResource::class, 'filament.atmo.resources.rejected-flights.*'),
                        self::atmoResourceNavigationItem(AllFlightResource::class, 'filament.atmo.resources.all-flight-plans.*'),
                        self::atmoPageNavigationItem(ImportScanQr::class, 'filament.atmo.pages.import-scan-qr', 10),
                    ]);
            });
    }

    /**
     * @param  class-string<FilamentResource>  $resource
     * @param  string|array<string>  $activeRoutePattern
     */
    private static function atmoResourceNavigationItem(string $resource, string|array $activeRoutePattern): NavigationItem
    {
        return NavigationItem::make($resource::getNavigationLabel())
            ->icon($resource::getNavigationIcon())
            ->activeIcon($resource::getActiveNavigationIcon())
            ->isActiveWhen(fn (): bool => original_request()->routeIs($activeRoutePattern))
            ->badge($resource::getNavigationBadge(), color: $resource::getNavigationBadgeColor())
            ->badgeTooltip($resource::getNavigationBadgeTooltip())
            ->sort($resource::getNavigationSort())
            ->url(fn (): string => $resource::getUrl('index', panel: 'atmo'));
    }

    /**
     * @param  class-string  $page
     * @param  string|array<string>  $activeRoutePattern
     */
    private static function atmoPageNavigationItem(string $page, string|array $activeRoutePattern, ?int $sort = null, ?string $label = null): NavigationItem
    {
        return NavigationItem::make($label ?? $page::getNavigationLabel())
            ->icon($page::getNavigationIcon())
            ->activeIcon($page::getActiveNavigationIcon())
            ->isActiveWhen(fn (): bool => original_request()->routeIs($activeRoutePattern))
            ->sort($sort ?? $page::getNavigationSort())
            ->url(fn (): string => $page::getUrl(panel: 'atmo'));
    }
}
