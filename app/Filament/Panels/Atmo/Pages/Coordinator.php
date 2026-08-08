<?php

namespace App\Filament\Panels\Atmo\Pages;

use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;

class Coordinator extends Page
{
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-user-group';

    protected static ?string $navigationLabel = 'Coordinator';

    protected static ?int $navigationSort = 2;

    protected static ?string $title = 'Coordinator';

    protected string $view = 'filament.pages.coordinator';
}
