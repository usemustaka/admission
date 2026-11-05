<?php

namespace App\Policies;

use App\Enums\Ability;
use App\Models\Payment;
use App\Models\User;

class PaymentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Ability::PaymentView->value);
    }

    /**
     * The committee, the payer, and whoever submitted the payment can look at it and its proof.
     */
    public function view(User $user, Payment $payment): bool
    {
        return $user->can(Ability::PaymentView->value)
            || $payment->payer_person_id === $user->person_id
            || $payment->created_by_user_id === $user->id;
    }

    public function verify(User $user): bool
    {
        return $user->can(Ability::PaymentVerify->value);
    }
}
