<?php

namespace App\Services\ProfileUpdates;

use App\Models\ProfileUpdateRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class ProfileAuditRecorder
{
    public function recordFieldChange(
        User $subject,
        ?User $actor,
        string $action,
        string $source,
        string $field,
        mixed $old,
        mixed $new,
        ?Model $auditable = null,
        ?ProfileUpdateRequest $request = null,
        ?string $remarks = null,
    ): void {
        $subject->auditLogs()->create([
            'performed_by_user_id' => $actor?->getKey(),
            'action' => $action,
            'source' => $source,
            'field' => $field,
            'old_value' => $this->stringValue(ProfileFieldRegistry::labelForValue($field, $old)),
            'new_value' => $this->stringValue(ProfileFieldRegistry::labelForValue($field, $new)),
            'auditable_type' => $auditable?->getMorphClass(),
            'auditable_id' => $auditable?->getKey(),
            'profile_update_request_id' => $request?->getKey(),
            'changes' => [
                $field => [
                    'old' => $old,
                    'new' => $new,
                    'old_label' => ProfileFieldRegistry::labelForValue($field, $old),
                    'new_label' => ProfileFieldRegistry::labelForValue($field, $new),
                ],
            ],
            'description' => match ($action) {
                'signature_submitted' => 'Signature version submitted for KYC review; approved signature unchanged.',
                'signature_approved' => 'Reviewed signature version approved and activated.',
                'signature_rejected' => 'Signature version rejected; approved signature unchanged.',
                'signature_replaced' => 'Approved signature replaced; previous version preserved.',
                default => str($field)->replace(['.', '_'], ' ')->headline().' changed.',
            },
            'remarks' => $remarks,
            'ip_address' => request()?->ip(),
            'user_agent' => request()?->userAgent(),
        ]);
    }

    private function stringValue(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        return (string) $value;
    }
}
