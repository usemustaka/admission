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
     * @param  list<array{bill_item_id: int, amount: int}>|null  $allocations  the final division; null keeps the submitted one
     *
     * @throws ValidationException
     */
    public function handle(Payment $payment, User $verifier, ?array $allocations = null): void
    {
        DB::transaction(function () use ($payment, $verifier, $allocations): void {
            $payment->refresh();

            if (! $payment->isPending()) {
                throw ValidationException::withMessages(['payment' => 'Pembayaran ini sudah diproses.']);
            }

            $division = $allocations ?? $payment->allocations->map(fn ($allocation): array => ['bill_item_id' => $allocation->bill_item_id, 'amount' => $allocation->amount])->all();

            if (array_sum(array_column($division, 'amount')) !== $payment->amount) {
                throw ValidationException::withMessages(['allocations' => 'Total alokasi harus sama dengan nominal pembayaran ('.Money::format($payment->amount).').']);
            }

            $items = BillItem::query()
                ->whereIn('id', array_column($division, 'bill_item_id'))
                ->whereHas('bill.registrant', fn (Builder $registrants) => $registrants->forParentPerson($payment->payer_person_id))
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

            if ($allocations !== null) {
                $payment->allocations()->delete();

                foreach ($division as $row) {
                    $payment->allocations()->create($row);
                }
            }

            $payment->update([
                'status' => PaymentStatus::Verified,
                'verified_by_user_id' => $verifier->id,
                'verified_at' => now(),
                'rejection_reason' => null,
            ]);
        });
    }
}
