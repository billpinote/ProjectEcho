<?php

namespace App\Domain\FlightPlans\Services;

use App\Domain\FlightPlans\Support\FlightManifestData;
use App\Models\Flight;
use App\Models\FlightManifest;
use App\Models\ManifestPerson;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class FlightManifestService
{
    public function __construct(private FlightManifestData $data) {}

    public function view(Flight $flight, User $actor): FlightManifest
    {
        $manifest = $flight->manifest()->firstOrFail();
        Gate::forUser($actor)->authorize('view', $manifest);

        return $manifest->load('persons');
    }

    /** @param array<string, mixed> $attributes */
    public function save(Flight $flight, User $actor, array $attributes): FlightManifest
    {
        return DB::transaction(function () use ($flight, $actor, $attributes): FlightManifest {
            $flight = Flight::query()->lockForUpdate()->findOrFail($flight->getKey());
            $manifest = $flight->manifest()->first();
            Gate::forUser($actor)->authorize($manifest === null ? 'create' : 'update', $manifest ?? [FlightManifest::class, $flight]);
            $attributes = $this->data->validate($attributes);

            if ($manifest === null) {
                $attributes = array_replace($this->data->defaults($flight), $attributes);
                $attributes['persons'] = $this->withDefaultPic($flight, $attributes['persons'] ?? []);
            }
            $validated = $this->data->validate($attributes);
            $persons = $validated['persons'] ?? null;
            unset($validated['persons']);

            if ($manifest === null) {
                $manifest = new FlightManifest($validated);
                $manifest->flight()->associate($flight);
                $manifest->creator()->associate($actor);
            } else {
                $manifest->fill($validated);
            }
            $manifest->save();

            if ($persons !== null) {
                $manifest->persons()->get()->each(fn (ManifestPerson $person) => $person->delete());
                foreach ($persons as $index => $person) {
                    $manifest->persons()->create([...$person, 'sequence_number' => $index + 1]);
                }
            }

            return $manifest->load('persons');
        });
    }

    public function delete(Flight $flight, User $actor): void
    {
        DB::transaction(function () use ($flight, $actor): void {
            $flight = Flight::query()->lockForUpdate()->findOrFail($flight->getKey());
            $manifest = $flight->manifest()->firstOrFail();
            Gate::forUser($actor)->authorize('delete', $manifest);
            $manifest->delete();
        });
    }

    public function assertComplete(Flight $flight): void
    {
        $manifest = $flight->manifest()->with('persons')->first();
        if ($manifest === null) {
            throw ValidationException::withMessages(['manifest' => 'Prepare and complete the linked flight manifest before ATMO acceptance.']);
        }

        try {
            $attributes = $manifest->toArray();
            $attributes['coa_expiry_date'] = $manifest->coa_expiry_date?->format('Y-m-d');
            $attributes['persons'] = $manifest->persons->map(fn (ManifestPerson $person): array => $person->only(['full_name', 'nationality', 'classification']))->all();
            $this->data->validate($attributes, complete: true);
            Validator::make($this->data->fromFlight($flight), [
                'aircraft_identification' => ['required', 'string'],
                'type_of_aircraft' => ['required', 'string'],
                'date_of_flight' => ['required', 'date'],
                'pilot_in_command' => ['required', 'string'],
                'pilot_license_no' => ['required', 'string'],
                'license_expiry_date' => ['required', 'date'],
            ])->validate();
        } catch (ValidationException $exception) {
            throw ValidationException::withMessages([
                'manifest' => 'Complete the linked flight manifest before ATMO acceptance: '.implode(' ', $exception->validator->errors()->all()),
            ]);
        }

        $pic = $manifest->persons->first();
        if ($pic?->classification !== 'pic'
            || $this->personName($pic->full_name) !== $this->personName($flight->pilot_in_command)
            || $manifest->persons->where('classification', 'pic')->count() !== 1) {
            throw ValidationException::withMessages(['manifest' => 'List the current flight-plan PIC once, as the first manifest person.']);
        }
        if (! $this->comparison($manifest)['matches']) {
            throw ValidationException::withMessages(['manifest' => 'The manifest person count must match the flight plan persons on board. Review both documents; neither count was changed.']);
        }
        if ((float) $manifest->total_cargo_weight > 0 && (blank($manifest->cargo_type) || blank($manifest->cargo_quantity))) {
            throw ValidationException::withMessages(['manifest' => 'Enter the cargo type and quantity when cargo weight is greater than zero.']);
        }
    }

    /** @return array{manifest_count: int, flight_plan_count: ?int, matches: bool} */
    public function comparison(FlightManifest $manifest): array
    {
        return $this->data->comparePersons($manifest->persons()->count(), $manifest->flight->persons_on_board);
    }

    /**
     * @param  list<array<string, mixed>>  $persons
     * @return list<array<string, mixed>>
     */
    private function withDefaultPic(Flight $flight, array $persons): array
    {
        if (blank($flight->pilot_in_command)) {
            return $persons;
        }
        $pic = ['full_name' => $flight->pilot_in_command, 'nationality' => null, 'classification' => 'pic'];
        $others = [];
        foreach ($persons as $person) {
            if ($this->personName($person['full_name'] ?? '') === $this->personName($flight->pilot_in_command)) {
                $pic = array_replace($pic, $person, ['classification' => 'pic']);
            } else {
                $others[] = $person;
            }
        }

        return [$pic, ...$others];
    }

    private function personName(?string $name): string
    {
        return Str::upper(Str::squish((string) $name));
    }
}
