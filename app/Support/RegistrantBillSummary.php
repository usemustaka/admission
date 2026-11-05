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
     * paid at all, and `partial` in between.
     *
     * @param  Collection<int, Bill>  $bills
     * @return 'none'|'unpaid'|'partial'|'paid'
     */
    public static function status(Collection $bills): string
    {
        if ($bills->isEmpty()) {
            return 'none';
        }

        $statuses = $bills->map(fn (Bill $bill): BillStatus => $bill->status());

        return match (true) {
            $statuses->every(fn (BillStatus $status): bool => $status === BillStatus::Paid) => 'paid',
            $statuses->every(fn (BillStatus $status): bool => $status === BillStatus::Unpaid) => 'unpaid',
            default => 'partial',
        };
    }

    /**
     * @param  Collection<int, Bill>  $bills
     * @return array{status: string, total: int, paid: int, pending: int, outstanding: int}
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
            'outstanding' => max($total - $paid, 0),
        ];
    }
}
