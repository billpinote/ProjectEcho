<?php

namespace App\Http\Controllers;

use App\Domain\FlightPlans\Services\FlightManifestService;
use App\Domain\FlightPlans\Support\FlightManifestData;
use App\Http\Requests\SaveFlightManifestRequest;
use App\Models\Flight;
use App\Models\FlightManifest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class FlightManifestController extends Controller
{
    public function __construct(private FlightManifestService $manifests, private FlightManifestData $data) {}

    public function index(Request $request): View
    {
        $user = $request->user();
        if ($user !== null) {
            abort_unless($user->canViewFlightPlans(), 403);
        }

        return view('flightplan.manifest-index', [
            'flights' => $user === null ? null : Flight::query()->visibleTo($user)
                ->with('manifest')->orderByDesc('id')->paginate(20),
        ]);
    }

    public function edit(Request $request, Flight $flight): View
    {
        $manifest = $flight->manifest()->with('persons')->first();
        Gate::authorize($manifest === null ? 'create' : 'view', $manifest ?? [FlightManifest::class, $flight]);
        $editable = $request->user()->can($manifest === null ? 'create' : 'update', $manifest ?? [FlightManifest::class, $flight]);
        $values = $manifest?->toArray() ?? $this->data->defaults($flight);
        if ($manifest !== null) {
            $values['coa_expiry_date'] = $manifest->coa_expiry_date?->format('Y-m-d');
        }
        $completionMessage = 'Save your manifest to check whether it is ready for ATMO acceptance.';
        $complete = false;
        if ($manifest !== null) {
            try {
                $this->manifests->assertComplete($flight);
                $complete = true;
                $completionMessage = 'Manifest complete and ready for ATMO review. PIC authorization is checked separately.';
            } catch (ValidationException $exception) {
                $completionMessage = implode(' ', $exception->errors()['manifest'] ?? []);
            }
        }

        return view('flightplan.manifest', [
            'flight' => $flight, 'manifest' => $manifest, 'editable' => $editable,
            'values' => $values, 'flightData' => $this->data->fromFlight($flight),
            'complete' => $complete, 'completionMessage' => $completionMessage,
        ]);
    }

    public function update(SaveFlightManifestRequest $request, Flight $flight): RedirectResponse
    {
        $this->manifests->save($flight, $request->user(), $request->validated());

        return to_route('flights.manifest.edit', $flight)->with('status', 'Manifest saved.');
    }
}
