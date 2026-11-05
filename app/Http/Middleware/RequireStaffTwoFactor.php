<?php

namespace App\Http\Middleware;

use App\Support\StaffTwoFactor;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Sends staff without confirmed two-factor authentication to the security page while the school requires it. Put
 * it on the admin routes only: the settings pages, two-factor routes and logout sit outside them and stay open.
 */
class RequireStaffTwoFactor
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user !== null && StaffTwoFactor::mustSetUp($user)) {
            return redirect()->route('security.edit');
        }

        return $next($request);
    }
}
