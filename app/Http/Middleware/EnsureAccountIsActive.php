<?php

namespace App\Http\Middleware;

use App\Enums\DefaultRole;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Signs out a deactivated account that still holds a session, for example one created before it was deactivated.
 */
class EnsureAccountIsActive
{
    public const MESSAGE = 'Akun ini sudah dinonaktifkan. Hubungi admin.';

    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null || ! $user->hasRole(DefaultRole::Inactive->value)) {
            return $next($request);
        }

        Auth::guard()->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->withErrors(['email' => self::MESSAGE]);
    }
}
