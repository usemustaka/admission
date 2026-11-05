<?php

namespace App\Actions\Billing;

use App\Models\Payment;
use App\Models\User;
use App\Support\Money;
use Illuminate\Validation\ValidationException;
use Throwable;

class BulkVerifyPayments
{
    public function __construct(private readonly VerifyPayment $verifyPayment) {}

    /**
     * Verify each payment as it was submitted. Every payment stands on its own: one that cannot be verified (already
     * handled, an item that cannot take the money any more, an unexpected failure) is skipped with its reason and
     * never blocks the rest. A pending payment without a proof file is skipped too, unless the committee opted in.
     *
     * @param  list<int>  $ids
     * @return array{verified: int, skipped: list<array{id: int, label: string, reason: string}>}
     */
    public function handle(array $ids, User $verifier, bool $includeWithoutProof = false): array
    {
        $payments = Payment::query()->with('payer')->whereIn('id', $ids)->get()->keyBy('id');
        $verified = 0;
        $skipped = [];

        foreach ($ids as $id) {
            $payment = $payments->get($id);

            if ($payment === null) {
                $skipped[] = ['id' => $id, 'label' => "Pembayaran #{$id}", 'reason' => 'tidak ditemukan'];

                continue;
            }

            $label = "{$payment->payer->name} ".Money::format($payment->amount);

            if (! $includeWithoutProof && $payment->isPending() && $payment->proof_path === null) {
                $skipped[] = ['id' => $id, 'label' => $label, 'reason' => 'Tanpa bukti'];

                continue;
            }

            try {
                $this->verifyPayment->handle($payment, $verifier);
                $verified++;
            } catch (ValidationException $exception) {
                $skipped[] = ['id' => $id, 'label' => $label, 'reason' => self::reason($exception)];
            } catch (Throwable $exception) {
                report($exception);

                $skipped[] = ['id' => $id, 'label' => $label, 'reason' => 'Gagal, coba lagi'];
            }
        }

        return ['verified' => $verified, 'skipped' => $skipped];
    }

    private static function reason(ValidationException $exception): string
    {
        $message = (string) collect($exception->errors())->flatten()->first();

        return rtrim(lcfirst($message), '.');
    }
}
