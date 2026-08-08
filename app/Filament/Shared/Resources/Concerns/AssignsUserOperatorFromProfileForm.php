<?php

namespace App\Filament\Shared\Resources\Concerns;

trait AssignsUserOperatorFromProfileForm
{
    protected ?int $profileFormOperatorId = null;

    protected bool $profileFormOperatorWasSubmitted = false;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function captureProfileFormOperator(array $data): array
    {
        if (! array_key_exists('operator_id', $data)) {
            return $data;
        }

        $this->profileFormOperatorWasSubmitted = true;
        $this->profileFormOperatorId = filled($data['operator_id'] ?? null)
            ? (int) $data['operator_id']
            : null;

        unset($data['operator_id']);

        return $data;
    }

    protected function saveProfileFormOperator(): void
    {
        if (! $this->profileFormOperatorWasSubmitted) {
            return;
        }

        $this->record?->user?->forceFill([
            'operator_id' => $this->profileFormOperatorId,
        ])->save();
    }
}
