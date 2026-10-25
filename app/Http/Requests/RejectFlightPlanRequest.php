<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class RejectFlightPlanRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null && $user->is_active && $user->canReviewFlightPlans() && $user->can('reject', $this->route('flight'));
    }

    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        return ['rejection_reason' => ['required', 'string', 'max:255']];
    }
}
