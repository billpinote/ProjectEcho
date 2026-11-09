<?php

namespace App\Http\Requests;

use App\Domain\FlightPlans\Services\AvsecVerificationService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AvsecClearanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return AvsecVerificationService::eligibleOfficer($this->user())
            && $this->user()->can('view', $this->route('flight'));
    }

    protected function prepareForValidation(): void
    {
        $this->merge(['reason' => is_string($this->reason) ? trim($this->reason) : $this->reason]);
    }

    public function rules(): array
    {
        return [
            'decision' => ['required', Rule::in(['grant', 'deny'])],
            'confirmation' => ['required', 'accepted'],
            'reason' => [Rule::requiredIf($this->input('decision') === 'deny'), 'nullable', 'string', 'max:255'],
            'reference' => ['nullable', 'string', 'max:255'],
            'reviewed_fingerprint' => ['required', 'string', 'size:64', 'regex:/^[a-f0-9]+$/'],
        ];
    }
}
