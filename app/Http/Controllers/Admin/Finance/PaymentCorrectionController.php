<?php

namespace App\Http\Controllers\Admin\Finance;

use App\Actions\Billing\ReopenPayment;
use App\Actions\Billing\VoidPayment;
use App\Enums\PaymentStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Finance\ReopenPaymentRequest;
use App\Http\Requests\Admin\Finance\VoidPaymentRequest;
use App\Models\Payment;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class PaymentCorrectionController extends Controller
{
    /**
     * Take back a verified payment: it becomes rejected or goes back to pending, with a reason either way.
     */
    public function void(VoidPaymentRequest $request, Payment $payment, VoidPayment $voidPayment): RedirectResponse
    {
        $to = $request->enum('to', PaymentStatus::class) ?? PaymentStatus::Rejected;

        $voidPayment->handle($payment, $request->user(), $to, trim($request->string('reason')->toString()));

        Inertia::flash('toast', ['type' => 'success', 'message' => $to === PaymentStatus::Pending
            ? 'Pembayaran dikembalikan ke antrean verifikasi dan tidak lagi dihitung ke tagihan.'
            : 'Pembayaran dibatalkan dan tidak lagi dihitung ke tagihan.']);

        return to_route('admin.payments.show', $payment);
    }

    /**
     * Put a rejected payment back in the verification queue.
     */
    public function reopen(ReopenPaymentRequest $request, Payment $payment, ReopenPayment $reopenPayment): RedirectResponse
    {
        $reopenPayment->handle($payment, $request->user(), trim($request->string('reason')->toString()));

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Pembayaran dibuka ulang dan menunggu verifikasi.']);

        return to_route('admin.payments.show', $payment);
    }
}
