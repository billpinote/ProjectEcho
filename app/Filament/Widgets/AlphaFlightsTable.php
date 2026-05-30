<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\Flights\Schemas\FlightForm;
use App\Models\Flight;
use Filament\Support\Enums\FontFamily;
use Filament\Support\Enums\FontWeight;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

class AlphaFlightsTable extends TableWidget
{
    protected static ?string $heading = 'Alpha Flights';

    protected int | string | array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Flight::query()
                    ->accepted()
                    ->where(function (Builder $query): void {
                        $query
                            ->whereNull('date_of_flight')
                            ->orWhereDate('date_of_flight', now('Asia/Manila')->toDateString());
                    })
            )
            ->defaultSort('proposed_time')
            ->columns([
                TextColumn::make('aircraft_identification')
                    ->label('Callsign')
                    ->fontFamily(FontFamily::Mono)
                    ->weight(FontWeight::Black)
                    ->size('lg')
                    ->searchable()
                    ->sortable()
                    ->extraHeaderAttributes([
                        'class' => 'text-left text-base font-black uppercase tracking-[0.2em] text-slate-900',
                    ])
                    ->extraCellAttributes([
                        'class' => 'text-lg font-black tracking-wide text-slate-950',
                    ]),
                TextColumn::make('proposed_time')
                    ->label('PTD')
                    ->state(fn (Flight $record): ?string => FlightForm::formatTimeForForm($record->proposed_time))
                    ->fontFamily(FontFamily::Mono)
                    ->alignCenter()
                    ->searchable()
                    ->sortable()
                    ->extraHeaderAttributes([
                        'class' => 'text-center font-semibold uppercase tracking-wide text-slate-700',
                    ]),
                TextColumn::make('time_block_off')
                    ->label('Block Off')
                    ->state(fn (Flight $record): ?string => FlightForm::formatTimeForForm($record->time_block_off))
                    ->placeholder('-')
                    ->fontFamily(FontFamily::Mono)
                    ->alignCenter()
                    ->searchable()
                    ->sortable()
                    ->extraHeaderAttributes([
                        'class' => 'text-center font-semibold uppercase tracking-wide text-slate-700',
                    ]),
                TextColumn::make('route')
                    ->label('Route')
                    ->fontFamily(FontFamily::Mono)
                    ->wrap()
                    ->searchable()
                    ->sortable()
                    ->extraHeaderAttributes([
                        'class' => 'text-center font-semibold uppercase tracking-wide text-slate-700',
                    ]),
                TextColumn::make('destination_aerodrome')
                    ->label('Destination')
                    ->alignCenter()
                    ->searchable()
                    ->sortable()
                    ->extraHeaderAttributes([
                        'class' => 'text-center font-semibold uppercase tracking-wide text-slate-700',
                    ]),
            ])
            ->poll('10s')
            ->striped()
            ->paginated([10, 25, 50]);
    }
}

