<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class LimitGuestManifestRequest
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->is('manifest/guest/pdf') && $request->isMethod('POST')) {
            abort_if((int) $request->server('CONTENT_LENGTH', 0) > 262144 || strlen($request->getContent()) > 262144, 413);
        }

        return $next($request);
    }
}
