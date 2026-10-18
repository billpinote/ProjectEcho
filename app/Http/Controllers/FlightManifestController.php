<?php

namespace App\Http\Controllers;

use App\Domain\FlightPlans\Services\FlightManifestService;
use App\Domain\FlightPlans\Support\FlightManifestData;
use App\Domain\Users\Enums\UserRole;
use App\Filament\Panels\Pilot\Pages\EditFlightManifest;
use App\Filament\Panels\Pilot\Pages\ListFlightManifests;
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

    public function index(Request $request): View|RedirectResponse
    {
        $user = $request->user();
        if ($user !== null) {
            abort_unless($user->canViewFlightPlans(), 403);
        }

        if ($user?->isPilot()) {
            return redirect(ListFlightManifests::getUrl(panel: 'pilot'));
        }

        if ($user?->isDispatch() || $user?->isOperatorStaff()) {
            return redirect(\App\Filament\Panels\Dispatch\Pages\ListFlightManifests::getUrl(panel: 'dispatch'));
        }

        if ($user?->role === UserRole::Atmo) {
            abort(403);
        }

        return view('flightplan.manifest-index', [
            'flights' => $user === null ? null : Flight::query()->visibleTo($user)
                ->with('manifest')->orderByDesc('id')->paginate(20),
        ]);
    }

    public function edit(Request $request, Flight $flight): View|RedirectResponse
    {
        if ($request->user()->role === UserRole::Atmo) {
            $this->manifests->view($flight, $request->user());

            return redirect()->route('flights.manifest.pdf', $flight);
        }

        $data = $this->formData($request, $flight);
        if ($request->user()->isPilot()) {
            return redirect(EditFlightManifest::getUrl(['flight' => $flight], panel: 'pilot'));
        }

        if ($request->user()->isDispatch() || $request->user()->isOperatorStaff()) {
            return redirect(\App\Filament\Panels\Dispatch\Pages\EditFlightManifest::getUrl(['flight' => $flight], panel: 'dispatch'));
        }

        return view('flightplan.manifest', $data);
    }

    /** @return array<string, mixed> */
    public function formData(Request $request, Flight $flight): array
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

        return [
            'flight' => $flight, 'manifest' => $manifest, 'editable' => $editable,
            'values' => $values, 'flightData' => $this->data->fromFlight($flight),
            'complete' => $complete, 'completionMessage' => $completionMessage,
        ];
    }

    public function update(SaveFlightManifestRequest $request, Flight $flight): RedirectResponse
    {
        $this->manifests->save($flight, $request->user(), $request->validated());

        $url = $request->user()->isPilot()
            ? EditFlightManifest::getUrl(['flight' => $flight], panel: 'pilot')
            : route('flights.manifest.edit', $flight);

        if ($request->user()->isDispatch() || $request->user()->isOperatorStaff()) {
            $url = \App\Filament\Panels\Dispatch\Pages\EditFlightManifest::getUrl(['flight' => $flight], panel: 'dispatch');
        }

        return redirect($url)->with('status', 'Manifest saved.');
    }
}
