<?php

namespace App\Actions\Billing;

use App\Enums\PaymentStatus;
use App\Models\BillItem;
use App\Models\Payment;
use App\Models\User;
use App\Support\Money;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class VerifyPayment
{
    /**
     * Confirm a payment, optionally changing how it is divided over the items. The total of the allocations must
     * stay equal to the payment, and no item may be paid beyond what is left of it.
     *
     * The payment row and the affected items are locked first, so two staff members (or a double click) queue up
     * and the second one reads the totals the first one left. Moving the status from pending is also a
     * compare-and-swap, which holds even where row locks do nothing.
     *
     * @param  array<int, array{bill_item_id: int, amount: int}>|null  $allocations  the final division, keyed by the row it came from so errors point at it; null keeps the submitted one
     *
     * @throws ValidationException
     */
    public function handle(Payment $payment, User $verifier, ?array $allocations = null): void
    {
        DB::transaction(function () use ($payment, $verifier, $allocations): void {
            $locked = Payment::query()->whereKey($payment->id)->lockForUpdate()->first();

            if ($locked === null || ! $locked->isPending()) {
                throw ValidationException::withMessages(['payment' => 'Pembayaran ini sudah diproses.']);
            }

            $division = $allocations ?? $locked->allocations()->get()->map(fn ($allocation): array => ['bill_item_id' => $allocation->bill_item_id, 'amount' => $allocation->amount])->all();

            if (array_sum(array_column($division, 'amount')) !== $locked->amount) {
                throw ValidationException::withMessages(['allocations' => 'Total alokasi harus sama dengan nominal pembayaran ('.Money::format($locked->amount).').']);
            }

            $seen = [];

            foreach ($division as $index => $row) {
                if (isset($seen[$row['bill_item_id']])) {
                    throw ValidationException::withMessages(["allocations.{$index}.bill_item_id" => 'Rincian yang sama tidak boleh muncul dua kali.']);
                }

                $seen[$row['bill_item_id']] = true;
            }

            BillItem::lockForAllocation(array_column($division, 'bill_item_id'));

            $items = BillItem::query()
                ->whereIn('id', array_column($division, 'bill_item_id'))
                ->whereHas('bill.registrant', fn (Builder $registrants) => $registrants->forParentPerson($locked->payer_person_id))
                ->withPaymentTotals()
                ->get()
                ->keyBy('id');

            foreach ($division as $index => $row) {
                $item = $items->get($row['bill_item_id']);

                if ($item === null || $row['amount'] < 1) {
                    throw ValidationException::withMessages(["allocations.{$index}.amount" => 'Alokasi ini tidak valid.']);
                }

                $left = $item->outstandingAmount(reservePending: false);

                if ($row['amount'] > $left) {
                    throw ValidationException::withMessages(["allocations.{$index}.amount" => "Alokasi melebihi sisa tagihan {$item->name} (sisa ".Money::format($left).').']);
                }
            }

            $claimed = Payment::query()
                ->whereKey($locked->id)
                ->where('status', PaymentStatus::Pending->value)
                ->update([
                    'status' => PaymentStatus::Verified->value,
                    'verified_by_user_id' => $verifier->id,
                    'verified_at' => now(),
                    'rejection_reason' => null,
                ]);

            if ($claimed !== 1) {
                throw ValidationException::withMessages(['payment' => 'Pembayaran ini sudah diproses.']);
            }

            if ($allocations !== null) {
                $locked->allocations()->delete();

                foreach ($division as $row) {
                    $locked->allocations()->create($row);
                }
            }
        });

        $payment->refresh();
    }
}
