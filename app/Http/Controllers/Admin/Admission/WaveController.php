<?php

namespace App\Http\Controllers\Admin\Admission;

use App\Enums\Ability;
use App\Enums\RegistrantStatus;
use App\Enums\WaveState;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Admission\SaveWaveRequest;
use App\Models\Registrant;
use App\Models\Wave;
use Carbon\CarbonInterface;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class WaveController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Wave::class);

        $today = today();

        $registrants = Registrant::query()
            ->selectRaw('wave_id, status, count(*) as total')
            ->groupBy('wave_id', 'status')
            ->get()
            ->groupBy('wave_id');

        return Inertia::render('admin/waves/index', [
            'waves' => Wave::query()
                ->withCount('rulesets')
                ->get()
                ->map(fn (Wave $wave): array => $this->summary($wave, $today, $registrants->get($wave->id, collect())))
                ->sortBy([
                    fn (array $a, array $b): int => $this->stateOrder($a['state']) <=> $this->stateOrder($b['state']),
                    fn (array $a, array $b): int => $b['is_open'] <=> $a['is_open'],
                    fn (array $a, array $b): int => $b['id'] <=> $a['id'],
                ])
                ->values(),
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

    /**
     * @param  Collection<int, Registrant>  $statusCounts  one row per status, with the count in `total`
     * @return array<string, mixed>
     */
    private function summary(Wave $wave, CarbonInterface $today, Collection $statusCounts): array
    {
        $state = $wave->state($today);
        $total = $wave->starts_on !== null && $wave->ends_on !== null ? (int) $wave->starts_on->diffInDays($wave->ends_on) + 1 : null;
        $elapsed = $wave->starts_on !== null ? (int) $wave->starts_on->diffInDays($today, false) + 1 : null;

        return [
            'id' => $wave->id,
            'name' => $wave->name,
            'starts_on' => $wave->starts_on?->toDateString(),
            'ends_on' => $wave->ends_on?->toDateString(),
            'is_open' => $wave->is_open,
            'is_accepting' => $wave->is_open && $state === WaveState::Ongoing,
            'state' => $state->value,
            'state_label' => $state->label(),
            'days_left' => $state === WaveState::Ongoing && $wave->ends_on !== null ? (int) $today->diffInDays($wave->ends_on) : null,
            'days_until_start' => $state === WaveState::Upcoming ? (int) $today->diffInDays($wave->starts_on) : null,
            'days_since_end' => $state === WaveState::Ended ? (int) $wave->ends_on->diffInDays($today) : null,
            'progress' => $total === null ? null : (int) round(max(0, min($total, $elapsed)) / $total * 100),
            'rulesets_count' => $wave->rulesets_count,
            'registrants' => [
                'total' => (int) $statusCounts->sum('total'),
                'by_status' => collect(RegistrantStatus::cases())
                    ->map(fn (RegistrantStatus $status): array => [
                        'status' => $status->value,
                        'label' => $status->label(),
                        'count' => (int) $statusCounts->firstWhere('status', $status)?->total,
                    ])
                    ->filter(fn (array $row): bool => $row['count'] > 0)
                    ->values(),
            ],
        ];
    }

    private function stateOrder(string $state): int
    {
        return match ($state) {
            WaveState::Ongoing->value => 0,
            WaveState::Upcoming->value => 1,
            default => 2,
        };
    }

    private function flash(string $message): void
    {
        Inertia::flash('toast', ['type' => 'success', 'message' => $message]);
    }
}
