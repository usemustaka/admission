<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PaymentProofController extends Controller
{
    /**
     * Proofs are private files, shown only to the committee and the people who made the payment.
     */
    public function __invoke(Payment $payment): StreamedResponse
    {
        Gate::authorize('view', $payment);

        abort_if($payment->proof_path === null, 404);

        return Storage::disk('local')->response($payment->proof_path);
    }
}
