<?php

namespace App\Actions\Billing;

use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Models\User;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class VoidPayment
{
    public function __construct(private readonly WritePaymentAuditNote $writeAuditNote) {}

    /**
     * Take back a payment that was verified by mistake. It either becomes rejected (the money never arrived) or goes
     * back to pending (to be checked and verified again, possibly with another division). The bills recompute on their
     * own, since only verified payments count. Registrant statuses are not rolled back: the registrant may have moved
     * on since.
     *
     * Who verified it and when would be overwritten, so a system note records them first on the payer and on the
     * children it paid for. The row is locked and the status move is a compare-and-swap, like verifying.
     *
     * @throws ValidationException
     */
    public function handle(Payment $payment, User $actor, PaymentStatus $to, string $reason): void
    {
        if ($to === PaymentStatus::Verified) {
            throw ValidationException::withMessages(['to' => 'Pilih ditolak atau menunggu verifikasi.']);
        }

        DB::transaction(function () use ($payment, $actor, $to, $reason): void {
            $locked = Payment::query()->whereKey($payment->id)->with('verifiedBy')->lockForUpdate()->first();

            if ($locked === null || $locked->status !== PaymentStatus::Verified) {
                throw ValidationException::withMessages(['payment' => 'Hanya pembayaran yang terverifikasi yang bisa dibatalkan. Statusnya mungkin sudah berubah, muat ulang halaman ini.']);
            }

            $verifiedBy = $locked->verifiedBy?->name ?? 'pengguna yang sudah dihapus';
            $verifiedAt = $locked->verified_at?->translatedFormat('j M Y, H:i \W\I\B') ?? 'waktu yang tidak tercatat';

            $this->writeAuditNote->handle($locked, 'Pembayaran #'.$locked->id.' '.Money::format($locked->amount)." (diverifikasi oleh {$verifiedBy} pada {$verifiedAt}) dibatalkan oleh {$actor->name} dan statusnya menjadi ".mb_strtolower($to->label()).". Alasan: {$reason}");

            $claimed = Payment::query()
                ->whereKey($locked->id)
                ->where('status', PaymentStatus::Verified->value)
                ->update($to === PaymentStatus::Rejected
                    ? [
                        'status' => PaymentStatus::Rejected->value,
                        'verified_by_user_id' => $actor->id,
                        'verified_at' => now(),
                        'rejection_reason' => "Dibatalkan: {$reason}",
                    ]
                    : [
                        'status' => PaymentStatus::Pending->value,
                        'verified_by_user_id' => null,
                        'verified_at' => null,
                        'rejection_reason' => null,
                    ]);

            if ($claimed !== 1) {
                throw ValidationException::withMessages(['payment' => 'Pembayaran ini sudah diproses oleh orang lain. Muat ulang halaman ini.']);
            }
        });

        $payment->refresh();
    }
}
