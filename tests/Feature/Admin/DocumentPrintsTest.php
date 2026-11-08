<?php

use App\Enums\BillType;
use App\Enums\DefaultRole;
use App\Enums\RegistrantStatus;
use App\Models\Bill;
use App\Models\BillItem;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Models\Setting;
use App\Support\AdmissionLetter;
use App\Support\SettlementReceipt;
use Inertia\Testing\AssertableInertia;

beforeEach(function () {
    $this->withoutVite();
    $this->admin = userWithRole(DefaultRole::Admin);
    $this->actingAs($this->admin);
});

describe('admission letter', function () {
    test('it is addressed to the family contact and says the child was accepted, without a letter number', function () {
        Setting::put('foundation.name', 'Yayasan Harapan');
        $registrant = admissionRegistrant(RegistrantStatus::Validated, ['exam_scores' => [80, 75]]);

        $this->get(route('admin.registrants.letter', $registrant))->assertInertia(fn (AssertableInertia $page) => $page
            ->component('letters/admission')
            ->where('letter.child.name', $registrant->person->name)
            ->where('letter.subject', 'Pemberitahuan Hasil Seleksi Penerimaan Siswa Baru')
            ->where('letter.recipient', fn ($recipient) => str_starts_with($recipient, 'Bapak/Ibu '))
            ->where('letter.paragraphs.0', fn ($text) => str_contains($text, 'DITERIMA') && str_contains($text, $registrant->person->name))
            ->missing('letter.number'));
    });

    test('the letter mentions what is still owed when a bill is open and says nothing is owed when paid', function () {
        $registrant = admissionRegistrant(RegistrantStatus::Announced, ['exam_scores' => [80, 75]]);
        $bill = Bill::factory()->create(['registrant_id' => $registrant->id, 'type' => BillType::Settlement]);
        BillItem::factory()->create(['bill_id' => $bill->id, 'amount' => 1500000]);

        expect(AdmissionLetter::data($registrant)['paragraphs'][1])->toContain('Rp1.500.000');

        verifiedPayment($bill->items()->first(), 1500000);

        expect(AdmissionLetter::data($registrant->refresh())['paragraphs'][1])->toContain('terselesaikan');
    });

    test('it is refused for a child who did not pass, has no scores yet, or whose data is not valid yet', function () {
        $failed = admissionRegistrant(RegistrantStatus::Validated, ['exam_scores' => [10, 10]]);
        $noScores = admissionRegistrant(RegistrantStatus::Validated);
        $review = admissionRegistrant(RegistrantStatus::Review, ['exam_scores' => [80, 80]]);
        $flagged = admissionRegistrant(RegistrantStatus::Validated, ['exam_scores' => [80, 80], 'needs_identity_review' => true]);

        foreach ([$failed, $noScores, $review, $flagged] as $registrant) {
            expect(AdmissionLetter::canPrint($registrant))->toBeFalse();
            $this->get(route('admin.registrants.letter', $registrant))->assertNotFound();
        }
    });

    test('a registration without exam aspects counts as accepted', function () {
        $registrant = admissionRegistrant(RegistrantStatus::Validated);
        $registrant->update(['ruleset_snapshot' => ['ruleset_id' => 1, 'name' => 'A', 'bills' => [], 'exam_aspects' => []]]);

        expect(AdmissionLetter::canPrint($registrant))->toBeTrue();
    });

    test('only staff who may announce can print it', function () {
        $registrant = admissionRegistrant(RegistrantStatus::Validated, ['exam_scores' => [80, 80]]);

        $this->actingAs(registrantViewer())->get(route('admin.registrants.letter', $registrant))->assertForbidden();
        $this->actingAs(userWithRole(DefaultRole::TestCommittee))->get(route('admin.registrants.letter', $registrant))->assertOk();
        $this->actingAs(userWithRole(DefaultRole::User))->get(route('admin.registrants.letter', $registrant))->assertForbidden();
    });
});

describe('settlement receipt', function () {
    beforeEach(function () {
        $this->registrant = admissionRegistrant(RegistrantStatus::Validated);
        $this->bill = Bill::factory()->create(['registrant_id' => $this->registrant->id, 'type' => BillType::Settlement]);
        $this->tuition = BillItem::factory()->create(['bill_id' => $this->bill->id, 'name' => 'Uang pangkal', 'amount' => 1000000, 'sort_order' => 0]);
        $this->uniform = BillItem::factory()->create(['bill_id' => $this->bill->id, 'name' => 'Seragam', 'amount' => 500000, 'sort_order' => 1]);
    });

    test('it is not available while anything is owed, and the page says so', function () {
        verifiedPayment($this->tuition, 1000000);

        expect(SettlementReceipt::isAvailable($this->registrant))->toBeFalse();
        $this->get(route('admin.registrants.settlement-receipt', $this->registrant))->assertNotFound();
    });

    test('once everything is paid it lists every item and every payment, scholarship included', function () {
        verifiedPayment($this->tuition, 1000000);
        $scholarship = Payment::factory()->create(['amount' => 500000, 'status' => 'verified', 'method' => 'scholarship', 'payer_person_id' => $this->registrant->registered_by_person_id]);
        PaymentAllocation::factory()->create(['payment_id' => $scholarship->id, 'bill_item_id' => $this->uniform->id, 'amount' => 500000]);

        $this->get(route('admin.registrants.settlement-receipt', $this->registrant))->assertInertia(fn (AssertableInertia $page) => $page
            ->component('payments/settlement-receipt')
            ->where('receipt.total', 1500000)
            ->where('receipt.paid', 1500000)
            ->where('receipt.total_words', fn ($words) => str_contains($words, 'Satu juta lima ratus ribu'))
            ->has('receipt.items', 2)
            ->has('receipt.payments', 2)
            ->where('receipt.payments.1.method', 'Beasiswa')
            ->where('receipt.number', fn ($number) => preg_match('/^LNS-\d{4}-\d{6}$/', $number) === 1));
    });

    test('a pending payment does not make it available and pending money is not listed', function () {
        verifiedPayment($this->tuition, 1000000);
        $pending = Payment::factory()->create(['amount' => 500000, 'status' => 'pending']);
        PaymentAllocation::factory()->create(['payment_id' => $pending->id, 'bill_item_id' => $this->uniform->id, 'amount' => 500000]);

        expect(SettlementReceipt::isAvailable($this->registrant))->toBeFalse();
    });

    test('a registration that was never billed has no receipt', function () {
        expect(SettlementReceipt::isAvailable(admissionRegistrant()))->toBeFalse();
    });

    test('staff who may not see payments cannot open it', function () {
        verifiedPayment($this->tuition, 1000000);
        verifiedPayment($this->uniform, 500000);

        $this->actingAs(registrantViewer())->get(route('admin.registrants.settlement-receipt', $this->registrant))->assertForbidden();
        $this->actingAs(userWithRole(DefaultRole::FinanceCommittee))->get(route('admin.registrants.settlement-receipt', $this->registrant))->assertOk();
    });
});
