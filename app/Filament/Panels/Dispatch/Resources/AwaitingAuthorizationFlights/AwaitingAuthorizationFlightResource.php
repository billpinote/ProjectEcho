<?php

namespace App\Filament\Panels\Dispatch\Resources\AwaitingAuthorizationFlights;

use App\Domain\FlightPlans\Enums\FlightPlanStatus;
use App\Filament\Panels\Dispatch\Resources\AwaitingAuthorizationFlights\Pages\ListAwaitingAuthorizationFlights;
use App\Filament\Shared\Resources\Flights\FlightResource;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;

class AwaitingAuthorizationFlightResource extends FlightResource
{
    protected static ?string $slug = 'awaiting-authorization-flights';

    protected static string|\UnitEnum|null $navigationGroup = 'PIC Authorization';

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedShieldExclamation;

    protected static ?string $navigationLabel = 'Awaiting Authorization';

    protected static ?string $modelLabel = 'flight plan awaiting authorization';

    protected static ?string $pluralModelLabel = 'flight plans awaiting authorization';

    protected static ?int $navigationSort = 7;

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
        return static::getFlightPlanBaseQuery()->awaitingPicAuthorization();
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAwaitingAuthorizationFlights::route('/'),
        ];
    }

    public static function getNavigationBadge(): ?string
    {
        $count = static::getEloquentQuery()->whereIn('status', [FlightPlanStatus::AwaitingPic, FlightPlanStatus::Pending])
            ->where(fn (Builder $query) => $query->whereNull('pic_authorization_status')
                ->orWhere('pic_authorization_status', '!=', 'declined'))->count();

        return $count > 0 ? "New {$count}" : null;
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'Outstanding PIC filing authorizations';
    }
}
