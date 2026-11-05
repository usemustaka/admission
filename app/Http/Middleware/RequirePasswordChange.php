<?php

namespace App\Http\Middleware;

use App\Support\PasswordChange;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Keeps an account that an admin flagged for a password change on the security page until it has chosen a new
 * password. Only what that page needs stays reachable.
 */
class RequirePasswordChange
{
    /**
     * Route names that stay open: the security page and its password form, confirming the password, two-factor
     * setup, email verification and signing out.
     *
     * @var list<string>
     */
    private const ALLOWED_ROUTES = [
        'security.edit',
        'user-password.update',
        'password.confirm',
        'password.confirm.store',
        'password.confirmation',
        'two-factor.*',
        'verification.*',
        'logout',
    ];

    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null || $request->routeIs(...self::ALLOWED_ROUTES) || ! PasswordChange::isRequiredFor($user)) {
            return $next($request);
        }

        return redirect()->route('security.edit');
    }
}
