<?php

namespace App\Actions\Academic;

use App\Models\Year;
use Illuminate\Support\Facades\DB;

class ActivateYear
{
    /**
     * Make a year the active one: exactly one year is active at any time, so every other year is switched off
     * in the same transaction.
     */
    public function handle(Year $year): void
    {
        DB::transaction(function () use ($year): void {
            Year::query()->lockForUpdate()->get();

            Year::query()->whereKeyNot($year->id)->where('is_active', true)->update(['is_active' => false]);

            $year->forceFill(['is_active' => true])->save();
        });
    }
}
