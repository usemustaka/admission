<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Academic\SavePeriod;
use App\Enums\Ability;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SavePeriodRequest;
use App\Models\Period;
use App\Models\Year;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

class PeriodController extends Controller
{
    public function store(SavePeriodRequest $request, Year $year, SavePeriod $savePeriod): RedirectResponse
    {
        $savePeriod->handle($year, null, $request->safe()->only(['name', 'sequence', 'starts_on', 'ends_on']), $request->boolean('is_active'));

        $this->flash('Periode sudah ditambahkan.');

        return to_route('admin.years.index');
    }

    public function update(SavePeriodRequest $request, Year $year, Period $period, SavePeriod $savePeriod): RedirectResponse
    {
        $savePeriod->handle($year, $period, $request->safe()->only(['name', 'sequence', 'starts_on', 'ends_on']), $request->has('is_active') ? $request->boolean('is_active') : $period->is_active);

        $this->flash('Periode sudah diperbarui.');

        return to_route('admin.years.index');
    }

    public function destroy(Year $year, Period $period): RedirectResponse
    {
        Gate::authorize(Ability::YearManage->value);

        $period->delete();

        $this->flash("Periode {$period->name} sudah dihapus.");

        return to_route('admin.years.index');
    }

    private function flash(string $message): void
    {
        Inertia::flash('toast', ['type' => 'success', 'message' => $message]);
    }
}
