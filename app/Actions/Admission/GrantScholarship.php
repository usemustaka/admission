<?php

namespace App\Actions\Admission;

use App\Actions\Billing\CreatePayment;
use App\Actions\Billing\PaymentData;
use App\Actions\Notes\WritePersonAuditNote;
use App\Enums\PaymentMethod;
use App\Enums\RegistrantStatus;
use App\Enums\ScholarshipKind;
use App\Models\Payment;
use App\Models\Registrant;
use App\Models\User;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class GrantScholarship
{
    public function __construct(
        private CreatePayment $createPayment,
        private WritePersonAuditNote $writeAuditNote,
    ) {}

    /**
     * Grant a scholarship by adding a verified payment of the method "Beasiswa" to the bill items it covers. The bill
     * itself stays as it was: the scholarship shows up as money paid, so the amount owed and the history both stay
     * readable. A scholarship is granted at the committee's discretion, so nothing here checks the evidence the parent
     * gave. It can only cover what is still owed, and never for a child who is already placed.
     *
     * @param  list<array{bill_item_id: int, amount: int}>  $items
     *
     * @throws ValidationException
     */
    public function handle(Registrant $registrant, User $by, ScholarshipKind $kind, array $items, ?string $note = null): Payment
    {
        if ($registrant->needs_identity_review) {
            throw ValidationException::withMessages(['items' => 'Selesaikan dulu pencocokan NIK dan nama anak.']);
        }

        if ($registrant->status === RegistrantStatus::Placed) {
            throw ValidationException::withMessages(['items' => 'Anak ini sudah ditempatkan, beasiswa tidak bisa ditambahkan lagi.']);
        }

        $items = array_values(array_filter($items, fn (array $row): bool => $row['amount'] > 0));

        if ($items === []) {
            throw ValidationException::withMessages(['items' => 'Pilih minimal satu rincian tagihan dan isi nominalnya.']);
        }

        return DB::transaction(function () use ($registrant, $by, $kind, $items, $note): Payment {
            $payment = $this->createPayment->handle(new PaymentData(
                payerPersonId: $registrant->registered_by_person_id,
                paidOn: today()->toDateString(),
                method: PaymentMethod::Scholarship,
                items: $items,
                createdBy: $by,
                note: trim("Beasiswa {$kind->label()}. ".($note ?? '')),
                confirmDuplicate: true,
            ), verified: true);

            $this->writeAuditNote->handle($registrant->person, $by, "Beasiswa {$kind->label()} diberikan sebesar ".Money::format($payment->amount).'.');

            return $payment;
        });
    }
}
