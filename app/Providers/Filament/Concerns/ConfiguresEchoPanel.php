<?php

namespace App\Providers\Filament\Concerns;

use App\Http\Middleware\RedirectExpiredPanelLogout;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\MenuItem;
use Filament\Panel;
use Filament\Support\Colors\Color;
use Filament\Support\Icons\Heroicon;
use Filament\View\PanelsRenderHook;
use Filament\Widgets\AccountWidget;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

trait ConfiguresEchoPanel
{
    protected function configureEchoPanel(Panel $panel, string $id, string $path): Panel
    {
        $accountNamespace = 'App\\Filament\\Panels\\'.ucfirst($id).'\\Pages\\';

        return $panel
            ->id($id)
            ->path($path)
            ->login()
            ->pages(array_map(fn (string $page): string => $accountNamespace.$page, [
                'MyProfilePage', 'PreferencesPage', 'SecurityPage', 'HelpPage',
            ]))
            ->userMenuItems([
                'profile' => MenuItem::make()
                    ->label('View Profile')
                    ->icon(Heroicon::OutlinedUserCircle)
                    ->url(fn (): string => route('filament.'.$id.'.pages.profile'))
                    ->sort(-1),
                MenuItem::make()
                    ->label('Preferences')
                    ->icon(Heroicon::OutlinedAdjustmentsHorizontal)
                    ->url(fn (): string => route('filament.'.$id.'.pages.preferences'))
                    ->sort(10),
                MenuItem::make()
                    ->label('Security')
                    ->icon(Heroicon::OutlinedShieldCheck)
                    ->url(fn (): string => route('filament.'.$id.'.pages.security'))
                    ->sort(11),
                MenuItem::make()
                    ->label('Help')
                    ->icon(Heroicon::OutlinedQuestionMarkCircle)
                    ->url(fn (): string => route('filament.'.$id.'.pages.help'))
                    ->sort(12),
            ])
            ->viteTheme('resources/css/filament/admin/theme.css')
            ->colors([
                'charcoal' => Color::hex('#364152'),
                'danger' => Color::hex('#EF4444'),
                'gray' => Color::hex('#68726B'),
                'indigo' => Color::hex('#4F46E5'),
                'info' => Color::hex('#2563EB'),
                'primary' => Color::hex('#0F5F4A'),
                'slate' => Color::hex('#334155'),
                'success' => Color::hex('#22C55E'),
                'warning' => Color::hex('#F5A524'),
            ])
            ->widgets([
                AccountWidget::class,
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                RedirectExpiredPanelLogout::class,
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
            ->globalSearch(false)
            ->renderHook(
                PanelsRenderHook::BODY_END,
                fn (): string => view('filament.components.echo-modal-root')->render(),
            );
    }
}
