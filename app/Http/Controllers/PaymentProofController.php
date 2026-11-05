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

        $disk = Storage::disk('local');

        abort_if($payment->proof_path === null || ! $disk->exists($payment->proof_path), 404);

        return $disk->response($payment->proof_path, null, ['X-Content-Type-Options' => 'nosniff']);
    }
}
