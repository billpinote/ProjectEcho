<?php

namespace App\Filament\Resources\Flights\Tables;

use App\Filament\Resources\AcceptedFlights\AcceptedFlightResource;
use App\Filament\Resources\ActiveFlights\ActiveFlightResource;
use App\Filament\Resources\AirborneFlights\AirborneFlightResource;
use App\Filament\Resources\CompletedFlights\CompletedFlightResource;
use App\Filament\Resources\ExpiredFlights\ExpiredFlightResource;
use App\Filament\Resources\Flights\FlightResource;
use App\Filament\Resources\Flights\Schemas\FlightForm;
use App\Filament\Resources\LandedFlights\LandedFlightResource;
use App\Filament\Resources\RejectedFlights\RejectedFlightResource;
use App\Filament\Resources\Reports\ActiveFlightDataResource;
use App\Models\Flight;
use App\Rules\UtcFourDigitTime;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Support\Enums\FontFamily;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\TextInputColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Livewire\Component as LivewireComponent;

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
            ActiveFlightDataResource::class,
        ];

        $isOperationalFlightTable = in_array($resourceClass, $operationalFlightResources, true);
        $canUpdateFlights = Auth::user()?->canUpdateFlightPlans() ?? false;

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
                ->state(fn (Flight $record): ?string => FlightForm::formatTimeForForm($record->proposed_time))
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
                ->dateTime('M j, Y H:i:s')
                ->sortable()
                ->alignCenter()
                ->extraHeaderAttributes(['class' => 'text-center'])
                ->toggleable(isToggledHiddenByDefault: true),
            TextColumn::make('updated_at')
                ->dateTime('M j, Y H:i:s')
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

        if ($resourceClass === AcceptedFlightResource::class) {
            $readyColumns = [
                TextInputColumn::make('time_start_up')
                    ->label('START UP TIME')
                    ->getStateUsing(fn (Flight $record): ?string => FlightForm::formatTimeForForm($record->time_start_up))
                    ->updateStateUsing(function (Flight $record, mixed $state, LivewireComponent $livewire): ?string {
                        if (filled($state) && ! UtcFourDigitTime::isValid($state)) {
                            $livewire->dispatch(
                                'echo-modal:open',
                                heading: 'Invalid UTC Time',
                                message: UtcFourDigitTime::message('start up time'),
                                tone: 'danger',
                                buttonLabel: 'Cancel',
                            );

                            return FlightForm::formatTimeForForm($record->time_start_up);
                        }

                        $normalizedState = UtcFourDigitTime::normalizeForStorage($state);

                        $record->forceFill([
                            'time_start_up' => $normalizedState,
                        ])->save();

                        return FlightForm::formatTimeForForm($normalizedState);
                    })
                    ->disabled(! $canUpdateFlights)
                    ->inputMode('numeric')
                    ->extraInputAttributes(fn (Flight $record): array => [
                        'maxlength' => 4,
                        'class' => 'echo-status-time-input',
                        'data-confirm-status-time' => 'true',
                        'data-time-label' => 'Start Up Time',
                        'data-confirm-heading' => 'Confirm Start Up Time',
                        'data-callsign' => (string) $record->aircraft_identification,
                    ])
                    ->alignCenter()
                    ->extraHeaderAttributes(['class' => 'text-center echo-ready-start-header echo-ready-start-header-main'])
                    ->extraCellAttributes(['class' => 'echo-ready-start-cell echo-ready-start-cell-main'])
                    ->width('10px'),
                TextColumn::make('time_start_up_now')
                    ->label(' ')
                    ->state('NOW')
                    ->badge()
                    ->color('warning')
                    ->alignCenter()
                    ->extraAttributes(fn (Flight $record): array => [
                        'class' => 'echo-status-time-now-trigger',
                        'role' => 'button',
                        'tabindex' => 0,
                        'data-record-id' => (string) $record->getKey(),
                        'data-confirm-method' => 'confirmStartUpNow',
                        'data-time-label' => 'Start Up Time',
                        'data-confirm-heading' => 'Confirm Start Up Time',
                        'data-callsign' => (string) $record->aircraft_identification,
                    ])
                    ->visible($canUpdateFlights)
                    ->extraHeaderAttributes(['class' => 'echo-ready-start-header echo-ready-start-header-now'])
                    ->extraCellAttributes(['class' => 'echo-ready-start-cell echo-ready-start-cell-now'])
                    ->width('3px'),
                ...self::pickColumns($columns, [
                    'aircraft_identification',
                    'proposed_time',
                    'departure_aerodrome',
                    'destination_aerodrome',
                    'route',
                ]),
                ...self::remainingColumns($columns, [
                    'aircraft_identification',
                    'proposed_time',
                    'departure_aerodrome',
                    'destination_aerodrome',
                    'route',
                ]),
            ];

            $columns = $readyColumns;
        }

        if ($resourceClass === ActiveFlightDataResource::class) {
            $reportColumns = [
                TextColumn::make('aircraft_identification')
                    ->label('Callsign')
                    ->fontFamily(FontFamily::Mono)
                    ->searchable()
                    ->sortable()
                    ->alignCenter()
                    ->extraHeaderAttributes(['class' => 'text-center'])
                    ->width('8px')
                    ->weight('bold'),
                TextColumn::make('type_of_aircraft')
                    ->label('Type')
                    ->alignCenter()
                    ->extraHeaderAttributes(['class' => 'text-center'])
                    ->width('5px')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('departure_aerodrome')
                    ->label('From')
                    ->alignCenter()
                    ->extraHeaderAttributes(['class' => 'text-center'])
                    ->width('5px')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('destination_aerodrome')
                    ->label('To')
                    ->alignCenter()
                    ->extraHeaderAttributes(['class' => 'text-center'])
                    ->width('5px')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('route')
                    ->label('Route')
                    ->fontFamily(FontFamily::Mono)
                    ->alignCenter()
                    ->extraHeaderAttributes(['class' => 'text-center'])
                    ->searchable()
                    ->limit(30)
                    ->width('14px')
                    ->tooltip(fn (Flight $record): ?string => filled($record->route) ? $record->route : null),
                TextColumn::make('time_start_up')
                    ->label('Start Up')
                    ->state(fn (Flight $record): ?string => FlightForm::formatTimeForForm($record->time_start_up))
                    ->alignCenter()
                    ->extraHeaderAttributes(['class' => 'text-center'])
                    ->width('10px'),
                TextColumn::make('time_airborne')
                    ->label('Airborne')
                    ->state(fn (Flight $record): ?string => FlightForm::formatTimeForForm($record->time_airborne))
                    ->alignCenter()
                    ->extraHeaderAttributes(['class' => 'text-center'])
                    ->width('10px'),
                TextColumn::make('time_touchdown')
                    ->label('Touchdown')
                    ->state(fn (Flight $record): ?string => FlightForm::formatTimeForForm($record->time_touchdown))
                    ->alignCenter()
                    ->extraHeaderAttributes(['class' => 'text-center'])
                    ->width('10px'),
                TextColumn::make('time_shutdown')
                    ->label('Shutdown')
                    ->state(fn (Flight $record): ?string => FlightForm::formatTimeForForm($record->time_shutdown))
                    ->alignCenter()
                    ->extraHeaderAttributes(['class' => 'text-center'])
                    ->width('10px'),
            ];

            $columns = $reportColumns;
        }

        if ($resourceClass === ActiveFlightResource::class) {
            array_splice($columns, 2, 0, [
                TextInputColumn::make('time_airborne')
                    ->label('TAKE-OFF TIME')
                    ->getStateUsing(fn (Flight $record): ?string => FlightForm::formatTimeForForm($record->time_airborne))
                    ->updateStateUsing(function (Flight $record, mixed $state, LivewireComponent $livewire): ?string {
                        if (filled($state) && ! UtcFourDigitTime::isValid($state)) {
                            $livewire->dispatch(
                                'echo-modal:open',
                                heading: 'Invalid UTC Time',
                                message: UtcFourDigitTime::message('take-off time'),
                                tone: 'danger',
                                buttonLabel: 'Cancel',
                            );

                            return FlightForm::formatTimeForForm($record->time_airborne);
                        }

                        $normalizedState = UtcFourDigitTime::normalizeForStorage($state);

                        $record->forceFill([
                            'time_airborne' => $normalizedState,
                        ])->save();

                        return FlightForm::formatTimeForForm($normalizedState);
                    })
                    ->disabled(! $canUpdateFlights)
                    ->inputMode('numeric')
                    ->extraInputAttributes(fn (Flight $record): array => [
                        'maxlength' => 4,
                        'class' => 'echo-status-time-input',
                        'data-confirm-status-time' => 'true',
                        'data-time-label' => 'Take-Off Time',
                        'data-confirm-heading' => 'Confirm Take-Off Time',
                        'data-callsign' => (string) $record->aircraft_identification,
                    ])
                    ->alignCenter()
                    ->extraHeaderAttributes(['class' => 'text-center echo-ready-start-header echo-ready-start-header-main'])
                    ->extraCellAttributes(['class' => 'echo-ready-start-cell echo-ready-start-cell-main'])
                    ->width('12px'),
                TextColumn::make('time_airborne_now')
                    ->label(' ')
                    ->state('AIRBORNE')
                    ->badge()
                    ->color('warning')
                    ->alignCenter()
                    ->extraAttributes(fn (Flight $record): array => [
                        'class' => 'echo-status-time-now-trigger',
                        'role' => 'button',
                        'tabindex' => 0,
                        'data-record-id' => (string) $record->getKey(),
                        'data-confirm-method' => 'confirmAirborneNow',
                        'data-time-label' => 'Take-Off Time',
                        'data-confirm-heading' => 'Confirm Airborne Time',
                        'data-callsign' => (string) $record->aircraft_identification,
                    ])
                    ->visible($canUpdateFlights)
                    ->extraHeaderAttributes(['class' => 'echo-ready-start-header echo-ready-start-header-now'])
                    ->extraCellAttributes(['class' => 'echo-ready-start-cell echo-ready-start-cell-now'])
                    ->width('5px'),
            ]);

            $activeColumns = [
                ...self::pickColumns($columns, [
                    'time_airborne',
                    'time_airborne_now',
                    'aircraft_identification',                    
                    'proposed_time',
                    'departure_aerodrome',
                    'destination_aerodrome',
                    'route',
                ]),
                ...self::remainingColumns($columns, [
                    'time_airborne',
                    'time_airborne_now',
                    'aircraft_identification',
                    'proposed_time',
                    'departure_aerodrome',
                    'destination_aerodrome',
                    'route',
                ]),
            ];

            $columns = $activeColumns;
        }

        if ($resourceClass === AirborneFlightResource::class) {
            array_splice($columns, 2, 0, [
                TextInputColumn::make('time_touchdown')
                    ->label('TOUCHDOWN TIME')
                    ->getStateUsing(fn (Flight $record): ?string => FlightForm::formatTimeForForm($record->time_touchdown))
                    ->updateStateUsing(function (Flight $record, mixed $state, LivewireComponent $livewire): ?string {
                        if (filled($state) && ! UtcFourDigitTime::isValid($state)) {
                            $livewire->dispatch(
                                'echo-modal:open',
                                heading: 'Invalid UTC Time',
                                message: UtcFourDigitTime::message('touchdown time'),
                                tone: 'danger',
                                buttonLabel: 'Cancel',
                            );

                            return FlightForm::formatTimeForForm($record->time_touchdown);
                        }

                        $normalizedState = UtcFourDigitTime::normalizeForStorage($state);

                        $record->forceFill([
                            'time_touchdown' => $normalizedState,
                        ])->save();

                        return FlightForm::formatTimeForForm($normalizedState);
                    })
                    ->disabled(! $canUpdateFlights)
                    ->inputMode('numeric')
                    ->extraInputAttributes(fn (Flight $record): array => [
                        'maxlength' => 4,
                        'class' => 'echo-status-time-input',
                        'data-confirm-status-time' => 'true',
                        'data-time-label' => 'Touchdown Time',
                        'data-confirm-heading' => 'Confirm Touchdown Time',
                        'data-callsign' => (string) $record->aircraft_identification,
                    ])
                    ->alignCenter()
                    ->extraHeaderAttributes(['class' => 'text-center echo-ready-start-header echo-ready-start-header-main'])
                    ->extraCellAttributes(['class' => 'echo-ready-start-cell echo-ready-start-cell-main'])
                    ->width('12px'),
                TextColumn::make('time_touchdown_now')
                    ->label(' ')
                    ->state('LANDED')
                    ->badge()
                    ->color('warning')
                    ->alignCenter()
                    ->extraAttributes(fn (Flight $record): array => [
                        'class' => 'echo-status-time-now-trigger',
                        'role' => 'button',
                        'tabindex' => 0,
                        'data-record-id' => (string) $record->getKey(),
                        'data-confirm-method' => 'confirmTouchdownNow',
                        'data-time-label' => 'Touchdown Time',
                        'data-confirm-heading' => 'Confirm Touchdown Time',
                        'data-callsign' => (string) $record->aircraft_identification,
                    ])
                    ->visible($canUpdateFlights)
                    ->extraHeaderAttributes(['class' => 'echo-ready-start-header echo-ready-start-header-now'])
                    ->extraCellAttributes(['class' => 'echo-ready-start-cell echo-ready-start-cell-now'])
                    ->width('5px'),
            ]);

            $airborneColumns = [
                ...self::pickColumns($columns, [
                    'time_touchdown',
                    'time_touchdown_now',
                    'aircraft_identification',
                    'proposed_time',
                    'departure_aerodrome',
                    'destination_aerodrome',
                    'route',
                    'time_airborne',
                ]),
                ...self::remainingColumns($columns, [
                    'time_touchdown',
                    'time_touchdown_now',
                    'aircraft_identification',
                    'proposed_time',
                    'departure_aerodrome',
                    'destination_aerodrome',
                    'route',
                    'time_airborne',
                ]),
            ];

            $columns = $airborneColumns;
        }

        if ($resourceClass === LandedFlightResource::class) {
            array_splice($columns, 2, 0, [
                TextInputColumn::make('time_shutdown')
                    ->label('SHUTDOWN TIME')
                    ->getStateUsing(fn (Flight $record): ?string => FlightForm::formatTimeForForm($record->time_shutdown))
                    ->updateStateUsing(function (Flight $record, mixed $state, LivewireComponent $livewire): ?string {
                        if (filled($state) && ! UtcFourDigitTime::isValid($state)) {
                            $livewire->dispatch(
                                'echo-modal:open',
                                heading: 'Invalid UTC Time',
                                message: UtcFourDigitTime::message('shutdown time'),
                                tone: 'danger',
                                buttonLabel: 'Cancel',
                            );

                            return FlightForm::formatTimeForForm($record->time_shutdown);
                        }

                        $normalizedState = UtcFourDigitTime::normalizeForStorage($state);

                        $record->forceFill([
                            'time_shutdown' => $normalizedState,
                        ])->save();

                        return FlightForm::formatTimeForForm($normalizedState);
                    })
                    ->disabled(! $canUpdateFlights)
                    ->inputMode('numeric')
                    ->extraInputAttributes(fn (Flight $record): array => [
                        'maxlength' => 4,
                        'class' => 'echo-status-time-input',
                        'data-confirm-status-time' => 'true',
                        'data-time-label' => 'Shutdown Time',
                        'data-confirm-heading' => 'Confirm Shutdown Time',
                        'data-callsign' => (string) $record->aircraft_identification,
                    ])
                    ->alignCenter()
                    ->extraHeaderAttributes(['class' => 'text-center echo-ready-start-header echo-ready-start-header-main'])
                    ->extraCellAttributes(['class' => 'echo-ready-start-cell echo-ready-start-cell-main'])
                    ->width('12px'),
                TextColumn::make('time_shutdown_now')
                    ->label(' ')
                    ->state('ENGINE OFF')
                    ->badge()
                    ->color('warning')
                    ->alignCenter()
                    ->extraAttributes(fn (Flight $record): array => [
                        'class' => 'echo-status-time-now-trigger',
                        'role' => 'button',
                        'tabindex' => 0,
                        'data-record-id' => (string) $record->getKey(),
                        'data-confirm-method' => 'confirmShutdownNow',
                        'data-time-label' => 'Shutdown Time',
                        'data-confirm-heading' => 'Confirm Shutdown Time',
                        'data-callsign' => (string) $record->aircraft_identification,
                    ])
                    ->visible($canUpdateFlights)
                    ->extraHeaderAttributes(['class' => 'echo-ready-start-header echo-ready-start-header-now'])
                    ->extraCellAttributes(['class' => 'echo-ready-start-cell echo-ready-start-cell-now'])
                    ->width('6px'),
            ]);

            $landedColumns = [
                ...self::pickColumns($columns, [
                    'time_shutdown',
                    'time_shutdown_now',
                    'aircraft_identification',
                    'proposed_time',
                    'departure_aerodrome',
                    'destination_aerodrome',
                    'route',
                    'time_airborne',
                    'time_touchdown',
                ]),
                ...self::remainingColumns($columns, [
                    'time_shutdown',
                    'time_shutdown_now',
                    'aircraft_identification',
                    'proposed_time',
                    'departure_aerodrome',
                    'destination_aerodrome',
                    'route',
                    'time_airborne',
                    'time_touchdown',
                ]),
            ];

            $columns = $landedColumns;
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
                $isOperationalFlightTable || $resourceClass === FlightResource::class,
                fn (Table $table): Table => $table->poll('5s')
            )
            ->when(
                filled($resourceClass) && $resourceClass !== ActiveFlightDataResource::class,
                fn (Table $table): Table => $table
                    ->recordUrl(fn (Flight $record): string => route('flights.view', $record))
                    ->openRecordUrlInNewTab()
            )
            ->modifyQueryUsing(
                fn (Builder $query): Builder => $isOperationalFlightTable || $resourceClass === FlightResource::class
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
            ->recordClasses(fn (Flight $record): array => $resourceClass === FlightResource::class && $record->reviewed_at === null
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
                EditAction::make()
                    ->label(fn (): string => Auth::user()?->createsFlightPlanRevisionsOnly() ? 'Revise' : 'Edit'),
            ]);
    }

    /**
     * @param  array<int, TextColumn|TextInputColumn|IconColumn>  $columns
     * @param  array<int, string>  $orderedNames
     * @return array<int, TextColumn|TextInputColumn|IconColumn>
     */
    private static function pickColumns(array $columns, array $orderedNames): array
    {
        $columnsByName = [];

        foreach ($columns as $column) {
            $columnsByName[$column->getName()] = $column;
        }

        $orderedColumns = [];

        foreach ($orderedNames as $name) {
            if (array_key_exists($name, $columnsByName)) {
                $orderedColumns[] = $columnsByName[$name];
            }
        }

        return $orderedColumns;
    }

    /**
     * @param  array<int, TextColumn|TextInputColumn|IconColumn>  $columns
     * @param  array<int, string>  $excludedNames
     * @return array<int, TextColumn|TextInputColumn|IconColumn>
     */
    private static function remainingColumns(array $columns, array $excludedNames): array
    {
        return array_values(array_filter(
            $columns,
            fn (TextColumn|TextInputColumn|IconColumn $column): bool => ! in_array($column->getName(), $excludedNames, true),
        ));
    }
}
