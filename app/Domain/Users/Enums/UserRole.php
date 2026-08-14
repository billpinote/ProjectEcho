<?php

namespace App\Domain\Users\Enums;

enum UserRole: string
{
    case Artisan = 'ARTISAN';
    case Admin = 'ADMIN';
    case Atmo = 'ATMO';
    case AtsHq = 'ATSHQ';
    case Avsec = 'AVSEC';
    case Dispatch = 'DISPATCH';
    case OperatorStaff = 'OPERATOR_STAFF';
    case Pilot = 'PILOT';

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_map(
            static fn (self $role): string => $role->value,
            self::cases(),
        );
    }

    public static function normalize(mixed $value): ?self
    {
        if ($value instanceof self) {
            return $value;
        }

        $value = strtoupper(trim((string) $value));

        return match ($value) {
            'ATC' => self::Atmo,
            'OPERATORSTAFF', 'OPERATOR STAFF' => self::OperatorStaff,
            default => self::tryFrom($value),
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Artisan => 'Artisan',
            self::Admin => 'Admin',
            self::Atmo => 'ATMO',
            self::AtsHq => 'ATSHQ',
            self::Avsec => 'AVSEC',
            self::Dispatch => 'Dispatch',
            self::OperatorStaff => 'Operator Staff',
            self::Pilot => 'Pilot',
        };
    }
}
