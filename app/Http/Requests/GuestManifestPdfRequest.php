<?php

namespace App\Http\Requests;

use App\Domain\FlightPlans\Support\FlightManifestData;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class GuestManifestPdfRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return array_replace(app(FlightManifestData::class)->rules(), [
            'aircraft_identification' => ['required', 'string', 'max:255'],
            'type_of_aircraft' => ['required', 'string', 'max:255'],
            'date_of_flight' => ['required', 'date_format:Y-m-d'],
            'pilot_in_command' => ['required', 'string', 'max:255'],
            'pilot_license_no' => ['nullable', 'string', 'max:255'],
            'pilot_ratings' => ['nullable', 'string', 'max:255'],
            'license_expiry_date' => ['nullable', 'date_format:Y-m-d'],
            'persons' => ['required', 'array', 'list', 'min:1', 'max:250'],
            'persons.*' => ['array:full_name,nationality,classification'],
            'remarks' => ['prohibited'],
        ]);
    }

    /** @return list<\Closure> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }
            $persons = $this->validated('persons');
            $normalize = fn (string $name): string => mb_strtoupper(preg_replace('/\s+/u', ' ', trim($name)));
            $picName = $normalize($this->validated('pilot_in_command'));
            $picCount = collect($persons)->where('classification', 'pic')->count();
            $nameCount = collect($persons)->filter(fn (array $person): bool => $normalize($person['full_name']) === $picName)->count();
            if ($picCount !== 1 || $nameCount !== 1 || $persons[0]['classification'] !== 'pic' || $normalize($persons[0]['full_name']) !== $picName) {
                $validator->errors()->add('persons', 'List the named PIC once, as the first person with classification pic.');
            }
        }];
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(response()->json(['message' => 'Review the manifest fields.', 'errors' => $validator->errors()], 422)->header('Cache-Control', 'no-store'));
    }
}
