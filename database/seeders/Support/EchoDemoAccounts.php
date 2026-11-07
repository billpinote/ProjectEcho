<?php

namespace Database\Seeders\Support;

use App\Domain\Pilots\Enums\PilotLicenseType;
use App\Domain\Users\Enums\UserRole;

class EchoDemoAccounts
{
    /** @return array<string, string> */
    public static function operators(): array
    {
        return [
            'LEIAA' => 'Leading Edge International Aviation Academy',
            'AAG' => 'Alpha Aviation Group',
        ];
    }

    /** @return list<array{email: string, name: string, role: UserRole, operator: ?string, license: ?PilotLicenseType, number: string}> */
    public static function users(): array
    {
        $accounts = [
            ['pilot1.leiaa@example.com', 'Adrian Mendoza', UserRole::Pilot, 'LEIAA', PilotLicenseType::CommercialPilot],
            ['pilot2.leiaa@example.com', 'Bianca Reyes', UserRole::Pilot, 'LEIAA', PilotLicenseType::PrivatePilot],
            ['dispatch1.leiaa@example.com', 'Carlo Navarro', UserRole::Dispatch, 'LEIAA', null],
            ['operator1.leiaa@example.com', 'Diana Santos', UserRole::OperatorStaff, 'LEIAA', null],
            ['student1.leiaa@example.com', 'Ethan Villanueva', UserRole::Pilot, 'LEIAA', PilotLicenseType::StudentPilot],
            ['pilot1.aag@example.com', 'Gabriel Cruz', UserRole::Pilot, 'AAG', PilotLicenseType::CommercialPilot],
            ['pilot2.aag@example.com', 'Hannah Flores', UserRole::Pilot, 'AAG', PilotLicenseType::PrivatePilot],
            ['dispatch1.aag@example.com', 'Isaac Ramos', UserRole::Dispatch, 'AAG', null],
            ['operator1.aag@example.com', 'Julia Mercado', UserRole::OperatorStaff, 'AAG', null],
            ['student1.aag@example.com', 'Kyle Bautista', UserRole::Pilot, 'AAG', PilotLicenseType::StudentPilot],
            ['atmo1.rpus@example.com', 'Lorenzo Garcia', UserRole::Atmo, null, null],
            ['atmo2.rpus@example.com', 'Maria Castillo', UserRole::Atmo, null, null],
            ['admin1.echo@example.com', 'Nathan Rivera', UserRole::Admin, null, null],
            ['avsec1.rpus@example.com', 'Olivia Torres', UserRole::Avsec, null, null],
            ['avsec2.rpus@example.com', 'Paolo Fernandez', UserRole::Avsec, null, null],
        ];

        return array_map(static fn (array $account, int $index): array => [
            'email' => $account[0], 'name' => $account[1], 'role' => $account[2],
            'operator' => $account[3], 'license' => $account[4],
            'number' => (string) (990001 + $index),
        ], $accounts, array_keys($accounts));
    }
}
