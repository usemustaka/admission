<?php

namespace App\Http\Middleware;

use App\Support\SetupState;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Sends every page to the first-run setup until it has been completed.
 */
class RedirectToSetup
{
    public function handle(Request $request, Closure $next): Response
    {
        if (
            config('setup.enforce')
            && $request->isMethod('GET')
            && ! $request->routeIs('setup.*')
            && ! $request->is('up')
            && ! SetupState::isComplete()
        ) {
            return redirect()->route('setup.show');
        }

        return $next($request);
    }
}
