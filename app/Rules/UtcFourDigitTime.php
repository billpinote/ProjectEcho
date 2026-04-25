<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Str;

class UtcFourDigitTime implements ValidationRule
{
    public static function message(string $attribute = ':attribute'): string
    {
        $attribute = $attribute === ':attribute'
            ? $attribute
            : Str::headline(str_replace('_', ' ', $attribute));

        return "The {$attribute} must be a valid UTC time in 4-digit HHMM format between 0000 and 2359.";
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (blank($value)) {
            return;
        }

        if (! self::isValid($value)) {
            $fail(self::message($attribute));
        }
    }

    public static function isValid(mixed $value): bool
    {
        $time = trim((string) $value);

        if (! preg_match('/^\d{4}$/', $time)) {
            return false;
        }

        $hours = (int) substr($time, 0, 2);
        $minutes = (int) substr($time, 2, 2);

        return $hours <= 23 && $minutes <= 59;
    }

    public static function normalizeForStorage(mixed $value): ?string
    {
        if (blank($value)) {
            return null;
        }

        $time = trim((string) $value);

        if (! self::isValid($time)) {
            return $time;
        }

        return substr($time, 0, 2).':'.substr($time, 2, 2);
    }

    public static function normalizeDatabaseTime(mixed $value): ?string
    {
        if (blank($value)) {
            return null;
        }

        $time = trim((string) $value);

        if (self::isValid($time)) {
            return self::normalizeForStorage($time);
        }

        if (preg_match('/^\d{2}:\d{2}(?::\d{2})?$/', $time) !== 1) {
            return $time;
        }

        [$hours, $minutes] = array_map('intval', explode(':', substr($time, 0, 5)));

        if ($hours > 23 || $minutes > 59) {
            return $time;
        }

        return sprintf('%02d:%02d', $hours, $minutes);
    }
}
