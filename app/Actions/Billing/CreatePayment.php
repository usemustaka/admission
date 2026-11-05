<?php

namespace App\Actions\Billing;

use App\Enums\PaymentStatus;
use App\Models\BillItem;
use App\Models\Payment;
use App\Support\Money;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Throwable;

class CreatePayment
{
    /**
     * Record a payment and allocate it to bill items. It waits for verification, unless the committee records
     * it directly. Only items of the payer's own children can be paid, and never more than what is left.
     *
     * The items are locked before their room is read, so two payments for the same item cannot both see the same
     * room. The proof file is stored last and removed again when anything fails, so no orphan is left behind.
     *
     * @throws ValidationException
     */
    public function handle(PaymentData $data, bool $verified = false): Payment
    {
        $storedProof = null;

        try {
            return DB::transaction(function () use ($data, $verified, &$storedProof): Payment {
                $itemIds = array_column($data->items, 'bill_item_id');

                BillItem::lockForAllocation($itemIds);

                $items = BillItem::query()
                    ->whereIn('id', $itemIds)
                    ->whereHas('bill.registrant', fn (Builder $registrants) => $registrants->forParentPerson($data->payerPersonId))
                    ->withPaymentTotals()
                    ->get()
                    ->keyBy('id');

                $seen = [];

                foreach ($data->items as $index => $row) {
                    if (isset($seen[$row['bill_item_id']])) {
                        throw ValidationException::withMessages(["items.{$index}.bill_item_id" => 'Rincian yang sama tidak boleh dipilih dua kali.']);
                    }

                    $seen[$row['bill_item_id']] = true;

                    $item = $items->get($row['bill_item_id']);

                    if ($item === null) {
                        throw ValidationException::withMessages(["items.{$index}.bill_item_id" => 'Tagihan ini tidak bisa dibayar dari sini.']);
                    }

                    // The committee may record money that is also waiting to be verified; checks then happen on verification.
                    $left = $item->outstandingAmount(reservePending: ! $verified);

                    if ($row['amount'] > $left) {
                        throw ValidationException::withMessages(["items.{$index}.amount" => "Nominal melebihi sisa tagihan {$item->name} (sisa ".Money::format($left).').']);
                    }
                }

                $payment = Payment::create([
                    'payer_person_id' => $data->payerPersonId,
                    'amount' => array_sum(array_column($data->items, 'amount')),
                    'paid_on' => $data->paidOn,
                    'method' => $data->method,
                    'note' => $data->note,
                    'status' => $verified ? PaymentStatus::Verified : PaymentStatus::Pending,
                    'created_by_user_id' => $data->createdBy->id,
                    'verified_by_user_id' => $verified ? $data->createdBy->id : null,
                    'verified_at' => $verified ? now() : null,
                ]);

                foreach ($data->items as $row) {
                    $payment->allocations()->create(['bill_item_id' => $row['bill_item_id'], 'amount' => $row['amount']]);
                }

                if ($data->proof !== null) {
                    $storedProof = $data->proof->store('payment-proofs', 'local') ?: null;

                    if ($storedProof === null) {
                        throw ValidationException::withMessages(['proof' => 'Bukti pembayaran tidak bisa disimpan. Coba kirim ulang.']);
                    }

                    $payment->update(['proof_path' => $storedProof]);
                }

                return $payment;
            });
        } catch (Throwable $exception) {
            if ($storedProof !== null) {
                Storage::disk('local')->delete($storedProof);
            }

            throw $exception;
        }
    }
}
