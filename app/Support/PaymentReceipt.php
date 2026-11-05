<?php

namespace App\Support;

use App\Models\Payment;
use App\Models\PaymentAllocation;

/**
 * What a printed receipt (kwitansi) says about one verified payment.
 */
class PaymentReceipt
{
    /**
     * "KW-2026-000123": the year the money was paid and the payment id, so the number never needs storing.
     */
    public static function number(Payment $payment): string
    {
        return sprintf('KW-%d-%06d', $payment->paid_on->year, $payment->id);
    }

    /**
     * @return array<string, mixed>
     */
    public static function data(Payment $payment): array
    {
        $payment->load([
            'payer',
            'verifiedBy',
            'allocations.billItem.bill.registrant.person',
            'allocations.billItem.bill.registrant.organization',
        ]);

        return [
            'number' => self::number($payment),
            'paid_on' => $payment->paid_on->toDateString(),
            'payer' => $payment->payer->name,
            'method' => $payment->method->label(),
            'lines' => $payment->allocations
                ->map(function (PaymentAllocation $allocation): array {
                    $bill = $allocation->billItem->bill;

                    return [
                        'child' => RegistrantPresenter::childName($bill->registrant),
                        'organization' => $bill->registrant->organization->name,
                        'bill' => $bill->type->label(),
                        'item' => $allocation->billItem->name,
                        'amount' => $allocation->amount,
                    ];
                })
                ->values()
                ->all(),
            'total' => $payment->amount,
            'total_words' => Terbilang::rupiah($payment->amount),
            'note' => $payment->note,
            'verified_by' => $payment->verifiedBy?->name,
            'verified_at' => $payment->verified_at?->translatedFormat('j F Y, H:i \W\I\B'),
        ];
    }
}
