<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class PreparePilotSignatureRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->is_active && $this->user()?->isPilot();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'source' => ['required', 'in:draw,upload'],
            'image' => ['required', 'file', 'mimetypes:image/png,image/jpeg', 'max:2048', 'dimensions:min_width=32,min_height=16,max_width=4096,max_height=2048'],
        ];
    }
}
