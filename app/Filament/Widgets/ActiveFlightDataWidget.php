<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\Flights\Schemas\FlightForm;
use App\Models\Flight;
use Filament\Support\Enums\FontFamily;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\PaginationMode;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Schema;

class ActiveFlightDataWidget extends TableWidget
{
    protected static ?int $sort = 2;

    protected int | string | array $columnSpan = 'full';

    protected static ?string $heading = 'Active Flight Data';

    public function table(Table $table): Table
    {
        return $table
            ->query($this->getTableQuery())
            ->poll('5s')
            ->paginated([5, 10, 25])
            ->defaultPaginationPageOption(5)
            ->paginationMode(PaginationMode::Simple)
            ->columns([
                TextColumn::make('aircraft_identification')
                    ->label('Callsign')
                    ->fontFamily(FontFamily::Mono)
                    ->searchable()
                    ->sortable()
                    ->alignCenter()
                    ->weight('bold'),
                TextColumn::make('type_of_aircraft')
                    ->label('Aircraft Type')
                    ->searchable()
                    ->sortable()
                    ->alignCenter(),
                TextColumn::make('departure_aerodrome')
                    ->label('From')
                    ->searchable()
                    ->sortable()
                    ->alignCenter(),
                TextColumn::make('destination_aerodrome')
                    ->label('To')
                    ->searchable()
                    ->sortable()
                    ->alignCenter(),
                TextColumn::make('route')
                    ->label('Route')
                    ->fontFamily(FontFamily::Mono)
                    ->searchable()
                    ->alignCenter()
                    ->limit(30)
                    ->tooltip(fn (Flight $record): ?string => filled($record->route) ? $record->route : null),
                TextColumn::make('time_start_up')
                    ->label('Start Up')
                    ->state(fn (Flight $record): ?string => FlightForm::formatTimeForForm($record->time_start_up))
                    ->alignCenter(),
                TextColumn::make('time_airborne')
                    ->label('Airborne')
                    ->state(fn (Flight $record): ?string => FlightForm::formatTimeForForm($record->time_airborne))
                    ->alignCenter(),
                TextColumn::make('time_touchdown')
                    ->label('Touchdown')
                    ->state(fn (Flight $record): ?string => FlightForm::formatTimeForForm($record->time_touchdown))
                    ->alignCenter(),
                TextColumn::make('time_shutdown')
                    ->label('Shutdown')
                    ->state(fn (Flight $record): ?string => FlightForm::formatTimeForForm($record->time_shutdown))
                    ->alignCenter(),
            ]);
    }

    protected function getTableQuery(): Builder
    {
        $query = Flight::query();

        if (Schema::hasColumn($query->getModel()->getTable(), 'status')) {
            return $query
                ->accepted()
                ->orderByRaw('case when date_of_flight is null then 1 else 0 end')
                ->orderBy('date_of_flight')
                ->orderByRaw('case when proposed_time is null then 1 else 0 end')
                ->orderBy('proposed_time')
                ->orderBy('id');
        }

        return $query
            ->whereNotNull('accepted_by_user_id')
            ->orderByRaw('case when date_of_flight is null then 1 else 0 end')
            ->orderBy('date_of_flight')
            ->orderByRaw('case when proposed_time is null then 1 else 0 end')
            ->orderBy('proposed_time')
            ->orderBy('id');
    }
}
