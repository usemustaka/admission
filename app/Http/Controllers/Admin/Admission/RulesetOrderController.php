<?php

namespace App\Http\Controllers\Admin\Admission;

use App\Actions\Admission\MoveRuleset;
use App\Http\Controllers\Controller;
use App\Models\Ruleset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class RulesetOrderController extends Controller
{
    /**
     * Move a ruleset up or down the priority order.
     */
    public function __invoke(Request $request, Ruleset $ruleset, MoveRuleset $moveRuleset): RedirectResponse
    {
        Gate::authorize('update', $ruleset);

        $validated = $request->validate(['direction' => ['required', Rule::in(['up', 'down'])]]);

        $moveRuleset->handle($ruleset, $validated['direction']);

        return back();
    }
}
