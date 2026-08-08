<?php

namespace App\Providers\Filament;

use App\Filament\Panels\Admin\Resources\AtcProfiles\AtcProfileResource;
use App\Filament\Panels\Admin\Resources\AvsecProfiles\AvsecProfileResource;
use App\Filament\Panels\Admin\Resources\DispatchProfiles\DispatchProfileResource;
use App\Filament\Panels\Admin\Resources\Operators\OperatorResource;
use App\Filament\Panels\Admin\Resources\PilotProfiles\PilotProfileResource;
use App\Filament\Panels\Admin\Resources\Users\UserResource;
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
                UserResource::class,
                OperatorResource::class,
                PilotProfileResource::class,
                AtcProfileResource::class,
                DispatchProfileResource::class,
                AvsecProfileResource::class,
            ]);
    }
}
