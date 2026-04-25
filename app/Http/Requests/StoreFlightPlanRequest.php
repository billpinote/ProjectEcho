<?php

namespace App\Http\Requests;

use App\Rules\FlightScheduleNotInPast;
use App\Rules\IcaoAerodrome;
use App\Rules\IcaoAircraftIdentification;
use App\Rules\IcaoCruisingSpeed;
use App\Rules\IcaoFlightLevel;
use App\Rules\IcaoFlightRules;
use App\Rules\IcaoTypeOfFlight;
use App\Rules\IcaoWakeTurbulenceCategory;
use App\Rules\UtcFourDigitTime;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

class StoreFlightPlanRequest extends FormRequest
{
    /**
     * Checkbox fields that must always resolve to an explicit boolean.
     *
     * @var array<int, string>
     */
    private const BOOLEAN_CHECKBOX_FIELDS = [
        'emergency_radio_uhf',
        'emergency_radio_vhf',
        'emergency_radio_elt',
        'survival_equipment_polar',
        'survival_equipment_desert',
        'survival_equipment_maritime',
        'survival_equipment_jungle',
        'jackets_light',
        'jackets_fluores',
        'jackets_uhf',
        'jackets_vhf',
        'dinghies_enabled',
        'authorized_representative_enabled',
    ];

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        $otherInformation = (string) $this->input('other_information', '');
        $dateOfFlightDof = $this->formatDateForDofTag($this->input('date_of_flight'));

        // Extract all tag values first
        $tagValues = [
            'other_info_dof' => $dateOfFlightDof ?? $this->extractOtherInfoTagValue('DOF', $otherInformation),
            'other_info_rmk' => $this->extractOtherInfoTagValue('RMK', $otherInformation),
            'other_info_typ' => $this->extractOtherInfoTagValue('TYP', $otherInformation),
            'other_info_dep' => $this->extractOtherInfoTagValue('DEP', $otherInformation),
            'other_info_route' => $this->extractOtherInfoTagValue('RTE', $otherInformation),
            'other_info_dest' => $this->extractOtherInfoTagValue('DEST', $otherInformation),
            'other_info_altn_1' => $this->extractOtherInfoTagValue('ALTN', $otherInformation),
            'other_info_altn_2' => $this->extractOtherInfoTagValue('ALTN2', $otherInformation),
            'other_info_pbn' => $this->extractOtherInfoTagValue('PBN', $otherInformation),
            'other_info_reg' => $this->extractOtherInfoTagValue('REG', $otherInformation),
            'other_info_opr' => $this->extractOtherInfoTagValue('OPR', $otherInformation),
        ];

        // Reorganize tags according to hierarchy and rebuild other_information
        $reorganizedInformation = $this->reorganizeTagsHierarchy($tagValues);

        $booleanCheckboxValues = [];
        foreach (self::BOOLEAN_CHECKBOX_FIELDS as $field) {
            $booleanCheckboxValues[$field] = $this->boolean($field);
        }

