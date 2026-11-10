<?php

namespace App\Filament\Panels\Avsec\Pages;

use Filament\Support\Icons\Heroicon;

class Dashboard extends \Filament\Pages\Dashboard
{
    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedSquares2x2;

    public function getHeading(): ?string
    {
        return null;
    }
}
