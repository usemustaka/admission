<?php

namespace App\Http\Controllers;

use App\Enums\Ability;
use App\Models\Organization;
use App\Models\Person;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    /**
     * Staff land on the admin dashboard, everyone else on the parent portal.
     */
    public function __invoke(Request $request): Response|RedirectResponse
    {
        if (! $request->user()->can(Ability::AdminAccess->value)) {
            return to_route('portal');
        }

        return Inertia::render('admin/dashboard', [
            'stats' => [
                'people' => Person::count(),
                'organizations' => Organization::active()->count(),
                'users' => User::count(),
            ],
        ]);
    }
}
