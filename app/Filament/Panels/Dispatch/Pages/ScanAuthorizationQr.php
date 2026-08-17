<?php

namespace App\Filament\Panels\Dispatch\Pages;

use App\Domain\Users\Enums\UserRole;
use App\Filament\Panels\Pilot\Pages\ScanAuthorizationQr as PilotScanAuthorizationQr;

class ScanAuthorizationQr extends PilotScanAuthorizationQr
{
    protected static string|\UnitEnum|null $navigationGroup = 'PIC Authorization';

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user !== null && $user->is_active
            && in_array($user->role, [UserRole::Dispatch, UserRole::OperatorStaff, UserRole::Artisan], true);
    }
}
