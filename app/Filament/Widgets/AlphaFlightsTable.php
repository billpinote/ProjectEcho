<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\Flights\Schemas\FlightForm;
use App\Models\Flight;
use Filament\Support\Enums\FontFamily;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

class AlphaFlightsTable extends TableWidget
{
    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->heading(false)
            ->query(
                Flight::query()
                    ->where('status', 'accepted')
                    ->whereNull('time_airborne')
                    ->where(function ($query) {
                        $query->whereDate('date_of_flight', now('UTC')->toDateString())
                            ->orWhere(function ($sub) {
                                $sub->whereDate('date_of_flight', now('UTC')->subDay()->toDateString())
                                    ->whereTime('time_start_up', '>=', '21:00:00');
                            });
                    })
                    ->orderBy('date_of_flight', 'asc')
                    ->orderBy('time_start_up', 'asc')
            )
            ->columns([
                TextColumn::make('aircraft_identification')
                    ->label('Callsign')
                    ->searchable()
                    ->sortable()
                    ->alignCenter()
                    ->width('20px')
                    ->weight('bold')
                    ->size('md'),
                TextColumn::make('proposed_time')
                    ->label('PTD')
                    ->state(fn (Flight $record): ?string => FlightForm::formatTimeForForm($record->proposed_time))
                    ->fontFamily(FontFamily::Mono)
                    ->alignCenter()
                    ->searchable()
                    ->sortable()
                    ->extraHeaderAttributes(['class' => 'text-center'])
                    ->width('6px'),
                TextColumn::make('time_start_up')
                    ->label('START-UP TIME')
                    ->state(fn (Flight $record): ?string => FlightForm::formatTimeForForm($record->time_start_up))
                    ->placeholder('-')
                    ->fontFamily(FontFamily::Mono)
                    ->alignCenter()
                    ->searchable()
                    ->sortable()
                    ->extraHeaderAttributes(['class' => 'text-center'])
                    ->width('6px'),
                TextColumn::make('time_block_off')
                    ->label('OFF-BLOCK TIME')
                    ->state(fn (Flight $record): ?string => FlightForm::formatTimeForForm($record->time_block_off))
                    ->placeholder('-')
                    ->fontFamily(FontFamily::Mono)
                    ->alignCenter()
                    ->searchable()
                    ->sortable()
                    ->extraHeaderAttributes(['class' => 'text-center'])
                    ->width('6px'),
                TextColumn::make('route')
                    ->label('Route')
                    ->fontFamily(FontFamily::Mono)
                    ->alignCenter()
                    ->searchable()
                    ->limit(30)
                    ->width('25px')
                    ->tooltip(fn (Flight $record): ?string => filled($record->route) ? $record->route : null),
                TextColumn::make('destination_aerodrome')
                    ->label('Destination')
                    ->alignCenter()
                    ->searchable()
                    ->sortable()
                    ->extraHeaderAttributes(['class' => 'text-center'])
                    ->width('14px')
                    ->tooltip(fn (Flight $record): ?string => strtoupper((string) $record->destination_aerodrome) === 'ZZZZ'
                        ? (filled($record->other_info_dest) ? (string) $record->other_info_dest : 'Destination aerodrome details not provided.')
                        : null),
            ])
            ->poll('5s')
            ->striped()
            ->paginated([10, 25, 50]);
    }
}
