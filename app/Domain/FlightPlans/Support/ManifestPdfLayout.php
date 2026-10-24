<?php

namespace App\Domain\FlightPlans\Support;

class ManifestPdfLayout
{
    public const WRAPPED_CREDENTIALS = [382, 697, 194, 29];

    /** Native PDF points measured from the top left of the 612 x 792 Letter page. */
    public const FIELDS = [
        'aircraft_identification' => [49, 148, 168, 23],
        'type_of_aircraft' => [228, 148, 168, 23],
        'date_of_flight' => [408, 148, 168, 23],
        'home_base_hangar' => [49, 189, 168, 23],
        'owner_and_phone' => [228, 189, 168, 23],
        'coa_expiry_date' => [408, 189, 168, 23],
        'cargo_type' => [49, 230, 168, 23],
        'cargo_quantity' => [228, 230, 168, 23],
        'total_cargo_weight' => [408, 230, 168, 23],
        'pilot_in_command' => [49, 711, 322, 15],
        'credentials' => [382, 711, 194, 15],
    ];

    /** Measured row tops; source row heights vary slightly. */
    public const ROW_TOPS = [270.08, 284.08, 298.27, 312.47, 326.48, 340.67, 354.70, 368.90, 383.10, 397.10, 411.30, 425.30, 439.50, 453.52, 467.72, 481.92, 495.92, 510.12, 524.12, 538.32, 552.55, 566.55, 580.75, 594.75, 608.95];
}
