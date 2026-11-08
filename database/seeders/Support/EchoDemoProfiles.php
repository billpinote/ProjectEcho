<?php

namespace Database\Seeders\Support;

use App\Domain\Pilots\Enums\PilotLicenseType;
use App\Domain\Pilots\Enums\PilotQualificationCategory;
use App\Domain\Users\Enums\UserRole;
use App\Models\PilotProfile;
use App\Models\User;

class EchoDemoProfiles
{
    public const REMARKS = 'PROJECT ECHO TEST DATA — fictional development fixture, not verified credentials.';

    public function create(User $user, ?PilotLicenseType $license, string $number): ?PilotProfile
    {
        $expiry = now('UTC')->addYears(2)->toDateString();

        if ($license !== null) {
            $codes = match ($license) {
                PilotLicenseType::CommercialPilot => ['ASEL', 'IR'],
                PilotLicenseType::PrivatePilot => ['ASEL'],
                default => ['SPL'],
            };
            $profile = $user->pilotProfile()->firstOrCreate([], [
                'license_type' => $license,
                'license_number' => $number,
                'ratings' => implode(' ', $codes),
                'license_expiry_date' => now('UTC')->addYears(3)->toDateString(),
                'medical_expiry_date' => $expiry,
                'operator' => null,
                'remarks' => self::REMARKS,
            ]);

            // Preserve a profile subsequently replaced or edited through the application.
            if ($profile->remarks !== self::REMARKS || $profile->license_type !== $license || $profile->license_number !== $number
                || $profile->license_expiry_date === null || $profile->medical_expiry_date === null) {
                return null;
            }

            foreach ($codes as $code) {
                [$category, $description] = match ($code) {
                    'ASEL' => [PilotQualificationCategory::AircraftRating, 'Airplane Single Engine Land'],
                    'IR' => [PilotQualificationCategory::InstrumentRating, 'Instrument Rating'],
                    default => [PilotQualificationCategory::Endorsement, 'Student training only; not PIC authorization'],
                };
                $profile->qualifications()->firstOrCreate([
                    'category' => $category->value, 'code' => $code,
                ], [
                    'description' => $description, 'expiry_date' => $expiry, 'remarks' => self::REMARKS,
                ]);
            }

            return $profile;
        }

        match ($user->role) {
            UserRole::Dispatch => $user->dispatchProfile()->firstOrCreate([], [
                'dispatcher_license_number' => 'TEST-DISP-'.$number,
                'dispatcher_certificate' => 'TEST-CERT-'.$number,
                'department' => 'Demo Operations', 'position' => 'Dispatcher',
                'shift' => 'Day', 'remarks' => self::REMARKS,
            ]),
            UserRole::OperatorStaff => $user->operatorStaffProfile()->firstOrCreate([], [
                'operator_id' => $user->operator_id, 'position_title' => 'Operator Representative',
                'company_employee_id' => 'TEST-EMP-'.$number,
                'authorization_reference' => 'TEST-REP-'.$number,
                'authorization_expiry_date' => $expiry, 'is_authorized' => true,
                'remarks' => self::REMARKS,
            ]),
            UserRole::Atmo => $user->atcProfile()->firstOrCreate([], [
                'wiresign' => $user->wiresign, 'facility' => 'RPUS TWR',
                'position' => 'Demo Controller', 'endorsements' => 'Demo IFR, VFR',
                'remarks' => self::REMARKS,
            ]),
            UserRole::Avsec => $user->avsecProfile()->firstOrCreate([], [
                'security_certification' => 'TEST-SEC-'.$number, 'certification_expiry' => $expiry,
                'security_clearance_level' => 'Demo only', 'position' => 'Demo Security Officer',
                'remarks' => self::REMARKS,
            ]),
            default => null,
        };

        return null;
    }
}
