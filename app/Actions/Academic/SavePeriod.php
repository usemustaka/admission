<?php

namespace App\Actions\Academic;

use App\Models\Period;
use App\Models\Year;
use Illuminate\Support\Facades\DB;

class SavePeriod
{
    /**
     * Create or update a period of a year. At most one period is active across the whole system, so activating
     * one switches the others off in the same transaction.
     *
     * @param  array{name: string, sequence: int, starts_on: string, ends_on: string}  $data
     */
    public function handle(Year $year, ?Period $period, array $data, bool $active): Period
    {
        return DB::transaction(function () use ($year, $period, $data, $active): Period {
            if ($active) {
                Period::query()->lockForUpdate()->get();
                Period::query()->where('is_active', true)->when($period, fn ($query) => $query->whereKeyNot($period->id))->update(['is_active' => false]);
            }

            $period ??= new Period(['year_id' => $year->id]);
            $period->fill([...$data, 'is_active' => $active])->save();

            return $period;
        });
    }
}
