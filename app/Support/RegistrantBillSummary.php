<?php

namespace App\Support;

use App\Enums\BillStatus;
use App\Models\Bill;
use Illuminate\Support\Collection;

/**
 * How a registrant stands on all their bills together, for lists and quick views. Bills must be loaded with
 * items that have payment totals (`withPaymentTotals`), so no query runs per bill.
 */
class RegistrantBillSummary
{
    /**
     * `none` when no bill was issued yet; otherwise `paid` once every bill is paid, `unpaid` while nothing is
     * paid at all, and `partial` in between. A bill that asks for nothing (all items Rp0) is settled from the start
     * and never makes the registrant look partly paid; when every bill is like that, the registrant counts as `paid`.
     *
     * @param  Collection<int, Bill>  $bills
     * @return 'none'|'unpaid'|'partial'|'paid'
     */
    public static function status(Collection $bills): string
    {
        if ($bills->isEmpty()) {
            return 'none';
        }

        $payable = $bills->filter(fn (Bill $bill): bool => $bill->totalAmount() > 0);

        if ($payable->isEmpty()) {
            return 'paid';
        }

        $statuses = $payable->map(fn (Bill $bill): BillStatus => $bill->status());

        return match (true) {
            $statuses->every(fn (BillStatus $status): bool => $status === BillStatus::Paid) => 'paid',
            $statuses->every(fn (BillStatus $status): bool => $status === BillStatus::Unpaid) => 'unpaid',
            default => 'partial',
        };
    }

    /**
     * @param  Collection<int, Bill>  $bills
     *                                        `excess` is verified money beyond what the items ask for; `outstanding` is counted per item so it is never
     *                                        lowered by that surplus.
     * @return array{status: string, total: int, paid: int, pending: int, outstanding: int, excess: int}
     */
    public static function totals(Collection $bills): array
    {
        $total = (int) $bills->sum(fn (Bill $bill): int => $bill->totalAmount());
        $paid = (int) $bills->sum(fn (Bill $bill): int => $bill->paidAmount());

        return [
            'status' => self::status($bills),
            'total' => $total,
            'paid' => $paid,
            'pending' => (int) $bills->sum(fn (Bill $bill): int => $bill->pendingAmount()),
            'outstanding' => (int) $bills->sum(fn (Bill $bill): int => $bill->outstandingAmount()),
            'excess' => (int) $bills->sum(fn (Bill $bill): int => $bill->excessAmount()),
        ];
    }
}
