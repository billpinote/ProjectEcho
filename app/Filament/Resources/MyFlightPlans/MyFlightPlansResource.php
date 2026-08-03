<?php

namespace App\Filament\Resources\MyFlightPlans;

use App\Filament\Resources\AllFlightPlans\AllFlightResource;
use App\Filament\Resources\MyFlightPlans\Pages\EditMyFlightPlan;
use App\Filament\Resources\MyFlightPlans\Pages\ListMyFlightPlans;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Filament\Support\Icons\Heroicon;

class MyFlightPlansResource extends AllFlightResource
{
    protected static ?string $slug = 'my-flight-plans';

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedPaperAirplane;

    protected static ?string $navigationLabel = 'My Flight Plans';

    protected static ?string $modelLabel = 'my flight plan';

    protected static ?string $pluralModelLabel = 'my flight plans';

    protected static ?int $navigationSort = 20;

    public static function getEloquentQuery(): Builder
    {
        $userId = Auth::id();

        return static::getFlightPlanBaseQuery()
            ->where('user_id', $userId)
            ->whereNot(static fn (Builder $query): Builder => $query->pendingExpired());
    }

    public static function getPages(): array
    {
        return [
            'index' => ListMyFlightPlans::route('/'),
            'edit' => EditMyFlightPlan::route('/{record}/edit'),
        ];
    }
}
