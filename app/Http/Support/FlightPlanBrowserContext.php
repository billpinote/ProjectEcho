<?php

namespace App\Http\Support;

use App\Models\Flight;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class FlightPlanBrowserContext
{
    public function storeScannedFlightPlanPreview(Request $request, array $preview): string
    {
        $previewToken = (string) Str::uuid();
        $previews = $request->session()->get('scanned_flight_plan_previews', []);

        if (! is_array($previews)) {
            $previews = [];
        }

        $previews[$previewToken] = $preview;

        if (count($previews) > 10) {
            $previews = array_slice($previews, -10, null, true);
        }

        $request->session()->put('scanned_flight_plan_previews', $previews);

        return $previewToken;
    }

    public function ensureReviewerAccess(): void
    {
        $user = Auth::user();

        abort_unless(
            $user
            && $user->is_active
            && $user->canReviewFlightPlans(),
            403
        );
    }

    public function ensurePilotCannotScanQr(): void
    {
        $user = Auth::user();

        abort_if($user?->isPilot(), 403);
    }

    public function ensureFlightUserAccess(): void
    {
        $user = Auth::user();

        abort_unless(
            $user
            && $user->is_active
            && $user->canViewFlightPlans(),
            403
        );
    }

    public function ensureFlightAssetAccess(Request $request, Flight $flight): void
    {
        if (Auth::check()) {
            $this->ensureFlightUserAccess();
            abort_unless(Auth::user()?->can('view', $flight) ?? false, 403);

            return;
        }

        abort_unless($this->sessionCanAccessFlight($request, $flight), 403);
    }

    public function grantSessionAccessToFlight(Request $request, Flight $flight): void
    {
        $allowedFlightIds = $request->session()->get('public_flight_access', []);

        if (! is_array($allowedFlightIds)) {
            $allowedFlightIds = [];
        }

        $allowedFlightIds[] = $flight->getKey();

        $request->session()->put(
            'public_flight_access',
            array_values(array_unique(array_map('intval', $allowedFlightIds)))
        );
    }

    public function sessionCanAccessFlight(Request $request, Flight $flight): bool
    {
        $allowedFlightIds = $request->session()->get('public_flight_access', []);

        if (! is_array($allowedFlightIds)) {
            return false;
        }

        return in_array((int) $flight->getKey(), array_map('intval', $allowedFlightIds), true);
    }

    public function resolveAtcWiresign(): string
    {
        $user = Auth::user();

        return (string) ($user?->wiresign ?: $user?->name ?: '');
    }

    public function picAuthorizationScannerUrl(): string
    {
        return Auth::user()?->isPilot()
            ? route('filament.pilot.pages.scan-authorization-qr')
            : route('filament.dispatch.pages.scan-authorization-qr');
    }

    public function roleAwarePanelUrl(): string
    {
        $panel = match (Auth::user()?->role?->value) {
            'ATMO' => 'atmo',
            'PILOT' => 'pilot',
            'DISPATCH', 'OPERATOR_STAFF' => 'dispatch',
            'AVSEC' => 'avsec',
            'ATSHQ' => 'ats',
            'ARTISAN' => 'artisan',
            default => 'admin',
        };

        return url('/'.$panel);
    }
}
