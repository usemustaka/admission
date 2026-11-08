<?php

namespace App\Support;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\RegistrantStatus;
use App\Enums\ScholarshipKind;
use App\Models\Bill;
use App\Models\BillItem;
use App\Models\Payment;
use App\Models\Registrant;

/**
 * What the committee needs to weigh and grant a scholarship for one registration: the kinds the evidence points at,
 * what is still owed per bill item, and the scholarships already granted.
 */
class ScholarshipPresenter
{
    /**
     * @return array{suggested: list<string>, kinds: list<array{value: string, label: string}>, items: list<array{bill_item_id: int, label: string, outstanding: int}>, granted: list<array{id: int, amount: int, date: string, note: string|null}>, blocked: string|null}
     */
    public static function for(Registrant $registrant): array
    {
        $bills = $registrant->bills()->with(['items' => fn ($items) => $items->withPaymentTotals()])->orderBy('issued_at')->orderBy('id')->get();

        $items = [];

        foreach ($bills as $bill) {
            /** @var Bill $bill */
            foreach ($bill->items as $item) {
                /** @var BillItem $item */
                $outstanding = $item->outstandingAmount(reservePending: false);

                if ($outstanding > 0) {
                    $items[] = ['bill_item_id' => $item->id, 'label' => "Tagihan {$bill->type->label()}: {$item->name}", 'outstanding' => $outstanding];
                }
            }
        }

        $granted = Payment::query()
            ->where('method', PaymentMethod::Scholarship)
            ->where('status', PaymentStatus::Verified)
            ->whereHas('allocations.billItem.bill', fn ($bill) => $bill->where('registrant_id', $registrant->id))
            ->latest('id')
            ->get()
            ->map(fn (Payment $payment): array => [
                'id' => $payment->id,
                'amount' => $payment->amount,
                'date' => $payment->paid_on->toDateString(),
                'note' => $payment->note,
            ])
            ->values()
            ->all();

        return [
            'suggested' => array_map(fn (ScholarshipKind $kind): string => $kind->value, RegistrantDocuments::scholarshipKinds($registrant)),
            'kinds' => array_map(fn (ScholarshipKind $kind): array => ['value' => $kind->value, 'label' => $kind->label()], ScholarshipKind::cases()),
            'items' => $items,
            'granted' => $granted,
            'blocked' => match (true) {
                $registrant->status === RegistrantStatus::Placed => 'Anak ini sudah ditempatkan, beasiswa tidak bisa ditambahkan lagi.',
                $registrant->needs_identity_review => 'Selesaikan dulu pencocokan NIK dan nama anak.',
                $items === [] => 'Tidak ada tagihan yang masih harus dibayar.',
                default => null,
            },
        ];
    }
}
