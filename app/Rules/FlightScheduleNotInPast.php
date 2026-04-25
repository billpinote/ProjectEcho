<?php

namespace App\Rules;

use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Contracts\Validation\DataAwareRule;
use Illuminate\Contracts\Validation\ValidationRule;

class FlightScheduleNotInPast implements DataAwareRule, ValidationRule
{
    /**
     * @var array<string, mixed>
     */
    private array $data = [];

    /**
     * @param  array<string, mixed>  $data
     */
    public function setData(array $data): static
    {
        $this->data = $data;

        return $this;
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $date = $this->parseDate($value);
        $time = $this->parseTime($this->data['proposed_time'] ?? null);

        if ($date === null || $time === null) {
            return;
        }

        $scheduledAt = $date->setTime((int) $time[0], (int) $time[1]);

        if ($scheduledAt->lessThanOrEqualTo(now('UTC'))) {
            $fail('The date of flight and proposed time must be in the future.');
        }
    }

    private function parseDate(mixed $value): ?CarbonImmutable
    {
        if (blank($value)) {
            return null;
        }

        try {
            return CarbonImmutable::parse((string) $value, 'UTC')->startOfDay();
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @return array{0: string, 1: string}|null
     */
    private function parseTime(mixed $value): ?array
    {
        if (blank($value)) {
            return null;
        }

        $digits = preg_replace('/\D/', '', trim((string) $value));

        if ($digits === null || strlen($digits) < 4) {
            return null;
        }

        $hours = substr($digits, 0, 2);
        $minutes = substr($digits, 2, 2);

        if ((int) $hours > 23 || (int) $minutes > 59) {
            return null;
        }

        return [$hours, $minutes];
    }
}
