<?php

namespace App\Filament\Panels\Dispatch\Pages;

use App\Filament\Panels\Dispatch\Resources\Flights\FlightResource;
use App\Filament\Shared\Pages\ListFlightManifests as SharedListFlightManifests;
use App\Models\Flight;
use App\Models\FlightManifest;
use Filament\Tables\Table;

class ListFlightManifests extends SharedListFlightManifests
{
    public function table(Table $table): Table
    {
        return parent::table($table)
            ->recordUrl(fn (Flight $record): ?string => auth()->user()->can('create', [FlightManifest::class, $record])
                || ($record->manifest !== null && auth()->user()->can('view', $record->manifest))
                    ? route('flights.manifest.edit', $record) : null);
    }

    protected function createFlightUrl(): string
    {
        return FlightResource::getUrl('create', panel: 'dispatch');
    }
}
