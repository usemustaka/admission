<?php

namespace App\Actions\Admission;

use App\Enums\BillType;
use App\Models\Ruleset;
use App\Support\SyncRows;
use Illuminate\Support\Facades\DB;

class SyncBills
{
    /**
     * Replace the bill templates of a ruleset. A type without items has no bill at all, which skips that stage
     * of the admission flow.
     *
     * @param  array<string, list<array{id?: int|null, name: string, amount: int}>>  $bills  items per bill type
     */
    public function handle(Ruleset $ruleset, array $bills): void
    {
        DB::transaction(function () use ($ruleset, $bills): void {
            foreach (BillType::cases() as $type) {
                $items = $bills[$type->value] ?? [];
                $bill = $ruleset->bills()->where('type', $type)->first();

                if ($items === []) {
                    $bill?->delete();

                    continue;
                }

                $bill ??= $ruleset->bills()->create(['type' => $type]);

                SyncRows::handle($bill->items(), $items, fn (array $row, int $index): array => [
                    'name' => $row['name'],
                    'amount' => $row['amount'],
                    'sort_order' => $index,
                ]);
            }
        });
    }
}
