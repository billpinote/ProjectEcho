<?php

namespace App\Filament\Panels\Avsec\Resources\FiledRpusDepartures;

use App\Domain\FlightPlans\Rules\UtcFourDigitTime;
use App\Domain\FlightPlans\Services\AvsecDepartureQuery;
use App\Domain\FlightPlans\Services\AvsecVerificationService;
use App\Filament\Panels\Avsec\Pages\ImportScanQr;
use App\Filament\Panels\Avsec\Resources\FiledRpusDepartures\Pages\ListFiledRpusDepartures;
use App\Models\Flight;
use Carbon\CarbonImmutable;
use Filament\Actions\Action;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class FiledRpusDepartureResource extends Resource
{
    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedPaperAirplane;

    protected static ?string $model = Flight::class;

    protected static ?string $navigationLabel = 'Filed RPUS Departures';

    protected static ?string $pluralModelLabel = 'Filed RPUS Departures';

    protected static ?int $navigationSort = 1;

    public static function getEloquentQuery(): Builder
    {
        return app(AvsecDepartureQuery::class)->forOfficer(auth()->user());
    }

    public static function canViewAny(): bool
    {
        return AvsecVerificationService::eligibleOfficer(auth()->user());
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return ['index' => ListFiledRpusDepartures::route('/')];
    }

    public static function departureTime(Flight $flight): ?string
    {
        $time = UtcFourDigitTime::normalizeDatabaseTime($flight->currentEobt());
        if (blank($flight->date_of_flight) || blank($time)) {
            return null;
        }

        return CarbonImmutable::parse($flight->date_of_flight.' '.$time, 'UTC')
            ->setTimezone('Asia/Manila')->format('Y-m-d H:i');
    }

    public static function table(Table $table): Table
    {
        return $table->heading('Filed RPUS Departures')
            ->description('All dates and PTD are UTC (Z). Eligible departures awaiting AVSEC clearance.')
            ->defaultSort(fn (Builder $query) => $query->orderBy('date_of_flight')->orderBy('proposed_time')->orderBy('id'))
            ->columns([
                TextColumn::make('aircraft_identification')->label('CALLSIGN')->searchable()->sortable()->weight('bold'),
                TextColumn::make('date_of_flight')->label('DATE')->date('Y-m-d', timezone: 'UTC')->sortable(),
                TextColumn::make('proposed_time')->label('PTD')->state(fn (Flight $record) => filled($record->proposed_time) ? UtcFourDigitTime::formatForDisplay($record->proposed_time).'Z' : null)
                    ->sortable(query: fn (Builder $query, string $direction) => $query->orderBy('date_of_flight', $direction)->orderBy('proposed_time', $direction)),
                TextColumn::make('pilot_in_command')->label('PIC'),
                TextColumn::make('avsec_status')->label('AVSEC STATUS')->badge()->state('Awaiting AVSEC')->color('warning'),
            ])->filters([
                SelectFilter::make('destination_aerodrome')->label('Destination')->options(fn () => static::getEloquentQuery()->distinct()->pluck('destination_aerodrome', 'destination_aerodrome')->all()),
            ])->recordUrl(null)->recordActions([
                Action::make('review')->label('Review')->url(fn (Flight $record) => ImportScanQr::getUrl(['flight' => $record->id], panel: 'avsec')),
            ])
            ->paginated([10, 25, 50])->defaultPaginationPageOption(10);
    }
}
