<?php

namespace App\Filament\Resources\MyFlightPlans;

use App\Enums\UserRole;
use App\Filament\Resources\AllFlightPlans\AllFlightResource;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

abstract class MyFlightPlanResource extends AllFlightResource
{
    protected static string|\UnitEnum|null $navigationGroup = 'My Flight Plans';

    protected static ?string $navigationParentItem = null;

    public static function canAccess(): bool
    {
        $user = Auth::user();

        return $user !== null
            && (bool) $user->is_active
            && ($user->isPilot() || $user->role === UserRole::Artisan);
    }

    public static function canViewAny(): bool
    {
        return static::canAccess();
    }

    public static function canView($record): bool
    {
        return Auth::user()?->can('view', $record) ?? false;
    }

    public static function canEdit($record): bool
    {
        return false;
    }

    public static function canDelete($record): bool
    {
        return false;
    }

    protected static function getOwnedFlightQuery(): Builder
    {
        return static::getFlightPlanBaseQuery()
            ->where('filed_by_user_id', Auth::id());
    }

    protected static function getPilotStatusTableName(): string
    {
        return static::class;
    }
}
