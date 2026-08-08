<?php

namespace App\Filament\Panels\Pilot\Resources\MyFlightPlans;

use App\Filament\Shared\Resources\AllFlightPlans\AllFlightResource;
use App\Filament\Panels\Pilot\Resources\MyFlightPlans\Pages\ListMyFlightPlans;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class MyFlightPlansResource extends AllFlightResource
{
    protected static ?string $slug = 'my-flight-plans';

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedPaperAirplane;

    protected static ?string $navigationLabel = 'My Flight Plans';

    protected static ?string $modelLabel = 'my flight plan';

    protected static ?string $pluralModelLabel = 'my flight plans';

    protected static ?int $navigationSort = 20;

    public static function canAccess(): bool
    {
        $user = Auth::user();

        return $user?->canViewFlightPlans() ?? false;
    }

    public static function canViewAny(): bool
    {
        return static::canAccess();
    }

    public static function getEloquentQuery(): Builder
    {
        $userId = Auth::id();

        return static::getFlightPlanBaseQuery()
            ->where('filed_by_user_id', $userId);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListMyFlightPlans::route('/'),
        ];
    }
}
