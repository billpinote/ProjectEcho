<?php

namespace App\Filament\Panels\Pilot\Widgets;

use Filament\Widgets\Widget;

class PilotDashboardWidget extends Widget
{
    protected string $view = 'filament.widgets.pilot-dashboard';

    protected static ?int $sort = 1;

    public function getColumns(): int|array
    {
        return 1;
    }
}
