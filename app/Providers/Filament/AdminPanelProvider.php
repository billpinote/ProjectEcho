<?php

namespace App\Providers\Filament;

use App\Filament\Pages\ImportScanQr;
use App\Filament\Resources\AcceptedFlights\AcceptedFlightResource;
use App\Filament\Resources\ExpiredFlights\ExpiredFlightResource;
use App\Filament\Resources\Flights\FlightResource;
use App\Filament\Resources\RejectedFlights\RejectedFlightResource;
use Filament\Support\Facades\FilamentView;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationBuilder;
use Filament\Navigation\NavigationItem;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\HtmlString;
use Filament\Widgets\AccountWidget;
use Filament\Widgets\FilamentInfoWidget;
use Filament\View\PanelsRenderHook;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use function Filament\Support\original_request;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login()
            ->viteTheme('resources/css/filament/admin/theme.css')
            ->colors([
                'danger' => Color::hex('#EF4444'),
                'gray' => Color::hex('#68726B'),
                'info' => Color::hex('#2563EB'),
                'primary' => Color::hex('#0F5F4A'),
                'success' => Color::hex('#22C55E'),
                'warning' => Color::hex('#F5A524'),
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->navigation(function (NavigationBuilder $builder): NavigationBuilder {
                return $builder->items([
                    NavigationItem::make(Dashboard::getNavigationLabel())
                        ->icon(Dashboard::getNavigationIcon())
                        ->isActiveWhen(fn (): bool => original_request()->routeIs('filament.admin.pages.dashboard'))
                        ->sort(-2)
                        ->url(fn (): string => Dashboard::getUrl()),
                    NavigationItem::make('Flight Plan')
                        ->icon(Heroicon::OutlinedPaperAirplane)
                        ->sort(10)
                        ->childItems([
                            ...FlightResource::getNavigationItems(),
                            ...AcceptedFlightResource::getNavigationItems(),
                            ...RejectedFlightResource::getNavigationItems(),
                            ...ExpiredFlightResource::getNavigationItems(),
                            ...ImportScanQr::getNavigationItems(),
                        ]),
                ]);
            })
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->widgets([
                AccountWidget::class,
                FilamentInfoWidget::class,
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ])
            ->sidebarCollapsibleOnDesktop()
            ->topbar(false)
            ->breadcrumbs(false)
            ->darkMode(false)
            ->renderHook(
                PanelsRenderHook::BODY_END,
                fn (): HtmlString => new HtmlString(<<<'HTML'
<script>
document.addEventListener('livewire:init', () => {
    const refreshSidebar = () => {
        if (document.visibilityState !== 'visible') {
            return;
        }

        Livewire.dispatch('refresh-sidebar');
    };

    refreshSidebar();

    window.setInterval(refreshSidebar, 5000);

    document.addEventListener('visibilitychange', refreshSidebar);
    window.addEventListener('focus', refreshSidebar);
});
</script>
HTML),
            );
    }
}
