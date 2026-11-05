<?php

use App\Enums\BillType;
use App\Enums\DefaultRole;
use App\Enums\ParentRelation;
use App\Enums\PaymentStatus;
use App\Enums\RegistrantStatus;
use App\Models\Bill;
use App\Models\BillItem;
use App\Models\Parentship;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Models\Person;
use App\Models\Registrant;
use App\Models\Student;

beforeEach(function () {
    $this->actingAs(userWithRole(DefaultRole::Operator));
});

/**
 * @param  array<string, mixed>  $person
 * @param  array<string, mixed>  $attributes
 */
function listedRegistrant(array $person = [], array $attributes = []): Registrant
{
    return Registrant::factory()->create([
        'person_id' => Person::factory()->create($person)->id,
        ...$attributes,
    ]);
}

/**
 * @return list<int>
 */
function listedIds(mixed $test, array $query): array
{
    $ids = [];

    $test->get(route('admin.registrants.index', $query))->assertInertia(function ($page) use (&$ids) {
        $ids = collect($page->toArray()['props']['registrants']['data'])->pluck('id')->sort()->values()->all();

        return $page;
    });

    return $ids;
}

test('the page size is one of 25, 50, 100 or 200 and defaults to 25', function () {
    Registrant::factory()->count(60)->create();

    $this->get(route('admin.registrants.index'))->assertInertia(fn ($page) => $page
        ->where('registrants.per_page', 25)
        ->where('filters.per_halaman', 25)
        ->where('options.per_page', [25, 50, 100, 200])
        ->has('registrants.data', 25));

    $this->get(route('admin.registrants.index', ['per_halaman' => 50]))->assertInertia(fn ($page) => $page
        ->where('registrants.per_page', 50)
        ->where('filters.per_halaman', 50)
        ->has('registrants.data', 50));

    foreach ([7, 1000, 0, -5, 'banyak'] as $invalid) {
        $this->get(route('admin.registrants.index', ['per_halaman' => $invalid]))->assertInertia(fn ($page) => $page->where('registrants.per_page', 25));
    }
});

test('the page size is kept in the links to the other pages', function () {
    Registrant::factory()->count(60)->create();

    $this->get(route('admin.registrants.index', ['per_halaman' => 50]))->assertInertia(fn ($page) => $page
        ->where('registrants.next_page_url', fn ($url) => str_contains($url, 'per_halaman=50')));
});

test('the search finds the child by the name on record or the name the parent typed', function () {
    $onRecord = listedRegistrant(['name' => 'Zahra Ananda Putri'], ['submitted_name' => 'Zahra A']);
    $typed = listedRegistrant(['name' => 'Siapa Saja'], ['submitted_name' => 'Kinanti Dewi Lestari']);
    listedRegistrant();

    expect(listedIds($this, ['cari' => 'ananda']))->toBe([$onRecord->id])
        ->and(listedIds($this, ['cari' => 'kinanti dewi']))->toBe([$typed->id]);
});

test('the search finds the child through the names and phone numbers of the parents', function () {
    $byName = listedRegistrant();
    Parentship::factory()->create(['person_id' => $byName->person_id, 'related_person_id' => Person::factory()->create(['name' => 'Sutrisno Hadi', 'phone' => '0811000111'])->id]);
    $byPhone = listedRegistrant();
    Parentship::factory()->create(['person_id' => $byPhone->person_id, 'relation' => ParentRelation::Mother, 'related_person_id' => Person::factory()->create(['name' => 'Ibu Lain', 'phone' => '081299990000'])->id]);
    listedRegistrant();

    expect(listedIds($this, ['cari' => 'sutrisno']))->toBe([$byName->id])
        ->and(listedIds($this, ['cari' => '08129999']))->toBe([$byPhone->id])
        ->and(listedIds($this, ['cari' => '99990000']))->toBe([$byPhone->id]);
});

test('the search matches a NIK, NISN or NIS only from its beginning', function () {
    $byNik = listedRegistrant(['nik' => '3277123456789012']);
    $byNisn = listedRegistrant(['nik' => '3278000000000001', 'nisn' => '0012345678']);
    $student = listedRegistrant(['nik' => '3279000000000002']);
    Student::factory()->create(['person_id' => $student->person_id, 'nis' => '24001234']);

    expect(listedIds($this, ['cari' => '32771234']))->toBe([$byNik->id])
        ->and(listedIds($this, ['cari' => '56789012']))->toBe([])
        ->and(listedIds($this, ['cari' => '00123']))->toBe([$byNisn->id])
        ->and(listedIds($this, ['cari' => '2400123']))->toBe([$student->id]);
});

