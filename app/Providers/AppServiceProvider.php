<?php

namespace App\Providers;

use App\Auth\AuthAccountUserProvider;
use App\Domain\FlightPlans\Services\FilingAuthorizationService;
use App\Domain\FlightPlans\Support\FilingDocumentRegistry;
use App\Http\Responses\SignedOutLogoutResponse;
use Filament\Auth\Http\Responses\Contracts\LogoutResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\ServiceProvider;
use Illuminate\View\View;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->scoped(FilingAuthorizationService::class);
        $this->app->singleton(FilingDocumentRegistry::class);
        $this->app->bind(LogoutResponse::class, SignedOutLogoutResponse::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        \Illuminate\Support\Facades\View::composer('flightplan.pdf', function (View $view): void {
            $data = $view->getData();
            $actor = Auth::user();
            $flight = $data['flight'];
            $view->with('hasApprovedPilotSignature', isset($data['filingPackage']) && $actor?->approvedSignature()->where('status', 'approved')->exists());
            if (! array_key_exists('pilotSignatureDataUrl', $data)) {
                $bytes = $actor?->is_active && $actor->can('view', $flight)
                    ? app(FilingAuthorizationService::class)->signatureBytes($flight)
                    : null;
                $view->with('pilotSignatureDataUrl', $bytes === null ? null : 'data:image/png;base64,'.base64_encode($bytes));
            }
            if (($url = $view->getData()['pilotSignatureDataUrl'] ?? null) !== null) {
                $dimensions = getimagesizefromstring(base64_decode(substr($url, strlen('data:image/png;base64,'))));
                $scale = min(145 / $dimensions[0], 24 / $dimensions[1]);
                $view->with('pilotSignatureWidth', $dimensions[0] * $scale);
                $view->with('pilotSignatureHeight', $dimensions[1] * $scale);
            }
        });
        Auth::provider('auth-account', function ($app, array $config) {
            return new AuthAccountUserProvider($app['hash'], $config['model']);
        });
    }
}
