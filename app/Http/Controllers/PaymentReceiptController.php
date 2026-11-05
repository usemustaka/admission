<?php

namespace App\Http\Controllers;

use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Support\PaymentReceipt;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class PaymentReceiptController extends Controller
{
    /**
     * A print view of the receipt for a verified payment, open to the same people as the proof: the committee, the
     * payer, and whoever submitted it. Money that was not accepted (pending or rejected) has no receipt.
     */
    public function __invoke(Payment $payment): Response
    {
        Gate::authorize('view', $payment);

        abort_unless($payment->status === PaymentStatus::Verified, 404, 'Kwitansi hanya tersedia untuk pembayaran yang sudah diverifikasi.');

        return Inertia::render('payments/receipt', ['receipt' => PaymentReceipt::data($payment)]);
    }
}
