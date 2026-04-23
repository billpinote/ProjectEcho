<?php

namespace App\Services;

use App\Models\Flight;

class FlightPlanICAOFormatter
{
    /**
     * Build the QR payload for a saved flight plan.
     */
    public static function toICAOMessage(Flight $flight): string
    {
        return sprintf('ECHOFPL|1|DB|%d', (int) $flight->getKey());
    }
}
