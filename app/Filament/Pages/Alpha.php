<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\AlphaFlightsTable;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;

class Alpha extends Page
{
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-queue-list';

    protected static ?string $navigationLabel = 'Alpha';

    protected static ?int $navigationSort = 1;

    protected static ?string $title = 'Alpha';

    protected string $view = 'filament.pages.alpha';

    /**
     * @return array<int, class-string<\Filament\Widgets\Widget>>
     */
    protected function getHeaderWidgets(): array
    {
        return [
            AlphaFlightsTable::class,
        ];
    }

    public function getHeaderWidgetsColumns(): int|array
    {
        return 1;
    }
}
