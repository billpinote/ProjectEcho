<?php

namespace App\Providers\Filament;

use App\Filament\Panels\Dispatch\Pages\EditFlightManifest;
use App\Filament\Panels\Dispatch\Pages\ListFlightManifests;
use App\Filament\Panels\Dispatch\Pages\ScanAuthorizationQr;
use App\Filament\Panels\Dispatch\Resources\AcceptedFlights\AcceptedFlightResource;
use App\Filament\Panels\Dispatch\Resources\ActiveFlights\ActiveFlightResource;
use App\Filament\Panels\Dispatch\Resources\AwaitingAuthorizationFlights\AwaitingAuthorizationFlightResource;
use App\Filament\Panels\Dispatch\Resources\CompletedFlights\CompletedFlightResource;
use App\Filament\Panels\Dispatch\Resources\Flights\FlightResource;
use App\Filament\Panels\Dispatch\Resources\LandedFlights\LandedFlightResource;
use App\Providers\Filament\Concerns\ConfiguresEchoPanel;
use Filament\Navigation\NavigationBuilder;
use Filament\Navigation\NavigationItem;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Resources\Resource as FilamentResource;
use Filament\Support\Icons\Heroicon;

use function Filament\Support\original_request;

class DispatchPanelProvider extends PanelProvider
{
    use ConfiguresEchoPanel;

    public function panel(Panel $panel): Panel
    {
        return $this->configureEchoPanel($panel, 'dispatch', 'dispatch')
            ->pages([
                Dashboard::class,
                ScanAuthorizationQr::class,
                ListFlightManifests::class,
                EditFlightManifest::class,
            ])
            ->resources([
                FlightResource::class,
                AcceptedFlightResource::class,
                ActiveFlightResource::class,
                LandedFlightResource::class,
                CompletedFlightResource::class,
                AwaitingAuthorizationFlightResource::class,
            ])
            ->navigation(function (NavigationBuilder $builder): NavigationBuilder {
                return $builder
                    ->items([
                        NavigationItem::make(Dashboard::getNavigationLabel())
                            ->icon(Dashboard::getNavigationIcon())
                            ->isActiveWhen(fn (): bool => original_request()->routeIs('filament.dispatch.pages.dashboard'))
                            ->sort(-2)
                            ->url(fn (): string => Dashboard::getUrl(panel: 'dispatch')),
                        NavigationItem::make('Create Flight Plan')
                            ->icon(Heroicon::OutlinedPlusCircle)
                            ->isActiveWhen(fn (): bool => original_request()->routeIs('filament.dispatch.resources.flights.create'))
                            ->sort(0)
                            ->url(fn (): string => FlightResource::getUrl('create', panel: 'dispatch'))
                            ->visible(fn (): bool => auth()->user()?->canCreateFlightPlans() ?? false),
                        NavigationItem::make('Flight Manifests')
                            ->icon(Heroicon::OutlinedClipboardDocumentList)
                            ->isActiveWhen(fn (): bool => original_request()->routeIs('filament.dispatch.pages.manifests', 'filament.dispatch.pages.flights.*'))
                            ->sort(1)
                            ->url(fn (): string => ListFlightManifests::getUrl(panel: 'dispatch')),
                    ])
                    ->group('Flight Operations', [
                        self::dispatchResourceNavigationItem(AcceptedFlightResource::class, 'filament.dispatch.resources.accepted-flights.*'),
                        self::dispatchResourceNavigationItem(ActiveFlightResource::class, 'filament.dispatch.resources.active-flights.*'),
                        self::dispatchResourceNavigationItem(LandedFlightResource::class, 'filament.dispatch.resources.landed-flights.*'),
                        self::dispatchResourceNavigationItem(CompletedFlightResource::class, 'filament.dispatch.resources.completed-flights.*'),
                    ])
                    ->group('PIC Authorization', [
                        self::dispatchResourceNavigationItem(AwaitingAuthorizationFlightResource::class, 'filament.dispatch.resources.awaiting-authorization-flights.*'),
                        NavigationItem::make('Scan Authorization QR')
                            ->icon(Heroicon::OutlinedQrCode)
                            ->isActiveWhen(fn (): bool => original_request()->routeIs('filament.dispatch.pages.scan-authorization-qr'))
                            ->sort(2)
                            ->url(fn (): string => ScanAuthorizationQr::getUrl(panel: 'dispatch')),
                    ]);
            });
    }

    /**
     * @param  class-string<FilamentResource>  $resource
     * @param  string|array<string>  $activeRoutePattern
     */
    private static function dispatchResourceNavigationItem(string $resource, string|array $activeRoutePattern): NavigationItem
    {
        return NavigationItem::make($resource::getNavigationLabel())
            ->icon($resource::getNavigationIcon())
            ->activeIcon($resource::getActiveNavigationIcon())
            ->isActiveWhen(fn (): bool => original_request()->routeIs($activeRoutePattern))
            ->badge($resource::getNavigationBadge(), color: $resource::getNavigationBadgeColor())
            ->badgeTooltip($resource::getNavigationBadgeTooltip())
            ->sort($resource::getNavigationSort())
            ->url(fn (): string => $resource::getUrl('index', panel: 'dispatch'));
    }
}
