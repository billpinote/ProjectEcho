<?php

namespace App\Domain\FlightPlans\Support;

use App\Domain\FlightPlans\Rules\UtcFourDigitTime;

class FlightPlanDataNormalizer
{
    /** @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function normalizePublicPreview(array $data): array
    {
        foreach (['proposed_time', 'total_eet', 'endurance'] as $field) {
            if (array_key_exists($field, $data)) {
                $data[$field] = UtcFourDigitTime::normalizeForStorage($data[$field]);
            }
        }
        $data['date_of_filing'] = $data['date_of_filing'] ?? now('UTC')->toDateString();

        return $this->normalizeNumericFlightFields($this->uppercaseStringFlightFields($data));
    }

    /** @param array<string, mixed> $flightData
     * @return array<string, mixed>
     */
    public function prepareFlightPlanPreviewInput(array $flightData): array
    {
        foreach (['proposed_time', 'total_eet', 'endurance'] as $field) {
            if (isset($flightData[$field]) && is_string($flightData[$field])) {
                $flightData[$field] = str_replace(':', '', $flightData[$field]);
            }
        }

        return $flightData;
    }

    /** @param array<string, mixed> $flightData */
    public function isRpusOperationalFlightData(array $flightData): bool
    {
        return strtoupper(trim((string) ($flightData['departure_aerodrome'] ?? ''))) === 'RPUS';
    }

    /** @param array<string, mixed> $flightData
     * @return array<string, mixed>
     */
    public function preparePreviewFlightAttributes(array $flightData): array
    {
        $otherInformation = (string) ($flightData['other_information'] ?? '');

        $tagMap = [
            'other_info_dof' => 'DOF',
            'other_info_rmk' => 'RMK',
            'other_info_typ' => 'TYP',
            'other_info_dep' => 'DEP',
            'other_info_route' => 'RTE',
            'other_info_dest' => 'DEST',
            'other_info_altn_1' => 'ALTN',
            'other_info_altn_2' => 'ALTN2',
            'other_info_pbn' => 'PBN',
            'other_info_reg' => 'REG',
            'other_info_opr' => 'OPR',
        ];

        foreach ($tagMap as $field => $tag) {
            if (! array_key_exists($field, $flightData) || blank($flightData[$field])) {
                $flightData[$field] = self::extractOtherInfoTagValue($tag, $otherInformation);
            }
        }

        return $flightData;
    }

    public static function extractOtherInfoTagValue(string $tag, string $text): ?string
    {
        if ($text === '') {
            return null;
        }

        $tagWithSlash = $tag.'/';
        $tagPos = stripos($text, $tagWithSlash);

        if ($tagPos === false) {
            return null;
        }

        $startPos = $tagPos + strlen($tagWithSlash);
        $remainingText = substr($text, $startPos);

        if (preg_match('/\s+[A-Z0-9]{2,5}\//i', $remainingText, $matches, PREG_OFFSET_CAPTURE)) {
            $value = substr($remainingText, 0, $matches[0][1]);
        } else {
            $value = $remainingText;
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }

    /** @param array<string, mixed> $validated
     * @return array<string, mixed>
     */
    public function normalizeNumericFlightFields(array $validated): array
    {
        foreach (['persons_on_board', 'dinghies_number', 'dinghies_capacity'] as $field) {
            if (array_key_exists($field, $validated) && $validated[$field] !== null && $validated[$field] !== '') {
                $validated[$field] = (int) $validated[$field];
            }
        }

        return $validated;
    }

    /** @param array<string, mixed> $validated
     * @return array<string, mixed>
     */
    public function uppercaseStringFlightFields(array $validated): array
    {
        foreach ($validated as $field => $value) {
            if (is_string($value)) {
                $validated[$field] = strtoupper(trim($value));
            }
        }

        return $validated;
    }

    /** @param array<string, ?string> $tagValues */
    public static function reorganizeOtherInformationTags(array $tagValues): string
    {
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
}
