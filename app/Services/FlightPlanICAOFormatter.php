<?php

namespace App\Services;

use App\Models\Flight;

class FlightPlanICAOFormatter
{
    /**
     * Backwards-compatible wrapper for callers that still use the older formatter name.
     */
    public static function toICAOMessage(Flight $flight): string
    {
        return app(FlightPlanQrPayloadService::class)->buildPayload($flight) ?? '';
    }
}
