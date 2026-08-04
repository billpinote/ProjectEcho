<?php

namespace App\Providers\Filament;

use App\Filament\Pages\ImportScanQr;
use App\Filament\Pages\MyProfilePage;
use App\Filament\Resources\Flights\FlightResource;
use App\Filament\Resources\Flights\Pages\CreateFlight;
use App\Filament\Resources\MyFlightPlans\MyFlightPlansResource;
use App\Filament\Widgets\PilotDashboardWidget;
use App\Providers\Filament\Concerns\ConfiguresEchoPanel;
use Filament\Navigation\NavigationBuilder;
use Filament\Navigation\NavigationItem;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Icons\Heroicon;

use function Filament\Support\original_request;

class PilotPanelProvider extends PanelProvider
{
    use ConfiguresEchoPanel;

    public function panel(Panel $panel): Panel
    {
        return $this->configureEchoPanel($panel, 'pilot', 'pilot')
            ->pages([
                Dashboard::class,
                ImportScanQr::class,
                MyProfilePage::class,
            ])
            ->resources([
                FlightResource::class,
                MyFlightPlansResource::class,
            ])
            ->widgets([
                PilotDashboardWidget::class,
            ])
            ->navigation(function (NavigationBuilder $builder): NavigationBuilder {
                return $builder->items([
                    NavigationItem::make(Dashboard::getNavigationLabel())
                        ->icon(Dashboard::getNavigationIcon())
                        ->isActiveWhen(fn (): bool => original_request()->routeIs('filament.pilot.pages.dashboard'))
                        ->sort(-2)
                        ->url(fn (): string => Dashboard::getUrl()),
                    NavigationItem::make('Create Flight Plan')
                        ->icon(Heroicon::OutlinedPlusCircle)
                        ->sort(10)
                        ->isActiveWhen(fn (): bool => original_request()->routeIs('filament.pilot.resources.flights.create'))
                        ->url(fn (): string => CreateFlight::getUrl()),
                    NavigationItem::make('My Flight Plans')
                        ->icon(Heroicon::OutlinedPaperAirplane)
                        ->sort(11)
                        ->url(fn (): string => MyFlightPlansResource::getUrl('index')),
                    NavigationItem::make('QR Import')
                        ->icon(Heroicon::OutlinedQrCode)
                        ->sort(12)
                        ->url(fn (): string => ImportScanQr::getUrl()),
                    NavigationItem::make('My Profile')
                        ->icon(Heroicon::OutlinedUserCircle)
                        ->sort(13)
                        ->url(fn (): string => MyProfilePage::getUrl()),
                ]);
            });
    }
}
