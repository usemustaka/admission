<?php

use App\Enums\BillType;
use App\Enums\DefaultRole;
use App\Enums\RegistrantStatus;
use App\Models\Bill;
use App\Models\BillItem;
use App\Models\Organization;
use App\Models\Parentship;
use App\Models\Person;
use App\Models\Registrant;

beforeEach(function () {
    $this->actingAs(userWithRole(DefaultRole::Operator));
});

/**
 * @return array{0: Registrant, 1: BillItem}
 */
function registrantWithBill(RegistrantStatus $status = RegistrantStatus::Draft, int $amount = 100000): array
{
    $registrant = Registrant::factory()->create(['status' => $status]);
    $bill = Bill::factory()->create(['registrant_id' => $registrant->id, 'type' => BillType::Registration]);

    return [$registrant, BillItem::factory()->create(['bill_id' => $bill->id, 'amount' => $amount])];
}

test('the payment filter separates paid registrants from those with an open bill', function () {
    [$paid, $paidItem] = registrantWithBill();
    verifiedPayment($paidItem, 100000);
    [$partial, $partialItem] = registrantWithBill();
    verifiedPayment($partialItem, 40000);
    [$unpaid] = registrantWithBill();
    $withoutBill = Registrant::factory()->create();

    $this->get(route('admin.registrants.index', ['bayar' => 'lunas']))->assertInertia(fn ($page) => $page
        ->has('registrants.data', 1)
        ->where('registrants.data.0.id', $paid->id)
        ->where('registrants.data.0.payment', 'paid'));

    $this->get(route('admin.registrants.index', ['bayar' => 'belum']))->assertInertia(fn ($page) => $page
        ->has('registrants.data', 2)
        ->where('registrants.data', fn ($rows) => collect($rows)->pluck('id')->sort()->values()->all() === collect([$partial->id, $unpaid->id])->sort()->values()->all()));

    $this->get(route('admin.registrants.index'))->assertInertia(fn ($page) => $page
        ->where('registrants.data', fn ($rows) => collect($rows)->firstWhere('id', $partial->id)['payment'] === 'partial'
            && collect($rows)->firstWhere('id', $unpaid->id)['payment'] === 'unpaid'
            && collect($rows)->firstWhere('id', $withoutBill->id)['payment'] === 'none'));
});

test('payments that are only waiting for verification do not count as paid', function () {
    [$registrant, $item] = registrantWithBill();
    $payment = verifiedPayment($item, 100000);
    $payment->update(['status' => 'pending']);

    $this->get(route('admin.registrants.index', ['bayar' => 'lunas']))->assertInertia(fn ($page) => $page->has('registrants.data', 0));
    $this->get(route('admin.registrants.index', ['bayar' => 'belum']))->assertInertia(fn ($page) => $page->has('registrants.data', 1));
});

test('the exam filter finds registrants who still have to pick a session', function () {
    $waiting = admissionRegistrant();
    examSessionFor($waiting);
    $chosen = admissionRegistrant(attributes: ['exam_session_id' => examSessionFor($waiting)->id]);
    admissionRegistrant();

    $this->get(route('admin.registrants.index', ['belum_ujian' => 1]))->assertInertia(fn ($page) => $page
        ->has('registrants.data', 1)
        ->where('registrants.data.0.id', $waiting->id)
        ->where('filters.belum_ujian', true));

    expect($chosen->exam_session_id)->not->toBeNull();
});

test('status counts follow the other filters but not the status filter', function () {
    $school = Organization::factory()->create();
    Registrant::factory()->count(2)->create(['status' => RegistrantStatus::Review, 'organization_id' => $school->id]);
    Registrant::factory()->create(['status' => RegistrantStatus::Validated, 'organization_id' => $school->id]);
    Registrant::factory()->count(3)->create(['status' => RegistrantStatus::Review]);

    $this->get(route('admin.registrants.index', ['lembaga' => $school->id, 'status' => 'validated']))->assertInertia(fn ($page) => $page
        ->has('registrants.data', 1)
        ->where('statusCounts.all', 3)
        ->where('statusCounts.review', 2)
        ->where('statusCounts.validated', 1)
        ->where('statusCounts.draft', 0)
        ->where('counts.in_review', 5)
        ->where('counts.validated', 1)
        ->where('counts.announced', 0));
});

