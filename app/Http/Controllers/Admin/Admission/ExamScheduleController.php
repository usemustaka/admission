<?php

namespace App\Http\Controllers\Admin\Admission;

use App\Actions\Admission\SyncExamSchedule;
use App\Enums\Ability;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Admission\SaveExamScheduleRequest;
use App\Models\ExamDay;
use App\Models\ExamSession;
use App\Models\Organization;
use App\Models\Registrant;
use App\Models\Wave;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The exam schedule: dates and sessions per organization and wave (either may be "all"), shared by every
 * registration that sits the exam there, whatever its ruleset.
 */
class ExamScheduleController extends Controller
{
    public function edit(Request $request): Response
    {
        Gate::authorize(Ability::ExamManage->value);

        $organizationId = $request->integer('lembaga') ?: null;
        $waveId = $request->integer('gelombang') ?: null;

        $organizationId = $organizationId !== null && Organization::query()->whereKey($organizationId)->exists() ? $organizationId : null;
        $waveId = $waveId !== null && Wave::query()->whereKey($waveId)->exists() ? $waveId : null;

        $days = ExamDay::query()
            ->inScope($organizationId, $waveId)
            ->with(['sessions' => fn ($sessions) => $sessions->withCount('registrants')])
            ->orderBy('date')
            ->get();

        return Inertia::render('admin/exams/schedule', [
            'scope' => ['organization_id' => $organizationId, 'wave_id' => $waveId],
            'days' => $days->map(fn (ExamDay $day): array => [
                'id' => $day->id,
                'date' => $day->date->toDateString(),
                'sessions' => $day->sessions->map(fn (ExamSession $session): array => [
                    'id' => $session->id,
                    'label' => $session->label,
                    'start_time' => substr($session->start_time, 0, 5),
                    'end_time' => substr($session->end_time, 0, 5),
                    'quota' => $session->quota,
                    'taken' => $session->registrants_count,
                ])->values(),
            ])->values(),
            'schedules' => $this->schedules(),
            'audience' => $this->audience($organizationId, $waveId),
            'options' => [
                'organizations' => Organization::query()->where('is_active', true)->orWhere('id', $organizationId)->orderBy('sort_order')->orderBy('name')->get()
                    ->map(fn (Organization $organization): array => ['value' => $organization->id, 'label' => $organization->name])->values(),
                'waves' => Wave::query()->orderByDesc('starts_on')->get()
                    ->map(fn (Wave $wave): array => ['value' => $wave->id, 'label' => $wave->name])->values(),
            ],
        ]);
    }

    public function update(SaveExamScheduleRequest $request, SyncExamSchedule $syncExamSchedule): RedirectResponse
    {
        $organizationId = $request->integer('organization_id') ?: null;
        $waveId = $request->integer('wave_id') ?: null;

        /** @var list<array{id?: int|null, date: string, sessions: list<array{id?: int|null, label: string, start_time: string, end_time: string, quota: int}>}> $days */
        $days = $request->validated('days');

        $syncExamSchedule->handle($organizationId, $waveId, $days);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Jadwal ujian sudah disimpan.']);

        return to_route('admin.exams.schedule.edit', array_filter(['lembaga' => $organizationId, 'gelombang' => $waveId]));
    }

    /**
     * The schedules that exist, one per organization and wave, so the admin can jump between them.
     *
     * @return list<array{organization_id: int|null, wave_id: int|null, label: string, days: int, sessions: int}>
     */
    private function schedules(): array
    {
        return ExamDay::query()
            ->with(['organization', 'wave'])
            ->withCount('sessions')
            ->orderBy('date')
            ->get()
            ->groupBy(fn (ExamDay $day): string => ($day->organization_id ?? '-').'|'.($day->wave_id ?? '-'))
            ->map(fn ($group): array => [
                'organization_id' => $group->first()->organization_id,
                'wave_id' => $group->first()->wave_id,
                'label' => $group->first()->scopeLabel(),
                'days' => $group->count(),
                'sessions' => (int) $group->sum('sessions_count'),
            ])
            ->sortBy('label')
            ->values()
            ->all();
    }

    /**
     * How many registrations the schedule being edited is open to: those of this organization and wave, or of
     * all of them when the scope is left empty.
     *
     * @return array{label: string, registrants: int}
     */
    private function audience(?int $organizationId, ?int $waveId): array
    {
        return [
            'label' => (Organization::query()->find($organizationId)?->name ?? 'Semua lembaga').' · '.(Wave::query()->find($waveId)?->name ?? 'Semua gelombang'),
            'registrants' => Registrant::query()
                ->when($organizationId, fn ($query) => $query->where('organization_id', $organizationId))
                ->when($waveId, fn ($query) => $query->where('wave_id', $waveId))
                ->count(),
        ];
    }
}
