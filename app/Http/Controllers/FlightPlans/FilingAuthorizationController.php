<?php

namespace App\Http\Controllers\FlightPlans;

use App\Auth\AuthenticationEvidence;
use App\Domain\FlightPlans\Services\FilingAuthorizationService;
use App\Domain\FlightPlans\Services\PicAuthorizationService;
use App\Domain\FlightPlans\Support\FilingDocumentRegistry;
use App\Domain\FlightPlans\Support\FlightAccess;
use App\Domain\Users\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\FilingAuthorization;
use App\Models\Flight;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class FilingAuthorizationController extends Controller
{
    public function __construct(private PicAuthorizationService $pic) {}

    public function reviewSelf(Request $request, Flight $flight): RedirectResponse
    {
        $this->guardSelf($request, $flight);
        $token = $this->pic->createAuthorizationHandoff($flight, selfPrepared: true);

        return redirect()->route('flightplan.pic-authorization.preview', ['token' => $token]);
    }

    public function authorizeSelf(Request $request, string $token): RedirectResponse
    {
        $flight = $this->pic->resolveAuthorizationHandoff($token);
        abort_unless($flight !== null, 403);
        $this->guardSelf($request, $flight);
        $data = $request->validate(['apply_signature' => ['required', 'boolean']]);
        $this->pic->authorizeFromHandoff($token, $request->user(), AuthenticationEvidence::fromSession(auth()->guard()),
            applySignature: (bool) $data['apply_signature'], selfPrepared: true);

        return redirect()->route('flights.view', $flight);
    }

    public function document(Request $request, FilingAuthorization $package, string $type): Response
    {
        $flight = $package->flight;
        $this->guardPackage($request, $package);
        $documents = $package->status === 'authorized' ? $package->authorized_documents : $package->documents;
        abort_unless(isset($documents[$type]), 404);
        abort_unless($package->signed_document_types === null || in_array($type, $package->signed_document_types, true), 404);

        $preserved = $package->pdf_documents[$type] ?? null;
        if ($preserved !== null) {
            $bytes = base64_decode($preserved['bytes'], true);
            abort_unless(is_string($bytes) && hash_equals($preserved['sha256'], hash('sha256', $bytes)), 409);
        } else {
            $bytes = app(FilingDocumentRegistry::class)->render($type, $documents, null);
        }

        return response($bytes, 200, [
            'Content-Type' => 'application/pdf', 'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
            'Content-Disposition' => 'inline; filename="filing-'.$package->id.'-'.$type.'.pdf"',
        ]);
    }

    public function history(Request $request, Flight $flight): View
    {
        $actor = $request->user();
        abort_unless($actor?->is_active && $actor->canViewFlightPlans(), 403);
        abort_if($actor->role === UserRole::Atmo && ! $actor->can('view', $flight), 403);
        $query = $flight->filingAuthorizations()->latest('id');
        if (! $actor->can('view', $flight)) {
            abort_unless(FlightAccess::operatorMatches($actor, $flight) && (clone $query)->where('pic_user_id', $actor->id)->exists(), 403);
            $query->where('pic_user_id', $actor->id);
        }

        return view('flightplan.filing-history', [
            'flight' => $flight, 'packages' => $query->get(),
            'currentPackageId' => $flight->isPicAuthorizationCurrent() ? $flight->filingAuthorizations()->latest('id')->value('id') : null,
            'currentDocumentPackages' => collect(['flight_plan', 'passenger_cargo_manifest'])->mapWithKeys(fn ($type) => [
                $type => app(FilingAuthorizationService::class)->currentForDocument($flight, $type)?->id,
            ])->all(),
        ]);
    }

    private function guardPackage(Request $request, FilingAuthorization $package): void
    {
        $actor = $request->user();
        abort_unless($actor?->is_active && $actor->canViewFlightPlans(), 403);
        abort_if($actor->role === UserRole::Atmo && ! $actor->can('view', $package->flight), 403);
        if ($package->status === 'pending') {
            try {
                $this->pic->guardEligiblePic($actor);
            } catch (ValidationException) {
                abort(403);
            }
        }
        if ($actor->can('view', $package->flight)
            || ((int) $package->pic_user_id === (int) $actor->id && FlightAccess::operatorMatches($actor, $package->flight))) {
            return;
        }
        $handoff = $this->pic->resolveAuthorizationHandoff((string) $request->query('token', ''));
        abort_unless($package->status === 'pending' && $handoff?->id === $package->flight_id
            && $handoff->filingAuthorizations()->latest('id')->value('id') === $package->id
            && FlightAccess::canAccessPicAuthorization($actor, $handoff), 403);
        $this->pic->guardEligiblePic($actor);
    }

    private function guardSelf(Request $request, Flight $flight): void
    {
        $actor = $request->user();
        abort_unless($actor?->is_active && $actor->isPilot() && $actor->can('view', $flight)
            && (int) $flight->prepared_by_user_id === (int) $actor->id
            && (int) $flight->pilot_in_command_user_id === (int) $actor->id, 403);
    }
}
