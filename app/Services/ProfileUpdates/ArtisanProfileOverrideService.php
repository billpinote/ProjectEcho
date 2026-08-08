<?php

namespace App\Services\ProfileUpdates;

use App\Domain\Users\Enums\UserRole;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ArtisanProfileOverrideService
{
    public function __construct(private readonly ProfileAuditRecorder $auditRecorder) {}

    /**
     * @param  array<string, mixed>  $values
     */
    public function override(User $subject, User $artisan, array $values, string $reason): void
    {
        abort_unless($artisan->is_active && $artisan->role === UserRole::Artisan, 403);

        if (blank($reason)) {
            throw new \InvalidArgumentException('Artisan override reason is required.');
        }

        DB::transaction(function () use ($subject, $artisan, $values, $reason): void {
            $subject->refresh()->loadMissing(['pilotProfile', 'dispatchProfile', 'atcProfile', 'avsecProfile']);
            $changes = ProfileFieldRegistry::changesForRequest($subject, $values);

            foreach ($changes as $field => $change) {
                $model = ProfileFieldRegistry::apply($subject, $field, $change['new']);
                $subject->refresh()->loadMissing(['pilotProfile', 'dispatchProfile', 'atcProfile', 'avsecProfile']);

                $this->auditRecorder->recordFieldChange(
                    $subject,
                    $artisan,
                    'artisan_override',
                    'artisan_override',
                    $field,
                    $change['old'],
                    $change['new'],
                    $model,
                    null,
                    'ARTISAN OVERRIDE: '.trim($reason),
                );
            }
        });
    }
}
