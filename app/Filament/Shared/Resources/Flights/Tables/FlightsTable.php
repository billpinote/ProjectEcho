<?php

namespace App\Filament\Shared\Resources\Flights\Tables;

use App\Domain\FlightPlans\Rules\UtcFourDigitTime;
use App\Domain\FlightPlans\Services\FlightPlanMutationService;
use App\Domain\FlightPlans\Support\FlightStatusDisplay;
use App\Filament\Panels\Dispatch\Resources\AwaitingAuthorizationFlights\AwaitingAuthorizationFlightResource as DispatchAwaitingAuthorizationFlightResource;
use App\Filament\Panels\Pilot\Resources\AwaitingAuthorizationFlights\AwaitingAuthorizationFlightResource;
use App\Filament\Panels\Pilot\Resources\MyArchivedFlights\MyArchivedFlightResource;
use App\Filament\Panels\Pilot\Resources\MyCompletedFlights\MyCompletedFlightResource;
use App\Filament\Panels\Pilot\Resources\MyCurrentFlights\MyCurrentFlightResource;
use App\Filament\Panels\Pilot\Resources\MyFlightPlans\MyFlightPlansResource;
use App\Filament\Shared\Resources\AcceptedFlights\AcceptedFlightResource;
use App\Filament\Shared\Resources\ActiveFlights\ActiveFlightResource;
use App\Filament\Shared\Resources\AirborneFlights\AirborneFlightResource;
use App\Filament\Shared\Resources\CompletedFlights\CompletedFlightResource;
use App\Filament\Shared\Resources\ExpiredFlights\ExpiredFlightResource;
use App\Filament\Shared\Resources\Flights\FlightResource;
use App\Filament\Shared\Resources\Flights\Schemas\FlightForm;
use App\Filament\Shared\Resources\LandedFlights\LandedFlightResource;
use App\Filament\Shared\Resources\RejectedFlights\RejectedFlightResource;
use App\Filament\Shared\Resources\Reports\AbbreviatedFlightReportResource;
use App\Filament\Shared\Resources\Reports\ActiveFlightDataResource;
use App\Filament\Shared\Resources\Reports\PostOpsLogResource;
use App\Models\Flight;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Support\Enums\FontFamily;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\TextInputColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Enums\FiltersResetActionPosition;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
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
            AbbreviatedFlightReportResource::class,
            ActiveFlightDataResource::class,
            PostOpsLogResource::class,
        ];

        $isOperationalFlightTable = self::matchesAnyResource($resourceClass, $operationalFlightResources);
        $canUpdateFlights = Auth::user()?->canUpdateFlightPlans() ?? false;
        $canUpdateStartUpTime = Auth::user()?->canUpdateFlightStartUpTime() ?? false;
        $canUpdateBlockOffTime = Auth::user()?->canUpdateFlightBlockOffTime() ?? false;
        $canUpdateShutdownTime = Auth::user()?->canUpdateFlightShutdownTime() ?? false;

        $filters = [
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
        ];

        if (self::matchesAnyResource($resourceClass, [AbbreviatedFlightReportResource::class, PostOpsLogResource::class])) {
            $reportDateOptions = fn (): array => $resourceClass::getEloquentQuery()
                ->whereNotNull('date_of_flight')
                ->orderByDesc('date_of_flight')
                ->pluck('date_of_flight')
                ->unique()
                ->mapWithKeys(fn (mixed $date): array => [
                    (string) $date => Carbon::parse((string) $date)->format('M d, Y'),
                ])
                ->all();

            $filters = [
                Filter::make('date_of_flight')
                    ->schema([
                        Select::make('value')
                            ->label('Date of Flight')
                            ->default(now('UTC')->toDateString())
                            ->options($reportDateOptions)
                            ->native(false)
                            ->selectablePlaceholder()
                            ->grow(false)
                            ->extraFieldWrapperAttributes([
                                'class' => 'echo-abbreviated-date-filter',
                            ]),
                    ])
                    ->query(fn (Builder $query, array $data): Builder => filled($data['value'] ?? null)
                        ? $query->whereDate('date_of_flight', $data['value'])
                        : $query),
            ];
        }

        $columns = [
            TextColumn::make('date_of_flight')
                ->label('DOF')
                ->date()
                ->searchable()
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
            TextColumn::make('time_airborne')
                ->label('ATD')
                ->state(fn (Flight $record): ?string => FlightForm::formatTimeForForm($record->time_airborne))
                ->fontFamily(FontFamily::Mono)
                ->alignCenter()
                ->extraHeaderAttributes(['class' => 'text-center'])
                ->width('10px')
                ->sortable()
                ->toggleable(isToggledHiddenByDefault: true),
            TextColumn::make('time_touchdown')
                ->label('TOUCHDOWN')
                ->state(fn (Flight $record): ?string => FlightForm::formatTimeForForm($record->time_touchdown))
                ->fontFamily(FontFamily::Mono)
                ->alignCenter()
                ->extraHeaderAttributes(['class' => 'text-center'])
                ->width('10px')
                ->sortable()
                ->toggleable(isToggledHiddenByDefault: true),
            TextColumn::make('time_shutdown')
                ->label('SHUTDOWN')
                ->state(fn (Flight $record): ?string => FlightForm::formatTimeForForm($record->time_shutdown))
                ->fontFamily(FontFamily::Mono)
                ->alignCenter()
                ->extraHeaderAttributes(['class' => 'text-center'])
                ->width('10px')
                ->sortable()
                ->toggleable(isToggledHiddenByDefault: true),
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

        if (in_array($resourceClass, [
            MyFlightPlansResource::class,
            MyCurrentFlightResource::class,
            MyCompletedFlightResource::class,
            MyArchivedFlightResource::class,
            AwaitingAuthorizationFlightResource::class,
            DispatchAwaitingAuthorizationFlightResource::class,
        ], true)) {
            array_unshift($columns, FlightStatusDisplay::tableColumn());
        }

        if ($isOperationalFlightTable) {
            $columns = array_values(array_filter(
                $columns,
                fn (TextColumn|IconColumn $column): bool => ! in_array($column->getName(), ['date_of_flight', 'accepted_by_wiresign'], true),
            ));
        }

        if (self::matchesResource($resourceClass, AcceptedFlightResource::class)) {
            $readyColumns = [
                TextInputColumn::make('time_start_up')
                    ->label('START-UP TIME')
                    ->getStateUsing(fn (Flight $record): ?string => FlightForm::formatTimeForForm($record->time_start_up))
                    ->updateStateUsing(function (Flight $record, mixed $state, LivewireComponent $livewire): ?string {
                        abort_unless(Auth::user()?->can('updateStartUpTime', $record) ?? false, 403);

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
                    ->disabled(fn (Flight $record): bool => ! (Auth::user()?->can('updateStartUpTime', $record) ?? false))
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
                    ->visible($canUpdateStartUpTime)
                    ->extraHeaderAttributes(['class' => 'echo-ready-start-header echo-ready-start-header-now'])
                    ->extraCellAttributes(['class' => 'echo-ready-start-cell echo-ready-start-cell-now'])
                    ->width('3px'),
                TextInputColumn::make('time_block_off')
                    ->label('OFF-BLOCK TIME')
                    ->getStateUsing(fn (Flight $record): ?string => FlightForm::formatTimeForForm($record->time_block_off))
                    ->updateStateUsing(function (Flight $record, mixed $state, LivewireComponent $livewire): ?string {
                        abort_unless(Auth::user()?->can('updateBlockOffTime', $record) ?? false, 403);

                        if (filled($state) && ! UtcFourDigitTime::isValid($state)) {
                            $livewire->dispatch(
                                'echo-modal:open',
                                heading: 'Invalid UTC Time',
                                message: UtcFourDigitTime::message('off-block time'),
                                tone: 'danger',
                                buttonLabel: 'Cancel',
                            );

                            return FlightForm::formatTimeForForm($record->time_block_off);
                        }

                        $normalizedState = UtcFourDigitTime::normalizeForStorage($state);

                        $record->forceFill([
                            'time_block_off' => $normalizedState,
                        ])->save();

                        return FlightForm::formatTimeForForm($normalizedState);
                    })
                    ->disabled(fn (Flight $record): bool => ! (Auth::user()?->can('updateBlockOffTime', $record) ?? false))
                    ->inputMode('numeric')
                    ->extraInputAttributes(fn (Flight $record): array => [
                        'maxlength' => 4,
                        'class' => 'echo-status-time-input',
                        'data-confirm-status-time' => 'true',
                        'data-time-label' => 'Off-Block Time',
                        'data-confirm-heading' => 'Confirm Off-Block Time',
                        'data-callsign' => (string) $record->aircraft_identification,
                    ])
                    ->alignCenter()
                    ->extraHeaderAttributes(['class' => 'text-center echo-ready-start-header echo-ready-start-header-main'])
                    ->extraCellAttributes(['class' => 'echo-ready-start-cell echo-ready-start-cell-main'])
                    ->width('12px'),
                TextColumn::make('time_block_off_now')
                    ->label(' ')
                    ->state('OFF-BLOCK')
                    ->badge()
                    ->color('warning')
                    ->alignCenter()
                    ->extraAttributes(fn (Flight $record): array => [
                        'class' => 'echo-status-time-now-trigger',
                        'role' => 'button',
                        'tabindex' => 0,
                        'data-record-id' => (string) $record->getKey(),
                        'data-confirm-method' => 'confirmBlockOffNow',
                        'data-time-label' => 'Off-Block Time',
                        'data-confirm-heading' => 'Confirm Off-Block Time',
                        'data-callsign' => (string) $record->aircraft_identification,
                    ])
                    ->visible($canUpdateBlockOffTime)
                    ->extraHeaderAttributes(['class' => 'echo-ready-start-header echo-ready-start-header-now'])
                    ->extraCellAttributes(['class' => 'echo-ready-start-cell echo-ready-start-cell-now'])
                    ->width('5px'),
                ...self::relabelColumns(
                    self::pickColumns($columns, [
                        'aircraft_identification',
                        'proposed_time',
                        'route',
                        'destination_aerodrome',
                    ]),
                    [
                        'route' => 'Route',
                        'destination_aerodrome' => 'Destination',
                    ],
                ),
            ];

            $columns = $readyColumns;
        }

        if (self::matchesResource($resourceClass, ActiveFlightDataResource::class)) {
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

        if (self::matchesResource($resourceClass, AbbreviatedFlightReportResource::class)) {
            $columns = [
                TextColumn::make('aircraft_identification')
                    ->label('Callsign')
                    ->fontFamily(FontFamily::Mono)
                    ->sortable()
                    ->extraHeaderAttributes(['class' => 'text-center'])
                    ->width('6px')
                    ->weight('bold'),
                TextColumn::make('type_of_aircraft')
                    ->label('Type')
                    ->sortable()
                    ->extraHeaderAttributes(['class' => 'text-center'])
                    ->width('4px'),
                TextColumn::make('departure_aerodrome')
                    ->label('Dep')
                    ->sortable()
                    ->extraHeaderAttributes(['class' => 'text-center'])
                    ->width('4px')
                    ->tooltip(fn (Flight $record): ?string => strtoupper((string) $record->departure_aerodrome) === 'ZZZZ'
                        ? (filled($record->other_info_dep) ? (string) $record->other_info_dep : 'Departure aerodrome details not provided.')
                        : null),
                TextColumn::make('destination_aerodrome')
                    ->label('Dest')
                    ->sortable()
                    ->extraHeaderAttributes(['class' => 'text-center'])
                    ->width('4px')
                    ->tooltip(fn (Flight $record): ?string => strtoupper((string) $record->destination_aerodrome) === 'ZZZZ'
                        ? (filled($record->other_info_dest) ? (string) $record->other_info_dest : 'Destination aerodrome details not provided.')
                        : null),
                TextColumn::make('proposed_time')
                    ->label('PTD')
                    ->state(fn (Flight $record): ?string => FlightForm::formatTimeForForm($record->proposed_time))
                    ->fontFamily(FontFamily::Mono)
                    ->extraHeaderAttributes(['class' => 'text-center'])
                    ->width('4px')
                    ->sortable(),
                TextColumn::make('time_airborne')
                    ->label('ATD')
                    ->state(fn (Flight $record): ?string => FlightForm::formatTimeForForm($record->time_airborne))
                    ->fontFamily(FontFamily::Mono)
                    ->extraHeaderAttributes(['class' => 'text-center'])
                    ->width('4px')
                    ->sortable(),
                TextColumn::make('route')
                    ->label('Route')
                    ->fontFamily(FontFamily::Mono)
                    ->extraHeaderAttributes(['class' => 'text-center'])
                    ->limit(30)
                    ->width('10px')
                    ->tooltip(fn (Flight $record): ?string => filled($record->route) ? $record->route : null),
                TextColumn::make('total_eet')
                    ->label('EET')
                    ->state(fn (Flight $record): ?string => FlightForm::formatTimeForForm($record->total_eet))
                    ->fontFamily(FontFamily::Mono)
                    ->extraHeaderAttributes(['class' => 'text-center'])
                    ->width('4px')
                    ->sortable(),
                TextColumn::make('fob')
                    ->label('FOB')
                    ->state(fn (Flight $record): ?string => FlightForm::formatTimeForForm($record->endurance))
                    ->fontFamily(FontFamily::Mono)
                    ->extraHeaderAttributes(['class' => 'text-center'])
                    ->width('4px')
                    ->sortable(),
                TextColumn::make('persons_on_board')
                    ->label('POB')
                    ->extraHeaderAttributes(['class' => 'text-center'])
                    ->width('4px')
                    ->sortable(),
                TextColumn::make('pilot_in_command')
                    ->label('Pilot')
                    ->extraHeaderAttributes(['class' => 'text-center'])
                    ->width('10px'),
                TextColumn::make('report_remarks')
                    ->label('REMARKS')
                    ->state(fn (): string => '')
                    ->extraHeaderAttributes(['class' => 'text-center'])
                    ->width('10px'),
            ];
        }

        if (self::matchesResource($resourceClass, PostOpsLogResource::class)) {
            $columns = [
                TextColumn::make('tg')
                    ->label('T/G')
                    ->state(fn (): string => '')
                    ->alignCenter()
                    ->extraHeaderAttributes(['class' => 'text-center'])
                    ->width('4px'),
                TextColumn::make('aircraft_identification')
                    ->label('Callsign')
                    ->fontFamily(FontFamily::Mono)
                    ->sortable()
                    ->alignCenter()
                    ->extraHeaderAttributes(['class' => 'text-center'])
                    ->width('8px')
                    ->weight('bold'),
                TextColumn::make('time_airborne')
                    ->label('Take-off')
                    ->state(fn (Flight $record): ?string => FlightForm::formatTimeForForm($record->time_airborne))
                    ->fontFamily(FontFamily::Mono)
                    ->alignCenter()
                    ->extraHeaderAttributes(['class' => 'text-center'])
                    ->width('6px')
                    ->sortable(),
                TextColumn::make('time_touchdown')
                    ->label('Landing')
                    ->state(fn (Flight $record): ?string => FlightForm::formatTimeForForm($record->time_touchdown))
                    ->fontFamily(FontFamily::Mono)
                    ->alignCenter()
                    ->extraHeaderAttributes(['class' => 'text-center'])
                    ->width('6px')
                    ->sortable(),
                TextColumn::make('overfly')
                    ->label('Overfly')
                    ->state(fn (): string => '')
                    ->alignCenter()
                    ->extraHeaderAttributes(['class' => 'text-center'])
                    ->width('6px'),
                TextColumn::make('departure_aerodrome')
                    ->label('Origin')
                    ->sortable()
                    ->alignCenter()
                    ->extraHeaderAttributes(['class' => 'text-center'])
                    ->width('6px')
                    ->tooltip(fn (Flight $record): ?string => strtoupper((string) $record->departure_aerodrome) === 'ZZZZ'
                        ? (filled($record->other_info_dep) ? (string) $record->other_info_dep : 'Departure aerodrome details not provided.')
                        : null),
                TextColumn::make('destination_aerodrome')
                    ->label('Destination')
                    ->sortable()
                    ->alignCenter()
                    ->extraHeaderAttributes(['class' => 'text-center'])
                    ->width('8px')
                    ->tooltip(fn (Flight $record): ?string => strtoupper((string) $record->destination_aerodrome) === 'ZZZZ'
                        ? (filled($record->other_info_dest) ? (string) $record->other_info_dest : 'Destination aerodrome details not provided.')
                        : null),
                TextColumn::make('type_of_aircraft')
                    ->label('Aircraft Type')
                    ->sortable()
                    ->alignCenter()
                    ->extraHeaderAttributes(['class' => 'text-center'])
                    ->width('8px'),
                TextColumn::make('nature')
                    ->label('Nature')
                    ->state(fn (): string => '')
                    ->alignCenter()
                    ->extraHeaderAttributes(['class' => 'text-center'])
                    ->width('6px'),
                TextColumn::make('operator')
                    ->label('Operator')
                    ->state(fn (): string => '')
                    ->alignCenter()
                    ->extraHeaderAttributes(['class' => 'text-center'])
                    ->width('8px'),
            ];
        }

        if (self::matchesResource($resourceClass, ActiveFlightResource::class)) {
            $activeColumns = [
                TextInputColumn::make('time_airborne')
                    ->label('TAKE OFF TIME')
                    ->getStateUsing(fn (Flight $record): ?string => FlightForm::formatTimeForForm($record->time_airborne))
                    ->updateStateUsing(function (Flight $record, mixed $state, LivewireComponent $livewire): ?string {
                        abort_unless(Auth::user()?->can('updateAirborneTime', $record) ?? false, 403);

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
                    ->disabled(fn (Flight $record): bool => ! (Auth::user()?->can('updateAirborneTime', $record) ?? false))
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
                ...self::pickColumns($columns, [
                    'aircraft_identification',
                    'proposed_time',
                ]),
                TextColumn::make('time_start_up')
                    ->label('START-UP TIME')
                    ->state(fn (Flight $record): ?string => FlightForm::formatTimeForForm($record->time_start_up))
                    ->placeholder('-')
                    ->fontFamily(FontFamily::Mono)
                    ->alignCenter()
                    ->searchable()
                    ->sortable()
                    ->extraHeaderAttributes(['class' => 'text-center'])
                    ->width('10px'),
                ...self::relabelColumns(
                    self::pickColumns($columns, [
                        'route',
                        'destination_aerodrome',
                    ]),
                    [
                        'route' => 'Route',
                        'destination_aerodrome' => 'Destination',
                    ],
                ),
            ];

            $columns = $activeColumns;
        }

        if (self::matchesResource($resourceClass, AirborneFlightResource::class)) {
            $airborneColumns = [
                TextInputColumn::make('time_touchdown')
                    ->label('TOUCHDOWN TIME')
                    ->getStateUsing(fn (Flight $record): ?string => FlightForm::formatTimeForForm($record->time_touchdown))
                    ->updateStateUsing(function (Flight $record, mixed $state, LivewireComponent $livewire): ?string {
                        abort_unless(Auth::user()?->can('updateTouchdownTime', $record) ?? false, 403);

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
                    ->disabled(fn (Flight $record): bool => ! (Auth::user()?->can('updateTouchdownTime', $record) ?? false))
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
                ...self::relabelColumns(
                    self::pickColumns($columns, [
                        'aircraft_identification',
                        'time_airborne',
                        'route',
                        'destination_aerodrome',
                    ]),
                    [
                        'route' => 'Route',
                        'destination_aerodrome' => 'Destination',
                    ],
                ),
            ];

            $columns = $airborneColumns;
        }

        if (self::matchesResource($resourceClass, LandedFlightResource::class)) {
            $landedColumns = [
                TextInputColumn::make('time_shutdown')
                    ->label('SHUTDOWN TIME')
                    ->getStateUsing(fn (Flight $record): ?string => FlightForm::formatTimeForForm($record->time_shutdown))
                    ->updateStateUsing(function (Flight $record, mixed $state, LivewireComponent $livewire): ?string {
                        abort_unless(Auth::user()?->can('updateShutdownTime', $record) ?? false, 403);

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
                    ->disabled(fn (Flight $record): bool => ! (Auth::user()?->can('updateShutdownTime', $record) ?? false))
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
                    ->visible($canUpdateShutdownTime)
                    ->extraHeaderAttributes(['class' => 'echo-ready-start-header echo-ready-start-header-now'])
                    ->extraCellAttributes(['class' => 'echo-ready-start-cell echo-ready-start-cell-now'])
                    ->width('6px'),
                ...self::relabelColumns(
                    self::pickColumns($columns, [
                        'aircraft_identification',
                        'time_touchdown',
                        'route',
                    ]),
                    [
                        'route' => 'Route',
                    ],
                ),
            ];

            $columns = $landedColumns;
        }

        if (self::matchesResource($resourceClass, CompletedFlightResource::class)) {
            $completedColumns = [
                ...self::pickColumns($columns, [
                    'aircraft_identification',
                    'proposed_time',
                ]),
                TextColumn::make('time_start_up')
                    ->label('START-UP TIME')
                    ->state(fn (Flight $record): ?string => FlightForm::formatTimeForForm($record->time_start_up))
                    ->placeholder('-')
                    ->fontFamily(FontFamily::Mono)
                    ->alignCenter()
                    ->searchable()
                    ->sortable()
                    ->extraHeaderAttributes(['class' => 'text-center'])
                    ->width('10px'),
                ...self::relabelColumns(
                    self::pickColumns($columns, [
                        'time_airborne',
                        'time_touchdown',
                        'time_shutdown',
                        'route',
                    ]),
                    [
                        'route' => 'Route',
                    ],
                ),
            ];

            $columns = $completedColumns;
        }

        if (self::matchesResource($resourceClass, RejectedFlightResource::class)) {
            $columns[] = TextColumn::make('rejected_by_wiresign')
                ->label('ATMO')
                ->alignCenter()
                ->extraHeaderAttributes(['class' => 'text-center'])
                ->width('10px')
                ->searchable();
        }

        if (self::matchesResource($resourceClass, ExpiredFlightResource::class)) {
            $columns[] = TextColumn::make('expiration_reason')
                ->label('Expired Reason')
                ->state(fn (Flight $record): ?string => $record->expiration_reason)
                ->wrap();
        }

        return $table
            ->when(
                $isOperationalFlightTable || self::matchesResource($resourceClass, FlightResource::class),
                fn (Table $table): Table => $table->poll('5s')
            )
            ->when(
                filled($resourceClass) && ! self::matchesResource($resourceClass, ActiveFlightDataResource::class),
                fn (Table $table): Table => $table
                    ->recordUrl(fn (Flight $record): string => route('flights.view', $record))
                    ->openRecordUrlInNewTab()
            )
            ->modifyQueryUsing(fn (Builder $query): Builder => self::applyDefaultOrdering($query, $resourceClass, $isOperationalFlightTable))
            ->recordClasses(fn (Flight $record): array => self::matchesResource($resourceClass, FlightResource::class) && $record->reviewed_at === null
                ? ['echo-new-flight-row']
                : [])
            ->columns($columns)
            ->filters(
                $filters,
                layout: self::matchesAnyResource($resourceClass, [AbbreviatedFlightReportResource::class, PostOpsLogResource::class])
                    ? FiltersLayout::Hidden
                    : null,
            )
            ->when(
                self::matchesAnyResource($resourceClass, [AbbreviatedFlightReportResource::class, PostOpsLogResource::class]),
                fn (Table $table): Table => $table
                    ->header(view('filament.tables.headers.abbreviated-flight-report-header', [
                        'dateOptions' => $reportDateOptions(),
                        'reportUrl' => self::matchesResource($resourceClass, AbbreviatedFlightReportResource::class)
                            ? route('reports.abbreviated.pdf')
                            : route('reports.post-ops-log.pdf'),
                        'showTestAction' => (static function (): bool {
                            $user = Auth::user();

                            return $user !== null
                                && (bool) $user->is_active
                                && $user->canReviewFlightPlans();
                        })(),
                    ]))
                    ->deferFilters(false)
                    ->filtersResetActionPosition(FiltersResetActionPosition::Footer)
                    ->hiddenFilterIndicators()
            )
            ->recordActions($isOperationalFlightTable ? [] : self::recordActions($resourceClass));
    }

    /**
     * @param  array<class-string>  $resources
     */
    private static function matchesAnyResource(?string $resourceClass, array $resources): bool
    {
        if ($resourceClass === null) {
            return false;
        }

        foreach ($resources as $resource) {
            if (is_a($resourceClass, $resource, true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  class-string  $resource
     */
    private static function matchesResource(?string $resourceClass, string $resource): bool
    {
        return $resourceClass !== null && is_a($resourceClass, $resource, true);
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
     * @param  array<string, string>  $labels
     * @return array<int, TextColumn|TextInputColumn|IconColumn>
     */
    private static function relabelColumns(array $columns, array $labels): array
    {
        foreach ($columns as $column) {
            $label = $labels[$column->getName()] ?? null;

            if ($label !== null) {
                $column->label($label);
            }
        }

        return $columns;
    }

    private static function applyDefaultOrdering(Builder $query, ?string $resourceClass, bool $isOperationalFlightTable): Builder
    {
        if ($resourceClass === MyCurrentFlightResource::class) {
            return $query
                ->orderByRaw('case when date_of_flight is null then 1 else 0 end')
                ->orderBy('date_of_flight')
                ->orderByRaw('case when proposed_time is null then 1 else 0 end')
                ->orderBy('proposed_time')
                ->orderByDesc('updated_at')
                ->orderByDesc('id');
        }

        if (in_array($resourceClass, [MyCompletedFlightResource::class, MyArchivedFlightResource::class, AwaitingAuthorizationFlightResource::class, DispatchAwaitingAuthorizationFlightResource::class], true)) {
            return $query
                ->orderByDesc('date_of_flight')
                ->orderByDesc('updated_at')
                ->orderByDesc('id');
        }

        return $isOperationalFlightTable || self::matchesResource($resourceClass, FlightResource::class)
            ? $query
                ->orderByRaw('case when date_of_flight is null then 1 else 0 end')
                ->orderBy('date_of_flight')
                ->orderByRaw('case when proposed_time is null then 1 else 0 end')
                ->orderBy('proposed_time')
                ->orderBy('id')
            : $query
                ->orderByDesc('created_at')
                ->orderByDesc('id');
    }

    /**
     * @return array<int, Action|EditAction>
     */
    private static function recordActions(?string $resourceClass): array
    {
        $actions = [
            Action::make('qr')
                ->label('QR')
                ->icon('heroicon-o-qr-code')
                ->iconButton()
                ->tooltip('View QR code')
                ->extraAttributes(['class' => 'echo-flight-row-action echo-flight-row-action-qr'])
                ->url(fn (Flight $record): string => route('flights.qr', $record))
                ->openUrlInNewTab(),
            Action::make('view')
                ->label('View')
                ->icon('heroicon-o-eye')
                ->iconButton()
                ->tooltip('View flight plan')
                ->extraAttributes(['class' => 'echo-flight-row-action echo-flight-row-action-view'])
                ->url(fn (Flight $record): string => route('flights.view', $record))
                ->openUrlInNewTab(),
            Action::make('pdf')
                ->label('PDF')
                ->icon('heroicon-o-document-text')
                ->iconButton()
                ->tooltip('Download PDF')
                ->extraAttributes(['class' => 'echo-flight-row-action echo-flight-row-action-pdf'])
                ->url(fn (Flight $record): string => route('flights.pdf.download', $record))
                ->openUrlInNewTab(),
        ];

        if (in_array($resourceClass, [MyFlightPlansResource::class, MyCurrentFlightResource::class], true)) {
            $actions[] = self::delayAction();
            $actions[] = self::cancelAction();

            return $actions;
        }

        if ($resourceClass === AwaitingAuthorizationFlightResource::class) {
            $actions[] = ActionGroup::make([
                Action::make('correctResubmit')
                    ->label('Correct & Resubmit')
                    ->icon('heroicon-o-pencil-square')
                    ->url(fn (Flight $record): string => \App\Filament\Panels\Pilot\Resources\Flights\FlightResource::getUrl('create', ['correct_from' => $record->getKey()], panel: 'pilot'))
                    ->visible(fn (Flight $record): bool => $record->pic_authorization_status === 'declined'
                        && (int) $record->prepared_by_user_id === (int) Auth::id()),
                Action::make('archive')
                    ->label('Archive')
                    ->icon('heroicon-o-archive-box')
                    ->color('gray')
                    ->requiresConfirmation()
                    ->action(function (Flight $record): void {
                        abort_unless($record->pic_authorization_status === 'declined'
                            && (int) $record->prepared_by_user_id === (int) Auth::id(), 403);
                        $record->archivePicDecline();
                    })
                    ->visible(fn (Flight $record): bool => $record->pic_authorization_status === 'declined'
                        && (int) $record->prepared_by_user_id === (int) Auth::id()),
            ])->label('More')->icon('heroicon-m-ellipsis-vertical')->iconButton()->tooltip('Workflow actions')
                ->extraAttributes(['class' => 'echo-flight-row-action echo-flight-row-action-more']);

            return $actions;
        }

        if (in_array($resourceClass, [MyCompletedFlightResource::class, MyArchivedFlightResource::class, DispatchAwaitingAuthorizationFlightResource::class], true)) {

            return $actions;
        }

        $actions[] = EditAction::make()->label('Edit');

        return $actions;
    }

    private static function delayAction(): Action
    {
        return Action::make('delay')
            ->label('Delay')
            ->icon('heroicon-o-clock')
            ->fillForm(fn (Flight $record): array => [
                'new_proposed_time' => UtcFourDigitTime::formatForDisplay($record->proposed_time) ?? '',
            ])
            ->form([
                TextInput::make('new_proposed_time')
                    ->label('New Proposed Time (UTC)')
                    ->required()
                    ->rule(new UtcFourDigitTime)
                    ->maxLength(5)
                    ->placeholder('HHMM')
                    ->helperText('Enter a new UTC time in four-digit format.'),
            ])
            ->modalHeading('Delay Flight Plan')
            ->modalDescription(function (Flight $record, array $data): string {
                $current = UtcFourDigitTime::formatForDisplay($record->proposed_time) ?? 'N/A';
                $next = UtcFourDigitTime::formatForDisplay($data['new_proposed_time'] ?? null) ?? ($data['new_proposed_time'] ?? 'N/A');

                return "Current proposed time: {$current}. New proposed time: {$next}.";
            })
            ->requiresConfirmation()
            ->visible(fn (Flight $record): bool => Auth::user()?->can('view', $record) ?? false)
            ->disabled(fn (Flight $record): bool => ! (Auth::user()?->can('delay', $record) ?? false))
            ->tooltip(fn (Flight $record): ?string => $record->canBeDelayedByPilot()
                ? null
                : 'Delay is unavailable once processing has started or the flight is no longer eligible.')
            ->action(function (Flight $record, array $data): void {
                app(FlightPlanMutationService::class)->delay(
                    $record,
                    Auth::user(),
                    (string) $data['new_proposed_time']
                );

                Notification::make()
                    ->success()
                    ->title('Proposed time updated')
                    ->send();
            });
    }

    private static function cancelAction(): Action
    {
        return Action::make('cancel')
            ->label('Cancel')
            ->icon('heroicon-o-no-symbol')
            ->color('danger')
            ->form([
                Textarea::make('reason')
                    ->label('Cancellation Reason')
                    ->rows(3)
                    ->maxLength(255)
                    ->placeholder('Optional short reason'),
            ])
            ->modalHeading('Cancel Flight Plan')
            ->modalDescription('This keeps the record and marks the flight plan as cancelled.')
            ->requiresConfirmation()
            ->visible(fn (Flight $record): bool => Auth::user()?->can('view', $record) ?? false)
            ->disabled(fn (Flight $record): bool => ! (Auth::user()?->can('cancel', $record) ?? false))
            ->tooltip(fn (Flight $record): ?string => $record->canBeCancelledByPilot()
                ? null
                : 'Cancellation is unavailable once processing has started or the flight has already been closed.')
            ->action(function (Flight $record, array $data): void {
                app(FlightPlanMutationService::class)->cancel(
                    $record,
                    Auth::user(),
                    $data['reason'] ?? null
                );

                Notification::make()
                    ->success()
                    ->title('Flight plan cancelled')
                    ->send();
            });
    }
}
