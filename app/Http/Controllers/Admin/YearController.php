<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Academic\ActivateYear;
use App\Actions\Academic\DeleteYear;
use App\Actions\Academic\SaveYear;
use App\Enums\Ability;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SaveYearRequest;
use App\Models\Period;
use App\Models\Year;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class YearController extends Controller
{
    public function index(): Response
    {
        Gate::authorize(Ability::YearManage->value);

        $years = Year::query()
            ->with(['periods' => fn ($periods) => $periods->orderBy('sequence')])
            ->withCount('students')
            ->orderByDesc('starts_on')
            ->orderByDesc('id')
            ->get();

        return Inertia::render('admin/years/index', [
            'years' => $years->map(fn (Year $year): array => [
                'id' => $year->id,
                'name' => $year->name,
                'starts_on' => $year->starts_on->toDateString(),
                'ends_on' => $year->ends_on->toDateString(),
                'is_active' => $year->is_active,
                'students_count' => $year->students_count,
                'delete_blocked' => match (true) {
                    $year->is_active => 'Aktifkan tahun ajaran lain dulu untuk bisa menghapus yang ini.',
                    $year->students_count > 0 => "Masih dipakai {$year->students_count} siswa.",
                    default => null,
                },
                'periods' => $year->periods->map(fn (Period $period): array => [
                    'id' => $period->id,
                    'name' => $period->name,
                    'sequence' => $period->sequence,
                    'starts_on' => $period->starts_on->toDateString(),
                    'ends_on' => $period->ends_on->toDateString(),
                    'is_active' => $period->is_active,
                ])->values(),
            ])->values(),
            'suggestion' => $this->suggestion($years->first()),
        ]);
    }

    public function store(SaveYearRequest $request, SaveYear $saveYear): RedirectResponse
    {
        $copyFrom = $request->filled('copy_periods_from') ? Year::query()->find($request->integer('copy_periods_from')) : null;

        $year = $saveYear->handle(null, $request->safe()->only(['name', 'starts_on', 'ends_on']), $request->boolean('is_active'), $copyFrom);

        $this->flash("Tahun ajaran {$year->name} sudah dibuat.");

        return to_route('admin.years.index');
    }

    public function update(SaveYearRequest $request, Year $year, SaveYear $saveYear): RedirectResponse
    {
        $saveYear->handle($year, $request->safe()->only(['name', 'starts_on', 'ends_on']), $request->has('is_active') ? $request->boolean('is_active') : $year->is_active);

        $this->flash('Tahun ajaran sudah diperbarui.');

        return to_route('admin.years.index');
    }

    public function destroy(Year $year, DeleteYear $deleteYear): RedirectResponse
    {
        Gate::authorize(Ability::YearManage->value);

        $deleteYear->handle($year);

        $this->flash("Tahun ajaran {$year->name} sudah dihapus.");

        return to_route('admin.years.index');
    }

    public function activate(Year $year, ActivateYear $activateYear): RedirectResponse
    {
        Gate::authorize(Ability::YearManage->value);

        $activateYear->handle($year);

        $this->flash("Tahun ajaran {$year->name} sekarang aktif.");

        return back();
    }

    /**
     * What the "new year" form starts with: the year after the latest one (or the current one, when there is none),
     * and the latest year that has periods, whose structure can be copied.
     *
     * @return array{name: string, starts_on: string, ends_on: string, copy_from_id: int|null, copy_from_name: string|null}
     */
    private function suggestion(?Year $latest): array
    {
        if ($latest === null) {
            $startYear = now()->month >= 7 ? now()->year : now()->year - 1;
            $starts = CarbonImmutable::create($startYear, 7, 1);
        } else {
            $starts = CarbonImmutable::parse($latest->ends_on)->addDay();
        }

        $ends = $starts->addYear()->subDay();
        $source = Year::query()->has('periods')->orderByDesc('starts_on')->first();

        return [
            'name' => $starts->year.'/'.($starts->year + 1),
            'starts_on' => $starts->toDateString(),
            'ends_on' => $ends->toDateString(),
            'copy_from_id' => $source?->id,
            'copy_from_name' => $source?->name,
        ];
    }

    private function flash(string $message): void
    {
        Inertia::flash('toast', ['type' => 'success', 'message' => $message]);
    }
}
