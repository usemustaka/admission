<?php

namespace App\Http\Controllers\Portal;

use App\Actions\Admission\SubmitRegistrant;
use App\Http\Controllers\Controller;
use App\Models\Registrant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

class ChildSubmissionController extends Controller
{
    /**
     * The parent sends the data to the committee.
     */
    public function store(Request $request, Registrant $registrant, SubmitRegistrant $submitRegistrant): RedirectResponse
    {
        Gate::authorize('submit', $registrant);

        $submitRegistrant->handle($registrant, $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Datanya sudah dikirim. Panitia akan memeriksanya.']);

        return to_route('portal.children.show', $registrant);
    }
}
