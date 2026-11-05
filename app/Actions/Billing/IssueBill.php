<?php

namespace App\Actions\Billing;

use App\Enums\BillType;
use App\Models\Bill;
use App\Models\Registrant;
use Illuminate\Support\Facades\DB;

class IssueBill
{
    /**
     * Issue the bill of a type from the ruleset copy kept on the registration. Nothing is issued when the ruleset
     * has no items for that type, and a bill is never issued twice.
     */
    public function handle(Registrant $registrant, BillType $type): ?Bill
    {
        $existing = $registrant->bills()->where('type', $type)->first();

        if ($existing !== null) {
            return $existing;
        }

        $items = collect($registrant->ruleset_snapshot['bills'] ?? [])
            ->firstWhere('type', $type->value)['items'] ?? [];

        if ($items === []) {
            return null;
        }

        return DB::transaction(function () use ($registrant, $type, $items): Bill {
            $bill = $registrant->bills()->create(['type' => $type, 'issued_at' => now()]);

            foreach ($items as $index => $item) {
                $bill->items()->create(['name' => $item['name'], 'amount' => $item['amount'], 'sort_order' => $index]);
            }

            return $bill;
        });
    }
}
