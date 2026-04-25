<?php

namespace App\Filament\Resources\Flights\Tables;

use App\Filament\Resources\AcceptedFlights\AcceptedFlightResource;
use App\Filament\Resources\ActiveFlights\ActiveFlightResource;
use App\Filament\Resources\AirborneFlights\AirborneFlightResource;
use App\Filament\Resources\CompletedFlights\CompletedFlightResource;
use App\Enums\FlightPlanStatus;
use App\Filament\Resources\ExpiredFlights\ExpiredFlightResource;
use App\Filament\Resources\LandedFlights\LandedFlightResource;
use App\Filament\Resources\RejectedFlights\RejectedFlightResource;
use App\Models\Flight;
use Illuminate\Database\Eloquent\Builder;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Filament\Support\Enums\FontFamily;

class FlightsTable
{
    public static function configure(Table $table, ?string $resourceClass = null): Table
    {
        $operationalFlightResources = [
            AcceptedFlightResource::class,
            ActiveFlightResource::class,
            AirborneFlightResource::class,
            LandedFlightResource::class,
            CompletedFlightResource::class,
        ];

        $isOperationalFlightTable = in_array($resourceClass, $operationalFlightResources, true);

        $columns = [
            TextColumn::make('date_of_flight')
                ->label('DOF')
                ->date()
                ->fontFamily(FontFamily::Mono)
                ->alignCenter()
                ->extraHeaderAttributes(['class' => 'text-center'])
                ->width('20px')
                ->sortable(),
            TextColumn::make('proposed_time')
                ->label('PTD')
                ->time('H:i')
                ->fontFamily(FontFamily::Mono)
                ->alignCenter()
                ->extraHeaderAttributes(['class' => 'text-center'])
                ->width('10px')
                ->sortable(),
            TextColumn::make('aircraft_identification')
                ->label('Callsign')
                ->fontFamily(FontFamily::Mono)
                ->searchable()
                ->sortable()
                ->alignCenter()
                ->extraHeaderAttributes(['class' => 'text-center'])
                ->width('14px')
                ->weight('bold'),
            
            TextColumn::make('departure_aerodrome')
                ->label('From')
                ->width('14px')
                ->searchable()
                ->alignCenter()
                ->extraHeaderAttributes(['class' => 'text-center'])
                ->sortable()
                ->tooltip(fn (Flight $record): ?string => strtoupper((string) $record->departure_aerodrome) === 'ZZZZ'
                    ? (filled($record->other_info_dep) ? (string) $record->other_info_dep : 'Departure aerodrome details not provided.')
                    : null),
            TextColumn::make('destination_aerodrome')
                ->label('To')
                ->width('14px')
                ->searchable()
                ->alignCenter()
                ->extraHeaderAttributes(['class' => 'text-center'])
                ->sortable()
                ->tooltip(fn (Flight $record): ?string => strtoupper((string) $record->destination_aerodrome) === 'ZZZZ'
                    ? (filled($record->other_info_dest) ? (string) $record->other_info_dest : 'Destination aerodrome details not provided.')
                    : null),
            TextColumn::make('route')
                ->label('Route of Flight')
                ->fontFamily(FontFamily::Mono)
                ->searchable()
                ->extraHeaderAttributes(['class' => 'text-center'])
                ->alignCenter()
                ->limit(15)
                ->width('25px')
                ->tooltip(fn (Flight $record): ?string => filled($record->route) ? $record->route : null),
            TextColumn::make('flight_rules')
                ->badge()
                ->searchable()
                ->sortable()
                ->alignCenter()
                ->extraHeaderAttributes(['class' => 'text-center'])
                ->toggleable(isToggledHiddenByDefault: true),
            TextColumn::make('type_of_flight')
                ->label('Type')
                ->badge()
                ->searchable()
                ->sortable()
                ->alignCenter()
                ->extraHeaderAttributes(['class' => 'text-center'])
                ->toggleable(isToggledHiddenByDefault: true),
            TextColumn::make('type_of_aircraft')
                ->label('Aircraft type')
                ->searchable()
                ->alignCenter()
                ->extraHeaderAttributes(['class' => 'text-center'])
                ->toggleable(isToggledHiddenByDefault: true),
            TextColumn::make('pilot_in_command')
                ->label('PIC')
                ->searchable()
                ->alignCenter()
                ->extraHeaderAttributes(['class' => 'text-center'])
                ->toggleable(isToggledHiddenByDefault: true),
            TextColumn::make('accepted_by_wiresign')
                ->label('Accepted By')
                ->searchable()
                ->alignCenter()
                ->extraHeaderAttributes(['class' => 'text-center'])
                ->toggleable(isToggledHiddenByDefault: true),
            TextColumn::make('rejected_by_wiresign')
                ->label('Rejected By')
                ->searchable()
                ->alignCenter()
                ->extraHeaderAttributes(['class' => 'text-center'])
                ->toggleable(isToggledHiddenByDefault: true),
            TextColumn::make('rejection_reason')
                ->label('Reject Reason')
                ->searchable()
                ->alignCenter()
                ->extraHeaderAttributes(['class' => 'text-center'])
                ->toggleable(isToggledHiddenByDefault: true),
            IconColumn::make('authorized_representative_enabled')
                ->label('Rep')
                ->boolean()
                ->alignCenter()
                ->extraHeaderAttributes(['class' => 'text-center'])
                ->toggleable(isToggledHiddenByDefault: true),
            TextColumn::make('created_at')
                ->dateTime()
                ->sortable()
                ->alignCenter()
                ->extraHeaderAttributes(['class' => 'text-center'])
                ->toggleable(isToggledHiddenByDefault: true),
            TextColumn::make('updated_at')
                ->dateTime()
                ->sortable()
                ->alignCenter()
                ->extraHeaderAttributes(['class' => 'text-center'])
                ->toggleable(isToggledHiddenByDefault: true),
        ];

        if ($isOperationalFlightTable) {
            $columns = array_values(array_filter(
                $columns,
                fn (TextColumn|IconColumn $column): bool => ! in_array($column->getName(), ['date_of_flight', 'accepted_by_wiresign'], true),
            ));
        }

        if ($resourceClass === RejectedFlightResource::class) {
            $columns[] = TextColumn::make('rejected_by_wiresign')
                ->label('ATMO')
                ->alignCenter()
                ->extraHeaderAttributes(['class' => 'text-center'])
                ->width('10px')
                ->searchable();
        }

        if ($resourceClass === ExpiredFlightResource::class) {
            $columns[] = TextColumn::make('expiration_reason')
                ->label('Expired Reason')
                ->state(fn (Flight $record): ?string => $record->expiration_reason)
                ->wrap();
        }

        return $table
            ->when(
                $resourceClass === \App\Filament\Resources\Flights\FlightResource::class,
                fn (Table $table): Table => $table->poll('5s')
            )
            ->when(
                filled($resourceClass),
                fn (Table $table): Table => $table
                    ->recordUrl(fn (Flight $record): string => route('flights.view', $record))
                    ->openRecordUrlInNewTab()
            )
            ->modifyQueryUsing(
                fn (Builder $query): Builder => $isOperationalFlightTable || $resourceClass === \App\Filament\Resources\Flights\FlightResource::class
                    ? $query
                        ->orderByRaw('case when date_of_flight is null then 1 else 0 end')
                        ->orderBy('date_of_flight')
                        ->orderByRaw('case when proposed_time is null then 1 else 0 end')
                        ->orderBy('proposed_time')
                        ->orderBy('id')
                    : $query
                        ->orderByDesc('created_at')
                        ->orderByDesc('id')
            )
            ->recordClasses(fn (Flight $record): array => $resourceClass === \App\Filament\Resources\Flights\FlightResource::class && $record->reviewed_at === null
                ? ['echo-new-flight-row']
                : [])
            ->columns($columns)
            ->filters([
                SelectFilter::make('flight_rules')
                    ->options([
                        'I' => 'IFR',
                        'V' => 'VFR',
                        'Y' => 'IFR then VFR',
                        'Z' => 'VFR then IFR',
                    ]),
                SelectFilter::make('type_of_flight')
                    ->options([
                        'S' => 'Scheduled',
                        'N' => 'Non-scheduled',
                        'G' => 'General aviation',
                        'M' => 'Military',
                        'X' => 'Other',
                    ]),
                SelectFilter::make('accepted_by_user_id')
                    ->label('Accepted by')
                    ->relationship('acceptedBy', 'name')
                    ->searchable()
                    ->preload(),
            ])
            ->recordActions($isOperationalFlightTable ? [] : [
                Action::make('qr')
                    ->label('QR')
                    ->url(fn (Flight $record): string => route('flights.qr', $record))
                    ->openUrlInNewTab(),
                Action::make('view')
                    ->label('View')
                    ->url(fn (Flight $record): string => route('flights.view', $record))
                    ->openUrlInNewTab(),
                Action::make('pdf')
                    ->label('PDF')
                    ->url(fn (Flight $record): string => route('flights.pdf.download', $record))
                    ->openUrlInNewTab(),
                EditAction::make(),
            ]);
    }
}
