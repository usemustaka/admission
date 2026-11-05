<?php

namespace App\Support;

use App\Models\Payment;
use App\Models\PaymentAllocation;

/**
 * Payments as the committee reads them: a compact row for the list and the full picture for the detail page and
 * the quick view.
 */
class PaymentPresenter
{
    /**
     * One row of the payments list. The payment needs `payer` and `allocations.billItem.bill.registrant.person`.
     *
     * @return array<string, mixed>
     */
    public static function row(Payment $payment): array
    {
        return [
            ...BillPresenter::payment($payment),
            'method_value' => $payment->method->value,
            'note' => $payment->note,
            'items' => $payment->allocations
                ->map(fn (PaymentAllocation $allocation): array => ['name' => $allocation->billItem->name, 'amount' => $allocation->amount])
                ->values()
                ->all(),
        ];
    }

    /**
     * Everything about one payment, with its division over bill items and what each item can still take.
     *
     * @return array{payment: array<string, mixed>, allocations: array<int, array<string, mixed>>, can_verify_as_submitted: bool}
     */
    public static function detail(Payment $payment): array
    {
        $payment->load(['payer', 'createdBy.person', 'verifiedBy.person', 'allocations.billItem.bill.registrant.person']);

        $allocations = $payment->allocations->map(function (PaymentAllocation $allocation) use ($payment): array {
            $item = $allocation->billItem()->withPaymentTotals()->firstOrFail();
            $bill = $item->bill;

            return [
                'bill_item_id' => $item->id,
                'item' => $item->name,
                'bill' => $bill->type->label(),
                'child' => RegistrantPresenter::childName($bill->registrant),
                'item_amount' => $item->amount,
                'amount' => $allocation->amount,
                'room' => $payment->isPending() ? $item->outstandingAmount(reservePending: false) : null,
            ];
        })->values()->all();

        return [
            'payment' => [
                ...BillPresenter::payment($payment),
                'method_value' => $payment->method->value,
                'note' => $payment->note,
                'proof_is_image' => $payment->proof_path !== null && preg_match('/\.(jpe?g|png)$/i', $payment->proof_path) === 1,
                'payer_id' => $payment->payer_person_id,
                'created_by' => $payment->createdBy?->name,
                'verified_by' => $payment->verifiedBy?->name,
                'verified_at' => $payment->verified_at?->translatedFormat('j M Y, H:i'),
            ],
            'allocations' => $allocations,
            'can_verify_as_submitted' => $payment->isPending() && collect($allocations)->every(fn (array $row): bool => $row['amount'] <= $row['room']),
        ];
    }
}
