<?php

namespace App\Actions\Billing;

use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class RejectPayment
{
    /**
     * Turn a payment down. It never counts towards a bill, and the reason is shown to the parent.
     *
     * @throws ValidationException
     */
    public function handle(Payment $payment, User $reviewer, string $reason): void
    {
        if (! $payment->isPending()) {
            throw ValidationException::withMessages(['payment' => 'Pembayaran ini sudah diproses.']);
        }

        $payment->update([
            'status' => PaymentStatus::Rejected,
            'verified_by_user_id' => $reviewer->id,
            'verified_at' => now(),
            'rejection_reason' => $reason,
        ]);
    }
}
