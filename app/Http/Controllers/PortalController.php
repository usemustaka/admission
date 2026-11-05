<?php

namespace App\Http\Controllers;

use App\Models\Registrant;
use App\Support\RegistrantPresenter;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PortalController extends Controller
{
    /**
     * All the children of the signed-in parent, with where each registration stands.
     */
    public function __invoke(Request $request): Response
    {
        return Inertia::render('portal/home', [
            'registrants' => Registrant::query()
                ->forParent($request->user())
                ->with(['person', 'organization', 'wave'])
                ->latest()
                ->get()
                ->map(fn (Registrant $registrant): array => RegistrantPresenter::summary($registrant))
                ->values(),
        ]);
    }
}
