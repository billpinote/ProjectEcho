<?php

namespace App\Domain\FlightPlans\Support;

use App\Models\User;

class AuthenticatedOperatorFlightData
{
    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function apply(array $data, ?User $user): array
    {
        unset($data['operator_id']);

        if (! $user?->isPilot() && ! $user?->isDispatch() && ! $user?->isOperatorStaff()) {
            return $data;
        }

        $data['operator_id'] = $user->operator_id;

        $operatorName = $user->flightPlanOperatorName();
        $data['other_information'] = self::replaceOtherInformationTag(
            (string) ($data['other_information'] ?? ''),
            'OPR',
            $operatorName,
        );
        $data['other_info_opr'] = $operatorName;

        return $data;
    }

    private static function replaceOtherInformationTag(string $otherInformation, string $tag, ?string $value): string
    {
        $withoutTag = trim((string) preg_replace(
            '/(?:^|\s)'.preg_quote($tag, '/').'\/.*?(?=\s+[A-Z0-9]{2,5}\/|$)/i',
            ' ',
            $otherInformation,
        ));

        $value = trim((string) ($value ?? ''));

        if ($value === '') {
            return preg_replace('/\s+/', ' ', $withoutTag) ?? $withoutTag;
        }

        return trim(preg_replace('/\s+/', ' ', $withoutTag.' '.$tag.'/'.$value) ?? $withoutTag.' '.$tag.'/'.$value);
    }
}
