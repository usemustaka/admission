<?php

namespace App\Support;

use App\Models\Bill;
use App\Models\BillItem;
use App\Models\Payment;
use App\Models\PaymentAllocation;

class BillPresenter
{
    /**
     * A bill with its items and how much of each was paid. Items must be loaded with `withPaymentTotals`.
     *
     * @return array<string, mixed>
     */
    public static function bill(Bill $bill): array
    {
        $bill->loadMissing('registrant.person', 'registrant.organization');
        $registrant = $bill->registrant;

        return [
            'id' => $bill->id,
            'type' => $bill->type->value,
            'type_label' => $bill->type->label(),
            'status' => $bill->status()->value,
            'registrant' => [
                'id' => $registrant->id,
                'child_name' => RegistrantPresenter::childName($registrant),
                'organization' => $registrant->organization->name,
            ],
            'total' => $bill->totalAmount(),
            'paid' => $bill->paidAmount(),
            'pending' => $bill->pendingAmount(),
            'items' => $bill->items->map(fn (BillItem $item): array => [
                'id' => $item->id,
                'name' => $item->name,
                'amount' => $item->amount,
                'paid' => $item->paidAmount(),
                'pending' => $item->pendingAmount(),
                'outstanding' => $item->outstandingAmount(),
                'unpaid' => $item->outstandingAmount(reservePending: false),
            ])->values()->all(),
        ];
    }

    /**
     * A payment as a parent or the committee reads it in a list.
     *
     * @return array<string, mixed>
     */
    public static function payment(Payment $payment): array
    {
        $payment->loadMissing('payer', 'allocations.billItem.bill.registrant.person');

        return [
            'id' => $payment->id,
            'amount' => $payment->amount,
            'paid_on' => $payment->paid_on->toDateString(),
            'method' => $payment->method->label(),
            'status' => $payment->status->value,
            'status_label' => $payment->status->label(),
            'rejection_reason' => $payment->rejection_reason,
            'has_proof' => $payment->proof_path !== null,
            'payer' => $payment->payer->name,
            'for' => $payment->allocations
                ->map(fn (PaymentAllocation $allocation): string => RegistrantPresenter::childName($allocation->billItem->bill->registrant))
                ->unique()
                ->values()
                ->all(),
            'created_at' => $payment->created_at?->translatedFormat('j M Y, H:i'),
        ];
    }

    /**
     * The items that can still be paid, grouped by bill, for the payment forms. Parents cannot resubmit money that
     * already waits for verification; the committee can, so it passes `$reservePending = false`.
     *
     * @param  iterable<Bill>  $bills  loaded with items that have payment totals
     * @return list<array{bill_id: int, type_label: string, registrant: array<string, mixed>, items: list<array{id: int, name: string, amount: int, outstanding: int}>}>
     */
    public static function openGroups(iterable $bills, bool $reservePending = true): array
    {
        $groups = [];

        foreach ($bills as $bill) {
            $presented = self::bill($bill);
            $items = [];

            foreach ($presented['items'] as $item) {
                $left = $reservePending ? $item['outstanding'] : $item['unpaid'];

                if ($left > 0) {
                    $items[] = ['id' => $item['id'], 'name' => $item['name'], 'amount' => $item['amount'], 'outstanding' => $left];
                }
            }

            if ($items !== []) {
                $groups[] = ['bill_id' => $presented['id'], 'type_label' => $presented['type_label'], 'registrant' => $presented['registrant'], 'items' => $items];
            }
        }

        return $groups;
    }
}
