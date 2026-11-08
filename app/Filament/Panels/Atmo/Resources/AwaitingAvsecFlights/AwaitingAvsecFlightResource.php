<?php

namespace App\Filament\Panels\Atmo\Resources\AwaitingAvsecFlights;

use App\Domain\FlightPlans\Services\FlightWorkflowService;
use App\Domain\FlightPlans\Support\FlightStatusDisplay;
use App\Filament\Panels\Atmo\Resources\AwaitingAvsecFlights\Pages\ListAwaitingAvsecFlights;
use App\Filament\Shared\Resources\Flights\FlightResource;
use App\Filament\Shared\Resources\Flights\Tables\FlightsTable;
use App\Models\Flight;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class AwaitingAvsecFlightResource extends FlightResource
{
    protected static ?string $slug = 'awaiting-avsec';

    protected static ?string $navigationLabel = 'Awaiting AVSEC';

    protected static ?string $pluralModelLabel = 'flights awaiting AVSEC';

    protected static ?int $navigationSort = 0;

    public static function canViewAny(): bool
    {
        return auth()->user()?->canReviewFlightPlans() ?? false;
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit($record): bool
    {
        return false;
    }

    public static function canDelete($record): bool
    {
        return false;
    }

    public static function getEloquentQuery(): Builder
    {
        return app(FlightWorkflowService::class)->queue(static::getFlightPlanBaseQuery(), FlightStatusDisplay::AWAITING_AVSEC);
    }

    public static function getNavigationBadge(): ?string
    {
        $count = static::getEloquentQuery()->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): string|array|null
    {
        return 'warning';
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'Signed filings awaiting AVSEC confirmation';
    }

    public static function table(Table $table): Table
    {
        return FlightsTable::configure($table, static::class, readOnly: true)->recordActions([ActionGroup::make([
            Action::make('preview')->label('View flight plan')->url(fn (Flight $record) => route('flights.view', $record))->openUrlInNewTab(),
            Action::make('qr')->label('QR')->url(fn (Flight $record) => route('flights.qr', $record))->openUrlInNewTab(),
            Action::make('manifest')->label('Manifest PDF')->url(fn (Flight $record) => route('flights.manifest.pdf', $record))->openUrlInNewTab(),
        ])->label('Actions')->icon('heroicon-o-squares-2x2')->button()])->toolbarActions([])->recordUrl(fn (Flight $record) => route('flights.view', $record));
    }

    public static function getPages(): array
    {
        return ['index' => ListAwaitingAvsecFlights::route('/')];
    }
}
