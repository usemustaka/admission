<?php

namespace App\Actions\Billing;

use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Models\User;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ReopenPayment
{
    public function __construct(private readonly WritePaymentAuditNote $writeAuditNote) {}

    /**
     * Put a rejected payment back in the queue. Rejecting never deletes the allocations, so nothing is lost; the
     * rejection reason is cleared and kept in a system note together with who rejected it and when.
     *
     * @throws ValidationException
     */
    public function handle(Payment $payment, User $actor, string $reason): void
    {
        DB::transaction(function () use ($payment, $actor, $reason): void {
            $locked = Payment::query()->whereKey($payment->id)->with('verifiedBy')->lockForUpdate()->first();

            if ($locked === null || $locked->status !== PaymentStatus::Rejected) {
                throw ValidationException::withMessages(['payment' => 'Hanya pembayaran yang ditolak yang bisa dibuka ulang. Statusnya mungkin sudah berubah, muat ulang halaman ini.']);
            }

            $rejectedBy = $locked->verifiedBy?->name ?? 'pengguna yang sudah dihapus';
            $rejectedAt = $locked->verified_at?->translatedFormat('j M Y, H:i \W\I\B') ?? 'waktu yang tidak tercatat';
            $previous = $locked->rejection_reason === null ? '' : "; alasan sebelumnya: {$locked->rejection_reason}";

            $this->writeAuditNote->handle($locked, 'Pembayaran #'.$locked->id.' '.Money::format($locked->amount)." (ditolak oleh {$rejectedBy} pada {$rejectedAt}{$previous}) dibuka ulang oleh {$actor->name} dan kembali menunggu verifikasi. Alasan: {$reason}");

            $claimed = Payment::query()
                ->whereKey($locked->id)
                ->where('status', PaymentStatus::Rejected->value)
                ->update([
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
