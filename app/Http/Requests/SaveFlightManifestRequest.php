<?php

namespace App\Http\Requests;

use App\Domain\FlightPlans\Support\FlightManifestData;
use App\Models\FlightManifest;
use Illuminate\Foundation\Http\FormRequest;

class SaveFlightManifestRequest extends FormRequest
{
    public function authorize(): bool
    {
        $flight = $this->route('flight');
        $manifest = $flight->manifest;

        return $this->user()?->can($manifest === null ? 'create' : 'update', $manifest ?? [FlightManifest::class, $flight]) ?? false;
    }

    protected function prepareForValidation(): void
    {
        if (! $this->has('persons')) {
            $this->merge(['persons' => []]);
        }
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return app(FlightManifestData::class)->rules();
    }
}
