<?php

namespace App\Http\Middleware;

use Closure;
use Filament\Facades\Filament;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RedirectExpiredPanelLogout
{
    public function handle(Request $request, Closure $next): Response
    {
        $panel = Filament::getCurrentPanel();

        // A stale logout form must still lead home after its authenticated session expires.
        if ($panel !== null && $request->isMethod('POST')
            && $request->routeIs('filament.'.$panel->getId().'.auth.logout')
            && Filament::auth()->guest()) {
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('gateway');
        }

        return $next($request);
    }
}
