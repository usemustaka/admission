<?php

namespace App\Actions\Billing;

use App\Enums\NoteUrgency;
use App\Enums\NoteVisibility;
use App\Models\Note;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Models\Person;

class WritePaymentAuditNote
{
    /**
     * Leave a system note (no author) about a payment correction on the payer and on every child the payment was
     * allocated to. Staff who may read notes see it on those people; the family never does.
     */
    public function handle(Payment $payment, string $text): void
    {
        $childIds = $payment->allocations()
            ->with('billItem.bill.registrant')
            ->get()
            ->map(fn (PaymentAllocation $allocation): int => $allocation->billItem->bill->registrant->person_id);

        $personIds = $childIds->push($payment->payer_person_id)->unique()->values();

        foreach ($personIds as $personId) {
            Note::create([
                'noteable_type' => (new Person)->getMorphClass(),
                'noteable_id' => $personId,
                'person_id' => $personId,
                'author_id' => null,
                'body' => $text,
                'urgency' => NoteUrgency::Normal,
                'visibility' => NoteVisibility::Public,
                'shared_with_family' => false,
            ]);
        }
    }
}
