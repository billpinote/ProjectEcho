<?php

namespace App\Filament\Pages;

use App\Filament\Resources\Flights\FlightResource;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;

class FlightPlan extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPaperAirplane;

    protected static ?string $navigationLabel = 'Flight Plan';

    protected static ?int $navigationSort = 10;

    protected string $view = 'filament.pages.flight-plan';

    public function mount(): void
    {
        $this->redirect(FlightResource::getUrl());
    }
}
