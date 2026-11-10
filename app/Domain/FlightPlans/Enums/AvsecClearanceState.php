<?php

namespace App\Domain\FlightPlans\Enums;

use App\Domain\Users\Enums\UserRole;

enum AvsecClearanceState: string
{
    case Awaiting = 'awaiting_avsec';
    case Granted = 'cleared';
    case Denied = 'clearance_denied';

    public function label(?UserRole $viewerRole = null): string
    {
        if (in_array($viewerRole, [UserRole::Pilot, UserRole::Atmo], true)) {
            return match ($this) {
                self::Awaiting => 'Pending AVSEC',
                self::Granted => 'AVSEC Approved',
                self::Denied => 'AVSEC Denied',
            };
        }

        return match ($this) {
            self::Awaiting => $viewerRole === UserRole::Avsec ? 'Pending Clearance' : 'Awaiting AVSEC',
            self::Granted => 'Clearance Granted',
            self::Denied => 'Clearance Denied',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Awaiting => 'warning',
            self::Granted => 'success',
            self::Denied => 'danger',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Awaiting => 'heroicon-o-shield-check',
            self::Granted => 'heroicon-o-check-circle',
            self::Denied => 'heroicon-o-x-circle',
        };
    }
}