test('percent, underscore and exclamation marks in the search only match themselves', function () {
    $percent = listedRegistrant(['name' => 'Diskon 100% Anak']);
    $plain = listedRegistrant(['name' => 'Diskon 1000 Anak']);
    $underscore = listedRegistrant(['name' => 'Budi_A']);
    $letter = listedRegistrant(['name' => 'BudiXA']);
    $bang = listedRegistrant(['name' => 'Wow! Anak']);
    $typedPercent = listedRegistrant(attributes: ['submitted_name' => '50% lulus']);

    expect(listedIds($this, ['cari' => '100%']))->toBe([$percent->id])
        ->and(listedIds($this, ['cari' => 'Budi_A']))->toBe([$underscore->id])
        ->and(listedIds($this, ['cari' => 'Wow!']))->toBe([$bang->id])
        ->and(listedIds($this, ['cari' => '%']))->toBe(collect([$percent->id, $typedPercent->id])->sort()->values()->all())
        ->and(listedIds($this, ['cari' => '_']))->toBe([$underscore->id])
        ->and(listedIds($this, ['cari' => 'Diskon 100']))->toBe([$percent->id, $plain->id]);

    expect($letter->id)->not->toBeIn(listedIds($this, ['cari' => 'Budi_A']));
});

test('the search combines with the other filters and the status counts', function () {
    $reviewing = listedRegistrant(['name' => 'Rahma Aulia'], ['status' => RegistrantStatus::Review]);
    listedRegistrant(['name' => 'Rahma Lain'], ['status' => RegistrantStatus::Draft]);

    $this->get(route('admin.registrants.index', ['cari' => 'rahma', 'status' => 'review']))->assertInertia(fn ($page) => $page
        ->has('registrants.data', 1)
        ->where('registrants.data.0.id', $reviewing->id)
        ->where('statusCounts.all', 2)
        ->where('statusCounts.draft', 1));
});

test('registrants can be sorted by how long they have waited since they were sent in', function () {
    $oldest = listedRegistrant(attributes: ['status' => RegistrantStatus::Review, 'submitted_at' => now()->subDays(3)]);
    $newest = listedRegistrant(attributes: ['status' => RegistrantStatus::Review, 'submitted_at' => now()->subDay()]);
    $never = listedRegistrant(attributes: ['status' => RegistrantStatus::Draft]);

    $this->get(route('admin.registrants.index', ['urut' => 'dikirim']))->assertInertia(fn ($page) => $page
        ->where('filters.urut', 'dikirim')
        ->where('filters.arah', 'asc')
        ->where('registrants.data', fn ($rows) => collect($rows)->pluck('id')->all() === [$oldest->id, $newest->id, $never->id])
        ->where('registrants.data.0.submitted_at', $oldest->submitted_at->toIso8601String())
        ->where('registrants.data.2.submitted_at', null));

    $this->get(route('admin.registrants.index', ['urut' => 'dikirim', 'arah' => 'desc']))->assertInertia(fn ($page) => $page
        ->where('registrants.data', fn ($rows) => collect($rows)->pluck('id')->all() === [$newest->id, $oldest->id, $never->id]));
});

test('an unknown sort key falls back to the newest registration first', function () {
    $older = listedRegistrant(attributes: ['created_at' => now()->subDay()]);
    $newer = listedRegistrant();

    $this->get(route('admin.registrants.index', ['urut' => 'password; drop table', 'per_halaman' => 25]))->assertInertia(fn ($page) => $page
        ->where('filters.urut', 'terbaru')
        ->where('filters.arah', 'desc')
        ->where('registrants.data.0.id', $newer->id)
        ->where('registrants.data.1.id', $older->id));
});

test('the payment filter finds registrants with a proof waiting for verification', function () {
    $waiting = listedRegistrant();
    $verified = listedRegistrant();
    $rejected = listedRegistrant();
    $withoutPayment = listedRegistrant();

    foreach ([[$waiting, PaymentStatus::Pending, 75000], [$verified, PaymentStatus::Verified, 100000], [$rejected, PaymentStatus::Rejected, 100000], [$withoutPayment, null, 0]] as [$registrant, $status, $amount]) {
        $item = BillItem::factory()->create(['bill_id' => Bill::factory()->create(['registrant_id' => $registrant->id, 'type' => BillType::Registration])->id, 'amount' => 100000]);

        if ($status !== null) {
            PaymentAllocation::factory()->create(['payment_id' => Payment::factory()->create(['amount' => $amount, 'status' => $status])->id, 'bill_item_id' => $item->id, 'amount' => $amount]);
        }
    }

    $this->get(route('admin.registrants.index', ['bayar' => 'menunggu']))->assertInertia(fn ($page) => $page
        ->has('registrants.data', 1)
        ->where('registrants.data.0.id', $waiting->id)
        ->where('registrants.data.0.payment_pending', 75000)
        ->where('filters.bayar', 'menunggu'));

    $this->get(route('admin.registrants.index'))->assertInertia(fn ($page) => $page
        ->where('registrants.data', fn ($rows) => collect($rows)->firstWhere('id', $verified->id)['payment_pending'] === 0));

    expect(listedIds($this, ['bayar' => 'lunas']))->toBe([$verified->id]);
});
