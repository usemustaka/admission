<?php

namespace App\Actions\Academic;

use App\Models\Period;
use App\Models\Year;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SaveYear
{
    public function __construct(private ActivateYear $activateYear) {}

    /**
     * Create or update an academic year. The first year ever created becomes the active one, and a year that is
     * active cannot be switched off here: another year has to be activated instead, so one always stays active.
     * A new year can copy the period structure (names, sequence and dates moved by whole years) of an earlier one.
     *
     * @param  array{name: string, starts_on: string, ends_on: string}  $data
     *
     * @throws ValidationException
     */
    public function handle(?Year $year, array $data, bool $active, ?Year $copyPeriodsFrom = null): Year
    {
        if ($year?->is_active && ! $active) {
            throw ValidationException::withMessages(['is_active' => 'Harus ada satu tahun ajaran yang aktif. Aktifkan tahun ajaran lain dulu, yang ini akan nonaktif otomatis.']);
        }

        return DB::transaction(function () use ($year, $data, $active, $copyPeriodsFrom): Year {
            $isNew = $year === null;
            $year ??= new Year;
            $year->fill([...$data, 'is_active' => $year->is_active ?? false])->save();

            if ($isNew && $copyPeriodsFrom !== null) {
                $this->copyPeriods($copyPeriodsFrom, $year);
            }

            if ($active || ($isNew && ! Year::query()->whereKeyNot($year->id)->where('is_active', true)->exists())) {
                $this->activateYear->handle($year);
            }

            return $year;
        });
    }

    private function copyPeriods(Year $from, Year $to): void
    {
        $shift = $to->starts_on->year - $from->starts_on->year;

        foreach ($from->periods()->orderBy('sequence')->get() as $period) {
            $startsOn = $period->starts_on->copy()->addYears($shift);
            $endsOn = $period->ends_on->copy()->addYears($shift);

            if ($startsOn->lt($to->starts_on) || $endsOn->gt($to->ends_on)) {
                continue;
            }

            Period::query()->create([
                'year_id' => $to->id,
                'name' => $period->name,
                'sequence' => $period->sequence,
                'starts_on' => $startsOn->toDateString(),
                'ends_on' => $endsOn->toDateString(),
                'is_active' => false,
            ]);
        }
    }
}
