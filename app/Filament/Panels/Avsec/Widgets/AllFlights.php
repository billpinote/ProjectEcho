<?php

namespace App\Filament\Panels\Avsec\Widgets;

use App\Domain\FlightPlans\Rules\UtcFourDigitTime;
use App\Domain\FlightPlans\Services\AvsecOperationalFlightQuery;
use App\Domain\FlightPlans\Services\AvsecVerificationService;
use App\Domain\FlightPlans\Support\FlightStatusDisplay;
use App\Domain\Users\Enums\UserRole;
use App\Models\Flight;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

class AllFlights extends TableWidget
{
    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        return AvsecVerificationService::eligibleOfficer(auth()->user());
    }

    public function table(Table $table): Table
    {
        return $table->heading('All Flights')
            ->description('UTC (Z) · Planned departures from the previous 24 hours through the next 24 hours.')
            ->query(fn () => app(AvsecOperationalFlightQuery::class)->forOfficer(auth()->user()))
            ->defaultSort(fn (Builder $query) => $query->orderBy('date_of_flight')->orderBy('proposed_time')->orderBy('id'))
            ->columns([
                TextColumn::make('aircraft_identification')->label('CALLSIGN')->searchable()->sortable(),
                TextColumn::make('date_of_flight')->label('DATE')->date('Y-m-d', timezone: 'UTC')->sortable(),
                TextColumn::make('proposed_time')->label('PTD')->formatStateUsing(fn ($state) => UtcFourDigitTime::formatForDisplay($state).'Z')
                    ->sortable(query: fn (Builder $query, string $direction) => $query->orderBy('date_of_flight', $direction)->orderBy('proposed_time', $direction)),
                TextColumn::make('pilot_in_command')->label('Pilot'),
                FlightStatusDisplay::tableColumn(UserRole::Avsec, 'avsec_status')->label('AVSEC STATUS')
                    ->state(fn (Flight $record) => FlightStatusDisplay::avsecBadge($record)['label'])
                    ->icon(fn (Flight $record) => FlightStatusDisplay::avsecBadge($record)['icon'])
                    ->tooltip(fn (Flight $record) => FlightStatusDisplay::avsecBadge($record)['tooltip'])
                    ->extraCellAttributes(fn (Flight $record) => [
                        'class' => 'echo-flight-status-cell',
                        'data-flight-status' => FlightStatusDisplay::avsecBadge($record)['key'],
                        'data-flight-status-outlined' => FlightStatusDisplay::avsecBadge($record)['outlined'] ? 'true' : 'false',
                    ])
                    ->color(fn (Flight $record) => FlightStatusDisplay::avsecBadge($record)['color']),
            ])->recordActions([])->recordAction(null)
            ->recordUrl(fn (Flight $record) => route('flights.view', $record))->openRecordUrlInNewTab()
            ->recordClasses('echo-avsec-linked-row')
            ->extraRecordLinkAttributes(['class' => 'echo-avsec-record-link', 'title' => 'Opens in a new tab'])
            ->paginated([10, 25, 50])->defaultPaginationPageOption(10);
    }
}
