<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\LogoutOtherSessionsRequest;
use App\Support\UserSessions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

class SessionController extends Controller
{
    /**
     * Sign the account out everywhere except on this device.
     */
    public function destroyOthers(LogoutOtherSessionsRequest $request): RedirectResponse
    {
        UserSessions::forgetOthers($request->user(), $request->session()->getId());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Semua perangkat lain sudah dikeluarkan.']);

        return back();
    }

    /**
     * Sign one other device out. Needs a recently confirmed password, which the route enforces.
     */
    public function destroy(Request $request, string $session): RedirectResponse
    {
        abort_unless(UserSessions::forget($request->user(), $session, $request->session()->getId()), 404);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Perangkat itu sudah dikeluarkan.']);

        return back();
    }
}
