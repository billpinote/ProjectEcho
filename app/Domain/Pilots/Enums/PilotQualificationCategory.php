<?php

namespace App\Domain\Pilots\Enums;

enum PilotQualificationCategory: string
{
    case AircraftRating = 'aircraft_rating';
    case InstrumentRating = 'instrument_rating';
    case InstructorRating = 'instructor_rating';
    case Endorsement = 'endorsement';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::AircraftRating => 'Aircraft Rating',
            self::InstrumentRating => 'Instrument Rating',
            self::InstructorRating => 'Instructor Rating',
            self::Endorsement => 'Endorsement',
            self::Other => 'Other',
        };
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return collect(self::cases())
            ->mapWithKeys(fn (self $category): array => [$category->value => $category->label()])
            ->all();
    }
}
