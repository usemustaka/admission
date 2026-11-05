<?php

namespace App\Actions\Billing;

use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class RejectPayment
{
    /**
     * Turn a payment down. It never counts towards a bill, and the reason is shown to the parent. The payment moves
     * from pending to rejected in one statement, so it can never overwrite a payment that was verified a moment ago.
     *
     * @throws ValidationException
     */
    public function handle(Payment $payment, User $reviewer, string $reason): void
    {
        $claimed = Payment::query()
            ->whereKey($payment->id)
            ->where('status', PaymentStatus::Pending->value)
            ->update([
                'status' => PaymentStatus::Rejected->value,
                'verified_by_user_id' => $reviewer->id,
                'verified_at' => now(),
                'rejection_reason' => $reason,
            ]);

        if ($claimed !== 1) {
            throw ValidationException::withMessages(['payment' => 'Pembayaran ini sudah diproses.']);
        }

        $payment->refresh();
    }
}
