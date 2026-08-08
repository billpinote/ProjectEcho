<?php

namespace App\Filament\Panels\Artisan\Pages;

use BackedEnum;
use Filament\Pages\Page;

class SystemHealth extends Page
{
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-wrench-screwdriver';

    protected static ?string $navigationLabel = 'System Health';

    protected static ?int $navigationSort = 1;

    protected static ?string $title = 'System Health';

    protected string $view = 'filament.pages.artisan.system-health';
}