        $this->merge([
            'persons_on_board' => $this->normalizeNumericField($this->input('persons_on_board')),
            'dinghies_number' => $this->normalizeNumericField($this->input('dinghies_number')),
            'dinghies_capacity' => $this->normalizeNumericField($this->input('dinghies_capacity')),
            'proposed_time' => $this->prepareTimeField($this->input('proposed_time')),
            'total_eet' => $this->prepareTimeField($this->input('total_eet')),
            'endurance' => $this->prepareTimeField($this->input('endurance')),
            'other_information' => $reorganizedInformation,
            ...$booleanCheckboxValues,
            ...$tagValues,
        ]);
    }

    protected function passedValidation(): void
    {
        $this->merge([
            'proposed_time' => $this->normalizeTimeField($this->input('proposed_time')),
            'total_eet' => $this->normalizeTimeField($this->input('total_eet')),
            'endurance' => $this->normalizeTimeField($this->input('endurance')),
        ]);
    }

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'originator' => ['nullable', 'string', 'max:255'],
            'date_of_filing' => ['nullable', 'date'],
            'date_of_flight' => ['required', 'date', new FlightScheduleNotInPast],
            'aircraft_identification' => ['nullable', new IcaoAircraftIdentification],
            'flight_rules' => ['nullable', new IcaoFlightRules],
            'type_of_flight' => ['nullable', new IcaoTypeOfFlight],
            'number' => ['nullable', 'string', 'max:50'],
            'type_of_aircraft' => ['nullable', 'string', 'max:255'],
            'wake_turbulence_cat' => ['nullable', new IcaoWakeTurbulenceCategory],
            'equipment_10a' => ['nullable', 'string', 'max:255'],
            'equipment_10b' => ['nullable', 'string', 'max:255'],
            'departure_aerodrome' => ['nullable', new IcaoAerodrome],
            'proposed_time' => ['nullable', new UtcFourDigitTime],
            'cruising_speed' => ['nullable', new IcaoCruisingSpeed],
            'level' => ['nullable', new IcaoFlightLevel],
            'route' => ['nullable', 'string'],
            'destination_aerodrome' => ['nullable', new IcaoAerodrome],
            'total_eet' => ['nullable', new UtcFourDigitTime],
            'altn_aerodrome_1' => ['nullable', new IcaoAerodrome],
            'altn_aerodrome_2' => ['nullable', new IcaoAerodrome],
            'other_information' => ['nullable', 'string'],
            'other_info_dep' => ['nullable', 'string', 'max:255'],
            'other_info_dest' => ['nullable', 'string', 'max:255'],
            'other_info_rmk' => ['nullable', 'string', 'max:255'],
            'other_info_pbn' => ['nullable', 'string', 'max:255'],
            'other_info_route' => ['nullable', 'string', 'max:255'],
            'other_info_typ' => ['nullable', 'string', 'max:255'],
            'other_info_reg' => ['nullable', 'string', 'max:255'],
            'other_info_altn_1' => ['nullable', 'string', 'max:255'],
            'other_info_altn_2' => ['nullable', 'string', 'max:255'],
            'other_info_opr' => ['nullable', 'string', 'max:255'],
            'other_info_dof' => ['nullable', 'string', 'max:255'],
            'endurance' => ['nullable', new UtcFourDigitTime],
            'persons_on_board' => ['nullable', 'regex:/^\d{1,3}$/'],
            'emergency_radio_uhf' => ['nullable', 'boolean'],
            'emergency_radio_vhf' => ['nullable', 'boolean'],
            'emergency_radio_elt' => ['nullable', 'boolean'],
            'survival_equipment_polar' => ['nullable', 'boolean'],
            'survival_equipment_desert' => ['nullable', 'boolean'],
            'survival_equipment_maritime' => ['nullable', 'boolean'],
            'survival_equipment_jungle' => ['nullable', 'boolean'],
            'jackets_light' => ['nullable', 'boolean'],
            'jackets_fluores' => ['nullable', 'boolean'],
            'jackets_uhf' => ['nullable', 'boolean'],
            'jackets_vhf' => ['nullable', 'boolean'],
            'dinghies_enabled' => ['nullable', 'boolean'],
            'dinghies_number' => ['nullable', 'integer', 'min:0'],
            'dinghies_capacity' => ['nullable', 'integer', 'min:0'],
            'dinghies_cover' => ['nullable', 'string', 'max:255'],
            'dinghies_color' => ['nullable', 'string', 'max:255'],
            'aircraft_colour_and_markings' => ['nullable', 'string'],
            'remarks' => ['nullable', 'string'],
            'pilot_in_command' => ['nullable', 'string', 'max:255'],
            'pilot_license_no' => ['nullable', 'string', 'max:255'],
            'pilot_ratings' => ['nullable', 'string', 'max:255'],
            'license_expiry_date' => ['nullable', 'date'],
            'authorized_representative_enabled' => ['nullable', 'boolean'],
            'authorized_representative_name' => ['nullable', 'string', 'max:255'],
            'authorized_representative_role' => ['nullable', 'string', 'max:255'],
            'authorized_representative_id_license' => ['nullable', 'string', 'max:255'],
            'authorized_representative_expiry_date' => ['nullable', 'date'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $departureAerodrome = strtoupper(trim((string) $this->input('departure_aerodrome', '')));
            $destinationAerodrome = strtoupper(trim((string) $this->input('destination_aerodrome', '')));
            $typeOfAircraft = strtoupper(trim((string) $this->input('type_of_aircraft', '')));
            $altnAerodrome1 = strtoupper(trim((string) $this->input('altn_aerodrome_1', '')));
            $altnAerodrome2 = strtoupper(trim((string) $this->input('altn_aerodrome_2', '')));
            $otherInformation = (string) $this->input('other_information', '');

            if ($departureAerodrome === 'ZZZZ' && stripos($otherInformation, 'DEP/') === false) {
                $validator->errors()->add('other_information', 'When departure aerodrome is ZZZZ, Other Information must include DEP/.');
            }

            if ($destinationAerodrome === 'ZZZZ' && stripos($otherInformation, 'DEST/') === false) {
                $validator->errors()->add('other_information', 'When destination aerodrome is ZZZZ, Other Information must include DEST/.');
            }

            if ($typeOfAircraft === 'ZZZZ' && stripos($otherInformation, 'TYP/') === false) {
                $validator->errors()->add('other_information', 'When type of aircraft is ZZZZ, Other Information must include TYP/.');
            }

            if ($altnAerodrome1 === 'ZZZZ' && stripos($otherInformation, 'ALTN/') === false) {
                $validator->errors()->add('other_information', 'When alternate aerodrome 1 is ZZZZ, Other Information must include ALTN/.');
            }

            if ($altnAerodrome2 === 'ZZZZ' && stripos($otherInformation, 'ALTN2/') === false) {
                $validator->errors()->add('other_information', 'When alternate aerodrome 2 is ZZZZ, Other Information must include ALTN2/.');
            }
        });
    }

    /**
     * Get the validation messages that apply to the request.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'persons_on_board.regex' => 'Persons on board must be a numeric value from 000 to 999.',
        ];
    }

    private function normalizeNumericField(mixed $value): mixed
    {
        if ($value === null) {
            return null;
        }

        $trimmed = trim((string) $value);

        return $trimmed === '' ? null : $trimmed;
    }

    private function normalizeTimeField(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $time = trim((string) $value);

        if ($time === '') {
            return null;
        }

        return UtcFourDigitTime::normalizeForStorage($time);
    }

    private function prepareTimeField(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $time = trim((string) $value);

        return $time === '' ? null : $time;
    }

    private function reorganizeTagsHierarchy(array $tagValues): string
    {
        // Define the hierarchy order for tags
        $hierarchy = [
            'DOF' => 'other_info_dof',
            'RMK' => 'other_info_rmk',
            'TYP' => 'other_info_typ',
            'DEP' => 'other_info_dep',
            'RTE' => 'other_info_route',
            'DEST' => 'other_info_dest',
            'ALTN' => 'other_info_altn_1',
            'ALTN2' => 'other_info_altn_2',
            'PBN' => 'other_info_pbn',
            'REG' => 'other_info_reg',
            'OPR' => 'other_info_opr',
        ];

        $parts = [];

        foreach ($hierarchy as $tag => $key) {
            $value = $tagValues[$key] ?? null;
            if ($value !== null) {
                $parts[] = $tag.'/'.$value;
            }
        }

        return implode(' ', $parts);
    }

    private function formatDateForDofTag(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $digits = preg_replace('/\D/', '', trim((string) $value));

        if ($digits === null || strlen($digits) !== 8) {
            return null;
        }

        return $digits;
    }

    private function extractOtherInfoTagValue(string $tag, string $text): ?string
    {
        if ($text === '') {
            return null;
        }

        // Find the position of our tag
        $tagWithSlash = $tag.'/';
        $tagPos = stripos($text, $tagWithSlash);

        if ($tagPos === false) {
            return null;
        }

        // Start collecting from after the tag and slash
        $startPos = $tagPos + strlen($tagWithSlash);

        // Find the next tag pattern in the remaining text
        $remainingText = substr($text, $startPos);

        // Pattern to find the next tag: 2-5 capital letters/numbers followed by /
        if (preg_match('/\s+[A-Z0-9]{2,5}\//i', $remainingText, $matches, PREG_OFFSET_CAPTURE)) {
            // Extract value from start to where the next tag begins
            $value = substr($remainingText, 0, $matches[0][1]);
        } else {
            // No next tag found, take everything remaining
            $value = $remainingText;
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }
}
