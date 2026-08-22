<?php

namespace App\Domain\FlightPlans\Support;

use App\Domain\FlightPlans\Enums\FlightPlanStatus;
use App\Domain\Pilots\Enums\PilotLicenseType;
use App\Domain\Users\Enums\UserRole;
use App\Models\User;

class FlightPlanPreparerContext
{
    public const CAPACITY_SELF_PIC = 'self_pic';

    public const CAPACITY_FOR_ANOTHER_PIC = 'for_another_pic';

    /**
     * @param  array<string, mixed>  $state
     */
    public function __construct(
        private readonly ?User $preparer,
        private readonly array $state = [],
    ) {}

    /**
     * @param  array<string, mixed>  $state
     */
    public static function for(?User $preparer, array $state = []): self
    {
        return new self($preparer, $state);
    }

    public function capacity(): string
    {
        if ($this->hasAutomaticallyResolvedCapacity()) {
            return self::CAPACITY_FOR_ANOTHER_PIC;
        }

        if ($this->preparer?->isPilot()) {
            return (string) ($this->state['filing_capacity'] ?? self::CAPACITY_SELF_PIC) === self::CAPACITY_FOR_ANOTHER_PIC
                ? self::CAPACITY_FOR_ANOTHER_PIC
                : self::CAPACITY_SELF_PIC;
        }

        return self::CAPACITY_SELF_PIC;
    }

    public function shouldShowFilingCapacityControl(): bool
    {
        return $this->preparer?->isPilot() === true
            && ! $this->hasAutomaticallyResolvedCapacity();
    }

    public function isPreparingForAnotherPic(): bool
    {
        return $this->capacity() === self::CAPACITY_FOR_ANOTHER_PIC;
    }

    public function preparerActsAsPic(): bool
    {
        return $this->preparer?->isPilot() === true && ! $this->isPreparingForAnotherPic();
    }

    public function shouldAutoEnableAuthorizedRepresentative(): bool
    {
        return $this->isPreparingForAnotherPic();
    }

    public function shouldLockAuthorizedRepresentativeFields(): bool
    {
        return $this->isPreparingForAnotherPic();
    }

    public function shouldLockPicQualificationFields(): bool
    {
        return $this->isPreparingForAnotherPic();
    }

    public function shouldRequirePicAuthorization(): bool
    {
        return $this->isPreparingForAnotherPic();
    }

    public function representativeName(): ?string
    {
        return $this->preparer?->preparedByNameSnapshot();
    }

    public function representativeRole(): ?string
    {
        if ($this->preparer?->isDispatch()) {
            $position = trim((string) ($this->preparer->dispatchProfile?->position ?? ''));

            if ($position !== '') {
                return strtoupper($position);
            }

            $department = trim((string) ($this->preparer->dispatchProfile?->department ?? ''));

            return $department !== '' ? strtoupper($department) : UserRole::Dispatch->label();
        }

        if ($this->isStudentPilot()) {
            return 'STUDENT PILOT';
        }

        return $this->preparer?->preparedByRoleSnapshot();
    }

    public function representativeIdOrLicense(): ?string
    {
        if ($this->preparer?->isOperatorStaff()) {
            return $this->firstFilled([
                $this->preparer->operatorStaffProfile?->company_employee_id,
                $this->preparer->operatorStaffProfile?->authorization_reference,
                $this->preparer->employee_id,
                $this->preparer->username,
            ]);
        }

        if ($this->preparer?->isDispatch()) {
            return $this->firstFilled([
                $this->preparer->dispatchProfile?->dispatcher_license_number,
                $this->preparer->dispatchProfile?->dispatcher_certificate,
                $this->preparer->employee_id,
                $this->preparer->username,
            ]);
        }

        if ($this->preparer?->isPilot()) {
            return $this->firstFilled([
                $this->preparer->pilotProfile?->formattedLicense(),
                $this->preparer->employee_id,
                $this->preparer->username,
            ]);
        }

        return $this->firstFilled([
            $this->preparer?->employee_id,
            $this->preparer?->username,
        ]);
    }

    public function representativeAuthorizationExpiry(): ?string
    {
        if ($this->preparer?->isOperatorStaff()) {
            return $this->preparer->operatorStaffProfile?->authorization_expiry_date?->toDateString();
        }

        if ($this->preparer?->isPilot()) {
            return $this->preparer->pilotProfile?->license_expiry_date?->toDateString();
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function applyToFlightData(array $data): array
    {
        if ($this->preparer === null) {
            return $data;
        }

        $data['filed_by_user_id'] = $this->preparer->id;
        $data['prepared_by_user_id'] = $this->preparer->id;
        $data['prepared_by_name'] = $this->preparer->preparedByNameSnapshot();
        $data['prepared_by_role'] = $this->representativeRole();

        if ($this->isPreparingForAnotherPic()) {
            $data['status'] = FlightPlanStatus::AwaitingPic;
            $data['pilot_id'] = null;
            $data['pilot_in_command_user_id'] = null;
            $data['pilot_in_command'] = null;
            $data['pilot_license_no'] = null;
            $data['pilot_ratings'] = null;
            $data['license_expiry_date'] = null;
            $data['authorized_representative_enabled'] = true;
            $data['authorized_representative_name'] = $this->representativeName();
            $data['authorized_representative_role'] = $this->representativeRole();
            $data['authorized_representative_id_license'] = $this->representativeIdOrLicense();
            $data['authorized_representative_expiry_date'] = $this->representativeAuthorizationExpiry();
        } elseif ($this->preparerActsAsPic()) {
            $data['authorized_representative_enabled'] = false;
            $data['authorized_representative_name'] = null;
            $data['authorized_representative_role'] = null;
            $data['authorized_representative_id_license'] = null;
            $data['authorized_representative_expiry_date'] = null;
        }

        unset($data['filing_capacity']);

        return $data;
    }

    /**
     * @param  array<int, mixed>  $values
     */
    private function firstFilled(array $values): ?string
    {
        foreach ($values as $value) {
            $value = trim((string) ($value ?? ''));

            if ($value !== '') {
                return strtoupper($value);
            }
        }

        return null;
    }

    private function hasAutomaticallyResolvedCapacity(): bool
    {
        return $this->preparer?->isOperatorStaff()
            || $this->preparer?->isDispatch()
            || $this->isStudentPilot();
    }

    private function isStudentPilot(): bool
    {
        return $this->preparer?->isPilot() === true
            && $this->preparer->pilotProfile?->license_type === PilotLicenseType::StudentPilot;
    }
}
