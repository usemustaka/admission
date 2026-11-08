<?php

namespace App\Support;

use App\Enums\PaymentStatus;
use App\Models\Bill;
use App\Models\BillItem;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Models\Registrant;
use Illuminate\Support\Collection;

/**
 * The receipt that says everything a child's family had to pay is paid ("kwitansi lunas"): one paper for the whole
 * registration, listing every bill item and every verified payment that covered them, scholarships included.
 */
class SettlementReceipt
{
    /**
     * "LNS-2026-000123": the year of the last payment and the registration id, so the number never needs storing.
     */
    public static function number(Registrant $registrant, ?Payment $last): string
    {
        return sprintf('LNS-%d-%06d', ($last?->paid_on ?? today())->year, $registrant->id);
    }

    /**
     * Whether every bill is paid and something was billed at all.
     */
    public static function isAvailable(Registrant $registrant): bool
    {
        $totals = RegistrantBillSummary::totals(self::bills($registrant));

        return $totals['total'] > 0 && $totals['status'] === 'paid' && $totals['outstanding'] === 0;
    }

    /**
     * @return array<string, mixed>
     */
    public static function data(Registrant $registrant): array
    {
        $registrant->loadMissing(['person', 'organization', 'registeredBy']);

        $bills = self::bills($registrant);
        $totals = RegistrantBillSummary::totals($bills);

        $payments = Payment::query()
            ->where('status', PaymentStatus::Verified)
            ->whereHas('allocations.billItem.bill', fn ($bill) => $bill->where('registrant_id', $registrant->id))
            ->with(['payer', 'allocations' => fn ($allocations) => $allocations->whereHas('billItem.bill', fn ($bill) => $bill->where('registrant_id', $registrant->id))])
            ->orderBy('paid_on')
            ->orderBy('id')
            ->get();

        $last = $payments->last();

        return [
            'number' => self::number($registrant, $last),
            'date' => ($last?->paid_on ?? today())->toDateString(),
            'payer' => $registrant->registeredBy->name,
            'child' => RegistrantPresenter::childName($registrant),
            'organization' => $registrant->organization->name,
            'items' => $bills->flatMap(fn (Bill $bill): Collection => $bill->items->map(fn (BillItem $item): array => [
                'bill' => $bill->type->label(),
                'name' => $item->name,
                'amount' => $item->amount,
            ]))->values()->all(),
            'payments' => $payments->map(fn (Payment $payment): array => [
                'date' => $payment->paid_on->toDateString(),
                'method' => $payment->method->label(),
                'amount' => (int) $payment->allocations->sum(fn (PaymentAllocation $allocation): int => $allocation->amount),
            ])->values()->all(),
            'total' => $totals['total'],
            'paid' => $totals['paid'],
            'total_words' => Terbilang::rupiah($totals['total']),
        ];
    }

    /**
     * @return Collection<int, Bill>
     */
    private static function bills(Registrant $registrant): Collection
    {
        return $registrant->bills()->with(['items' => fn ($items) => $items->withPaymentTotals()])->orderBy('issued_at')->orderBy('id')->get();
    }
}
