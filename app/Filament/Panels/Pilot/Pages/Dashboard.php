<?php

namespace App\Filament\Panels\Pilot\Pages;

use App\Filament\Panels\Pilot\Widgets\PilotDashboardWidget;
use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Widgets\Widget;

class Dashboard extends BaseDashboard
{
    /**
     * @return array<int, class-string<Widget>>
     */
    public function getWidgets(): array
    {
        return [];
    }

    /**
     * @return array<int, class-string<Widget>>
     */
    protected function getHeaderWidgets(): array
    {
        return [
            PilotDashboardWidget::class,
        ];
    }

    public function getHeaderWidgetsColumns(): int|array
    {
        return 1;
    }
}
