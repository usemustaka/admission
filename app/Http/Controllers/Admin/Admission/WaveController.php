<?php

namespace App\Http\Controllers\Admin\Admission;

use App\Enums\Ability;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Admission\SaveWaveRequest;
use App\Models\Wave;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class WaveController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Wave::class);

        return Inertia::render('admin/waves/index', [
            'waves' => Wave::query()
                ->withCount('rulesets')
                ->orderByDesc('is_open')
                ->orderByDesc('id')
                ->get()
                ->map(fn (Wave $wave): array => [
                    'id' => $wave->id,
                    'name' => $wave->name,
                    'starts_on' => $wave->starts_on?->toDateString(),
                    'ends_on' => $wave->ends_on?->toDateString(),
                    'is_open' => $wave->is_open,
                    'is_accepting' => Wave::query()->open()->whereKey($wave->id)->exists(),
                    'rulesets_count' => $wave->rulesets_count,
                ]),
            'can' => ['manage' => $request->user()->can(Ability::WaveManage->value)],
        ]);
    }

    public function store(SaveWaveRequest $request): RedirectResponse
    {
        Wave::create($request->validated());

        $this->flash('Gelombang baru sudah dibuat.');

        return to_route('admin.waves.index');
    }

    public function update(SaveWaveRequest $request, Wave $wave): RedirectResponse
    {
        $wave->update($request->validated());

        $this->flash('Gelombang sudah diperbarui.');

        return to_route('admin.waves.index');
    }

    private function flash(string $message): void
    {
        Inertia::flash('toast', ['type' => 'success', 'message' => $message]);
    }
}
