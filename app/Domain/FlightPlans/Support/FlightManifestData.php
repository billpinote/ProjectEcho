<?php

namespace App\Domain\FlightPlans\Support;

use App\Models\Flight;
use Illuminate\Support\Facades\Validator;

class FlightManifestData
{
    /** @return array<string, mixed> */
    public function fromFlight(Flight $flight): array
    {
        return $flight->only([
            'aircraft_identification', 'type_of_aircraft', 'date_of_flight',
            'departure_aerodrome', 'destination_aerodrome', 'pilot_in_command',
            'pilot_license_no', 'pilot_ratings', 'license_expiry_date', 'persons_on_board',
        ]);
    }

    /** @return array<string, mixed> */
    public function defaults(Flight $flight): array
    {
        return [
            'home_base_hangar' => null,
            'coa_expiry_date' => $flight->other_info_expiry_date_to_operate,
            'persons' => filled($flight->pilot_in_command) ? [[
                'full_name' => $flight->pilot_in_command,
                'nationality' => null,
                'classification' => 'pic',
            ]] : [],
        ];
    }

    /**
     * Pure validation usable by a future guest workflow without saving data.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function validate(array $data, bool $complete = false): array
    {
        return Validator::make($data, $this->rules($complete))->validate();
    }

    /** @return array<string, list<string>> */
    public function rules(bool $complete = false): array
    {
        $presence = $complete ? 'required' : 'nullable';

        return [
            'aircraft_owner_name' => [$presence, 'string', 'max:255'],
            'aircraft_owner_contact_number' => [$presence, 'string', 'max:255'],
            'home_base_hangar' => [$presence, 'string', 'max:255'],
            'coa_expiry_date' => [$presence, 'date_format:Y-m-d'],
            'cargo_type' => ['nullable', 'string', 'max:255'],
            'cargo_quantity' => ['nullable', 'string', 'max:255'],
            'total_cargo_weight' => [$presence, 'numeric', 'min:0', 'max:999999999.999', 'decimal:0,3'],
            'remarks' => ['nullable', 'string', 'max:5000'],
            'persons' => [$complete ? 'required' : 'sometimes', 'array', 'list', ...($complete ? ['min:1'] : [])],
            'persons.*.full_name' => ['required', 'string', 'max:255'],
            'persons.*.nationality' => [$presence, 'string', 'max:255'],
            'persons.*.classification' => ['required', 'in:pic,crew,passenger'],
        ];
    }

    /** @return array{manifest_count: int, flight_plan_count: ?int, matches: bool} */
    public function comparePersons(int $manifestCount, mixed $personsOnBoard): array
    {
        $expected = filter_var($personsOnBoard, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

        return [
            'manifest_count' => $manifestCount,
            'flight_plan_count' => $expected === false ? null : $expected,
            'matches' => $expected !== false && $expected === $manifestCount,
        ];
    }
}
