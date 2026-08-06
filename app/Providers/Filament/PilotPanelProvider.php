<?php

namespace App\Providers\Filament;

use App\Filament\Pages\EditMyProfilePage;
use App\Filament\Pages\HelpPage;
use App\Filament\Pages\MyProfilePage;
use App\Filament\Pages\PreferencesPage;
use App\Filament\Pages\SecurityPage;
use App\Filament\Resources\Flights\FlightResource;
use App\Filament\Resources\Flights\Pages\CreateFlight;
use App\Filament\Resources\MyArchivedFlights\MyArchivedFlightResource;
use App\Filament\Resources\MyCompletedFlights\MyCompletedFlightResource;
use App\Filament\Resources\MyCurrentFlights\MyCurrentFlightResource;
use App\Filament\Widgets\PilotDashboardWidget;
use App\Providers\Filament\Concerns\ConfiguresEchoPanel;
use Filament\Navigation\MenuItem;
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
                MyProfilePage::class,
                EditMyProfilePage::class,
                PreferencesPage::class,
                SecurityPage::class,
                HelpPage::class,
            ])
            ->resources([
                FlightResource::class,
                MyCurrentFlightResource::class,
                MyCompletedFlightResource::class,
                MyArchivedFlightResource::class,
            ])
            ->widgets([
                PilotDashboardWidget::class,
            ])
            ->userMenuItems([
                'profile' => MenuItem::make()
                    ->label('View Profile')
                    ->icon(Heroicon::OutlinedUserCircle)
                    ->url(fn (): string => MyProfilePage::getUrl(panel: 'pilot'))
                    ->sort(-1),
                MenuItem::make()
                    ->label('Preferences')
                    ->icon(Heroicon::OutlinedAdjustmentsHorizontal)
                    ->url(fn (): string => PreferencesPage::getUrl(panel: 'pilot'))
                    ->sort(10),
                MenuItem::make()
                    ->label('Security')
                    ->icon(Heroicon::OutlinedShieldCheck)
                    ->url(fn (): string => SecurityPage::getUrl(panel: 'pilot'))
                    ->sort(11),
                MenuItem::make()
                    ->label('Help')
                    ->icon(Heroicon::OutlinedQuestionMarkCircle)
                    ->url(fn (): string => HelpPage::getUrl(panel: 'pilot'))
                    ->sort(12),
            ])
            ->navigation(function (NavigationBuilder $builder): NavigationBuilder {
                return $builder
                    ->items([
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
                    ])
                    ->group('My Flight Plans', [
                        NavigationItem::make('Current')
                            ->icon(Heroicon::OutlinedPaperAirplane)
                            ->sort(11)
                            ->isActiveWhen(fn (): bool => original_request()->routeIs('filament.pilot.resources.my-current-flights.*'))
                            ->url(fn (): string => MyCurrentFlightResource::getUrl('index')),
                        NavigationItem::make('Completed')
                            ->icon(Heroicon::OutlinedCheckBadge)
                            ->sort(12)
                            ->isActiveWhen(fn (): bool => original_request()->routeIs('filament.pilot.resources.my-completed-flights.*'))
                            ->url(fn (): string => MyCompletedFlightResource::getUrl('index')),
                        NavigationItem::make('Archived')
                            ->icon(Heroicon::OutlinedArchiveBox)
                            ->sort(13)
                            ->isActiveWhen(fn (): bool => original_request()->routeIs('filament.pilot.resources.my-archived-flights.*'))
                            ->url(fn (): string => MyArchivedFlightResource::getUrl('index')),
                    ], collapsible: true);
            });
    }
}
