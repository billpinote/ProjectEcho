<?php

namespace App\Providers;

use App\Auth\AuthAccountUserProvider;
use App\Http\Responses\SignedOutLogoutResponse;
use Filament\Auth\Http\Responses\Contracts\LogoutResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(LogoutResponse::class, SignedOutLogoutResponse::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Auth::provider('auth-account', function ($app, array $config) {
            return new AuthAccountUserProvider($app['hash'], $config['model']);
        });
    }
}
