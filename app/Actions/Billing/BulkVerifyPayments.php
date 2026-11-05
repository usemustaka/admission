<?php

namespace App\Actions\Billing;

use App\Models\Payment;
use App\Models\User;
use App\Support\Money;
use Illuminate\Validation\ValidationException;

class BulkVerifyPayments
{
    public function __construct(private readonly VerifyPayment $verifyPayment) {}

    /**
     * Verify each payment as it was submitted. Every payment stands on its own: one that cannot be verified (already
     * handled, an item that cannot take the money any more) is skipped with its reason and never blocks the rest.
     *
     * @param  list<int>  $ids
     * @return array{verified: int, skipped: list<array{id: int, label: string, reason: string}>}
     */
    public function handle(array $ids, User $verifier): array
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

            try {
                $this->verifyPayment->handle($payment, $verifier);
                $verified++;
            } catch (ValidationException $exception) {
                $skipped[] = [
                    'id' => $id,
                    'label' => "{$payment->payer->name} ".Money::format($payment->amount),
                    'reason' => self::reason($exception),
                ];
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
