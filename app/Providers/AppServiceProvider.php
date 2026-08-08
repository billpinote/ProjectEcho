<?php

namespace App\Providers;

use App\Auth\AuthAccountUserProvider;
use Illuminate\Auth\AuthManager;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
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
