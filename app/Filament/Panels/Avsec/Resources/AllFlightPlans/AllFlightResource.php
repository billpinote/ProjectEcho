<?php

namespace App\Filament\Panels\Avsec\Resources\AllFlightPlans;

use App\Filament\Panels\Avsec\Resources\AllFlightPlans\Pages\EditAllFlight;
use App\Filament\Panels\Avsec\Resources\AllFlightPlans\Pages\ListAllFlights;
use App\Filament\Shared\Resources\AllFlightPlans\AllFlightResource as SharedAllFlightResource;
use App\Models\Flight;
use Filament\Tables\Table;

class AllFlightResource extends SharedAllFlightResource
{
    protected static bool $shouldRegisterNavigation = false;

    public static function table(Table $table): Table
    {
        $table = parent::table($table)->recordActions([])->recordAction(null)
            ->recordUrl(fn (Flight $record) => route('flights.view', $record))->openRecordUrlInNewTab()
            ->recordClasses('echo-avsec-linked-row')
            ->extraRecordLinkAttributes(['class' => 'echo-avsec-record-link', 'title' => 'Opens in a new tab']);
        $table->getColumn('pilot_in_command')?->label('Pilot');
        $table->getColumn('status_presentation')?->label('AVSEC STATUS');

        return $table;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAllFlights::route('/'),
            'edit' => EditAllFlight::route('/{record}/edit'),
        ];
    }
}
