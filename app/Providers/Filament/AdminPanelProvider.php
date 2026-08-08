<?php

namespace App\Providers\Filament;

use App\Filament\Shared\Resources\AtcProfiles\AtcProfileResource;
use App\Filament\Shared\Resources\AvsecProfiles\AvsecProfileResource;
use App\Filament\Shared\Resources\DispatchProfiles\DispatchProfileResource;
use App\Filament\Shared\Resources\Operators\OperatorResource;
use App\Filament\Shared\Resources\PilotProfiles\PilotProfileResource;
use App\Providers\Filament\Concerns\ConfiguresEchoPanel;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;

class AdminPanelProvider extends PanelProvider
{
    use ConfiguresEchoPanel;

    public function panel(Panel $panel): Panel
    {
        return $this->configureEchoPanel($panel, 'admin', 'admin')
            ->default()
            ->pages([
                Dashboard::class,
            ])
            ->resources([
                OperatorResource::class,
                PilotProfileResource::class,
                AtcProfileResource::class,
                DispatchProfileResource::class,
                AvsecProfileResource::class,
            ]);
    }
}
