<?php

namespace App\Enums;

enum UserRole: string
{
    case Artisan = 'ARTISAN';
    case Admin = 'ADMIN';
    case Atmo = 'ATMO';
    case AtsHq = 'ATSHQ';
    case Avsec = 'AVSEC';
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
            self::Pilot => 'Pilot',
        };
    }
}
