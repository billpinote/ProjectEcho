<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class LookupFlightPlanQrRequest extends FormRequest
{
    public function authorize(): bool
    {
        return ! ($this->user()?->isPilot() ?? false);
    }

    /** @return array<string, array<int, string>> */
    public function rules(): array
    {
        return ['payload' => ['required', 'string', 'max:20000']];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return ['payload.required' => $this->routeIs('flightplan.edit-from-qr')
            ? 'A QR payload is required.' : 'Paste or scan a QR payload first.'];
    }
}