test('the list tells which filters are in use and reads the combined filters', function () {
    $flagged = Registrant::factory()->create(['needs_identity_review' => true, 'status' => RegistrantStatus::Review]);
    Registrant::factory()->create(['status' => RegistrantStatus::Review]);

    $this->get(route('admin.registrants.index', ['status' => 'review', 'perlu_dicek' => 1]))->assertInertia(fn ($page) => $page
        ->has('registrants.data', 1)
        ->where('registrants.data.0.id', $flagged->id)
        ->where('filters.status', 'review')
        ->where('filters.perlu_dicek', true));
});

test('each row carries the primary contact as a WhatsApp link', function () {
    $registrant = Registrant::factory()->create();
    $contact = Person::factory()->create(['name' => 'Bu Siti', 'phone' => '081234567890']);
    Parentship::factory()->primaryContact()->create(['person_id' => $registrant->person_id, 'related_person_id' => $contact->id]);

    $this->get(route('admin.registrants.index'))->assertInertia(fn ($page) => $page
        ->where('registrants.data.0.contact.name', 'Bu Siti')
        ->where('registrants.data.0.contact.whatsapp_url', 'https://wa.me/6281234567890'));
});

test('a registrant whose NIK is unconfirmed is contacted through the parent who registered them', function () {
    $registrant = Registrant::factory()->create([
        'needs_identity_review' => true,
        'registered_by_person_id' => Person::factory()->create(['name' => 'Pak Budi', 'phone' => '0899000111'])->id,
    ]);
    Parentship::factory()->primaryContact()->create(['person_id' => $registrant->person_id, 'related_person_id' => Person::factory()->create(['phone' => '0811111111'])->id]);

    $this->get(route('admin.registrants.index'))->assertInertia(fn ($page) => $page
        ->where('registrants.data.0.contact.name', 'Pak Budi')
        ->where('registrants.data.0.contact.whatsapp_url', 'https://wa.me/62899000111'));
});

test('the list loads the same number of queries however many registrants it shows', function () {
    $fill = function (int $count): void {
        foreach (range(1, $count) as $ignored) {
            $registrant = admissionRegistrant();
            $registrant->update(['exam_session_id' => examSessionFor($registrant)->id]);
            [, $item] = registrantWithBill();
            Bill::query()->whereKey($item->bill_id)->update(['registrant_id' => $registrant->id]);
        }
    };
    $queries = function (): int {
        DB::flushQueryLog();
        test()->get(route('admin.registrants.index'))->assertOk();

        return count(DB::getQueryLog());
    };

    $fill(2);
    DB::enableQueryLog();
    $queries();
    $few = $queries();

    $fill(5);

    expect($queries())->toBe($few);
});

test('the detail knows where a registrant sits in the queue of its status', function () {
    $first = Registrant::factory()->create(['status' => RegistrantStatus::Review, 'created_at' => now()->subDays(3)]);
    $second = Registrant::factory()->create(['status' => RegistrantStatus::Review, 'created_at' => now()->subDays(2)]);
    $third = Registrant::factory()->create(['status' => RegistrantStatus::Review, 'created_at' => now()->subDay()]);
    Registrant::factory()->create(['status' => RegistrantStatus::Draft, 'created_at' => now()->subDays(2)]);

    $this->get(route('admin.registrants.show', $second))->assertInertia(fn ($page) => $page
        ->where('queue', ['position' => 2, 'total' => 3, 'previous_id' => $first->id, 'next_id' => $third->id]));

    $this->get(route('admin.registrants.show', $first))->assertInertia(fn ($page) => $page
        ->where('queue.previous_id', null)
        ->where('queue.next_id', $second->id));

    $this->get(route('admin.registrants.show', $third))->assertInertia(fn ($page) => $page
        ->where('queue.next_id', null));
});
