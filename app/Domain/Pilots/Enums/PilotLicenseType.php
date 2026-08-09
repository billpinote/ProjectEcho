<?php

namespace App\Domain\Pilots\Enums;

enum PilotLicenseType: string
{
    case StudentPilot = 'SPL';
    case PrivatePilot = 'PPL';
    case CommercialPilot = 'CPL';
    case AirlineTransportPilot = 'ATPL';

    public function label(): string
    {
        return match ($this) {
            self::StudentPilot => 'SPL',
            self::PrivatePilot => 'PPL',
            self::CommercialPilot => 'CPL',
            self::AirlineTransportPilot => 'ATPL',
        };
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $type): array => [$type->value => $type->label()])
            ->all();
    }
}
