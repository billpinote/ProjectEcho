<?php

namespace App\Enums;

use Illuminate\Support\Str;

enum FlightPlanStatus: string
{
    case Pending = 'pending';
    case Accepted = 'accepted';
    case Active = 'active';
    case Rejected = 'rejected';
    case Completed = 'completed';

    public function label(): string
    {
        return Str::headline($this->value);
    }

    public function filamentColor(): string
    {
        return match ($this) {
            self::Pending => 'warning',
            self::Accepted => 'success',
            self::Active => 'info',
            self::Rejected => 'danger',
            self::Completed => 'gray',
        };
    }
}
