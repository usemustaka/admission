<?php

use App\Enums\Ability;
use App\Enums\BillType;
use App\Enums\DefaultRole;
use App\Enums\PaymentStatus;
use App\Models\Bill;
use App\Models\BillItem;
use App\Models\Organization;
use App\Models\Parentship;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Models\Person;
use App\Models\Registrant;
use App\Models\Setting;
use App\Models\User;
use App\Models\Wave;
use App\Support\Brand;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Carbon;

/**
 * A registrant (child named `$name`) whose bill, issued at `$issuedAt`, has items of the given amounts, and a
 * parent with a phone number.
 *
 * @param  list<int>  $amounts
 * @param  array<string, mixed>  $registrantAttributes
 */
function owingChild(string $name, array $amounts, string $issuedAt = '2026-11-05 09:00:00', array $registrantAttributes = [], BillType $type = BillType::Registration): array
{
    $registrant = Registrant::factory()->create($registrantAttributes);
    $registrant->person->update(['name' => $name]);
    Parentship::factory()->primaryContact()->create(['person_id' => $registrant->person_id, 'related_person_id' => Person::factory()->create(['phone' => '0812-3456-7890'])->id]);
    $bill = Bill::factory()->create(['registrant_id' => $registrant->id, 'type' => $type, 'issued_at' => $issuedAt]);
    $items = collect($amounts)->map(fn (int $amount, int $index) => BillItem::factory()->create(['bill_id' => $bill->id, 'name' => "Rincian {$index}", 'amount' => $amount, 'sort_order' => $index]));

    return [$registrant, $bill, $items];
}

function allocatedPayment(BillItem $item, int $amount, PaymentStatus $status = PaymentStatus::Verified): Payment
{
    $payment = Payment::factory()->create(['amount' => $amount, 'status' => $status]);
    PaymentAllocation::factory()->create(['payment_id' => $payment->id, 'bill_item_id' => $item->id, 'amount' => $amount]);

    return $payment;
}

beforeEach(function () {
    $this->withoutVite();
    Brand::flush();
    Carbon::setTestNow('2026-11-05 12:00:00');
    $this->actingAs(userWithRole(DefaultRole::Admin));
});

describe('the rows', function () {
    test('add up billed, paid, waiting and owed money per registrant, with an overpaid item never covering another item', function () {
        [$registrant, , $items] = owingChild('Ahmad Fauzi', [100000, 50000]);
        allocatedPayment($items[0], 150000);
        allocatedPayment($items[1], 20000, PaymentStatus::Pending);
        allocatedPayment($items[1], 999000, PaymentStatus::Rejected);

        $this->get(route('admin.payments.arrears'))->assertOk()->assertInertia(fn ($page) => $page
            ->component('admin/payments/arrears')
            ->has('rows.data', 1)
            ->where('rows.data.0.id', $registrant->id)
            ->where('rows.data.0.child_name', 'Ahmad Fauzi')
            ->where('rows.data.0.billed', 150000)
            ->where('rows.data.0.paid', 150000)
            ->where('rows.data.0.pending', 20000)
            ->where('rows.data.0.outstanding', 50000)
            ->where('rows.data.0.excess', 50000)
            ->where('rows.data.0.types', ['Pendaftaran'])
            ->where('totals.all', ['count' => 1, 'outstanding' => 50000]));
    });

    test('agree with the bill of the registrant', function () {
        [$registrant, $bill, $items] = owingChild('Budi', [100000, 50000, 25000]);
        allocatedPayment($items[0], 130000);
        allocatedPayment($items[2], 5000);

        $row = $this->get(route('admin.payments.arrears'))->viewData('page')['props']['rows']['data'][0];
        $bill->loadTotals();

        expect($row['outstanding'])->toBe($bill->outstandingAmount())
            ->and($row['paid'])->toBe($bill->paidAmount());
    });

    test('leave out registrants who are paid up, have nothing billed, or have no bill at all', function () {
        [, , $paid] = owingChild('Lunas', [100000]);
        allocatedPayment($paid[0], 100000);
        owingChild('Gratis', [0]);
        Registrant::factory()->create();
        owingChild('Menunggak', [100000]);

        $this->get(route('admin.payments.arrears'))->assertInertia(fn ($page) => $page
            ->has('rows.data', 1)->where('rows.data.0.child_name', 'Menunggak')->where('totals.all.count', 1));
    });

    test('a payment that only waits for verification does not lower what is owed', function () {
        [, , $items] = owingChild('Menunggu', [100000]);
        allocatedPayment($items[0], 100000, PaymentStatus::Pending);

        $this->get(route('admin.payments.arrears'))->assertInertia(fn ($page) => $page
            ->where('rows.data.0.outstanding', 100000)->where('rows.data.0.pending', 100000));
    });

    test('list the kinds of bill that are still open and take the age from the oldest one', function () {
        [$registrant, $first, $items] = owingChild('Dua tagihan', [100000], '2026-09-20 08:00:00');
        allocatedPayment($items[0], 100000);
        $settlement = Bill::factory()->create(['registrant_id' => $registrant->id, 'type' => BillType::Settlement, 'issued_at' => '2026-10-25 08:00:00']);
        BillItem::factory()->create(['bill_id' => $settlement->id, 'amount' => 500000]);

        $this->get(route('admin.payments.arrears'))->assertInertia(fn ($page) => $page
            ->where('rows.data.0.types', ['Pelunasan'])
            ->where('rows.data.0.oldest_issued_on', '2026-10-25')
            ->where('rows.data.0.age_days', 11)
            ->where('rows.data.0.bucket', '8-30'));

        BillItem::factory()->create(['bill_id' => $first->id, 'amount' => 10000]);

        $this->get(route('admin.payments.arrears'))->assertInertia(fn ($page) => $page
            ->where('rows.data.0.types', ['Pendaftaran', 'Pelunasan'])
            ->where('rows.data.0.oldest_issued_on', '2026-09-20')
            ->where('rows.data.0.age_days', 46)
            ->where('rows.data.0.bucket', '31-60'));
    });
});

describe('the age buckets', function () {
    test('count calendar days in WIB from the issue date, so late on a day is still that day', function () {
        Carbon::setTestNow('2026-11-05 00:30:00');
        $cases = [
            'hari ini' => ['2026-11-05 00:10:00', 0, '0-7'],
            'tujuh hari, larut malam' => ['2026-10-29 23:59:00', 7, '0-7'],
            'delapan hari' => ['2026-10-28 23:59:00', 8, '8-30'],
            'tiga puluh hari' => ['2026-10-06 00:00:00', 30, '8-30'],
            'tiga puluh satu hari' => ['2026-10-05 23:59:00', 31, '31-60'],
            'enam puluh hari' => ['2026-09-06 00:00:00', 60, '31-60'],
            'enam puluh satu hari' => ['2026-09-05 23:59:00', 61, 'lebih-60'],
        ];

        foreach ($cases as $name => [$issuedAt]) {
            owingChild($name, [100000], $issuedAt);
        }

        $page = $this->get(route('admin.payments.arrears', ['urut' => 'nama', 'arah' => 'asc']))->viewData('page')['props'];
        $byName = collect($page['rows']['data'])->keyBy('child_name');

        foreach ($cases as $name => [, $age, $bucket]) {
            expect([$byName[$name]['age_days'], $byName[$name]['bucket']])->toBe([$age, $bucket], $name);
        }

        expect(array_map(fn (array $bucket): int => $bucket['count'], $page['totals']['buckets']))
            ->toBe(['0-7' => 2, '8-30' => 2, '31-60' => 2, 'lebih-60' => 1])
            ->and($page['totals']['all']['count'])->toBe(7);
    });

    test('filter the rows by bucket while the counts and sums keep describing every bucket', function () {
        owingChild('Baru', [100000], '2026-11-04 08:00:00');
        owingChild('Sedang', [250000], '2026-10-20 08:00:00');
        owingChild('Lama satu', [300000], '2026-08-01 08:00:00');
        owingChild('Lama dua', [400000], '2026-07-01 08:00:00');

        $this->get(route('admin.payments.arrears', ['umur' => 'lebih-60']))->assertInertia(fn ($page) => $page
            ->has('rows.data', 2)
            ->where('totals.buckets.lebih-60', ['count' => 2, 'outstanding' => 700000])
            ->where('totals.buckets.8-30', ['count' => 1, 'outstanding' => 250000])
            ->where('totals.buckets.0-7', ['count' => 1, 'outstanding' => 100000])
            ->where('totals.buckets.31-60', ['count' => 0, 'outstanding' => 0])
            ->where('totals.all', ['count' => 4, 'outstanding' => 1050000])
            ->where('filters.umur', 'lebih-60'));

        $this->get(route('admin.payments.arrears', ['umur' => 'bukan-kelompok']))->assertInertia(fn ($page) => $page->has('rows.data', 4)->where('filters.umur', ''));
    });
});

describe('filters, order and paging', function () {
    test('narrow by lembaga, gelombang and the name of the child, and the totals follow', function () {
        $organization = Organization::factory()->create();
        $wave = Wave::factory()->create();
        owingChild('Siti Aisyah', [100000], registrantAttributes: ['organization_id' => $organization->id, 'wave_id' => $wave->id]);
        owingChild('Siti Nur', [200000], registrantAttributes: ['organization_id' => $organization->id]);
        owingChild('Budi Santoso', [400000]);

        $this->get(route('admin.payments.arrears', ['lembaga' => $organization->id]))->assertInertia(fn ($page) => $page
            ->has('rows.data', 2)->where('totals.all', ['count' => 2, 'outstanding' => 300000]));
        $this->get(route('admin.payments.arrears', ['lembaga' => $organization->id, 'gelombang' => $wave->id]))->assertInertia(fn ($page) => $page
            ->has('rows.data', 1)->where('rows.data.0.child_name', 'Siti Aisyah'));
        $this->get(route('admin.payments.arrears', ['cari' => 'santoso']))->assertInertia(fn ($page) => $page
            ->has('rows.data', 1)->where('rows.data.0.child_name', 'Budi Santoso'));
    });

    test('search treats percent and underscore as plain characters', function () {
        owingChild('Anak 100% Baik', [100000]);
        owingChild('Anak Biasa', [100000]);

        $this->get(route('admin.payments.arrears', ['cari' => '%']))->assertInertia(fn ($page) => $page->has('rows.data', 1)->where('rows.data.0.child_name', 'Anak 100% Baik'));
        $this->get(route('admin.payments.arrears', ['cari' => '_']))->assertInertia(fn ($page) => $page->has('rows.data', 0));
    });

    test('sort by what is owed, by age or by name', function () {
        owingChild('Cici', [300000], '2026-10-30 08:00:00');
        owingChild('Ani', [100000], '2026-08-01 08:00:00');
        owingChild('Budi', [200000], '2026-11-04 08:00:00');
        $names = fn (array $query) => collect($this->get(route('admin.payments.arrears', $query))->viewData('page')['props']['rows']['data'])->pluck('child_name')->all();

        expect($names([]))->toBe(['Cici', 'Budi', 'Ani'])
            ->and($names(['urut' => 'sisa', 'arah' => 'asc']))->toBe(['Ani', 'Budi', 'Cici'])
            ->and($names(['urut' => 'umur']))->toBe(['Ani', 'Cici', 'Budi'])
            ->and($names(['urut' => 'umur', 'arah' => 'asc']))->toBe(['Budi', 'Cici', 'Ani'])
            ->and($names(['urut' => 'nama']))->toBe(['Ani', 'Budi', 'Cici'])
            ->and($names(['urut' => 'nama', 'arah' => 'desc']))->toBe(['Cici', 'Budi', 'Ani']);
    });

    test('are paged on the server with totals over every page', function () {
        foreach (range(1, 30) as $number) {
            owingChild("Anak {$number}", [10000]);
        }

        $this->get(route('admin.payments.arrears'))->assertInertia(fn ($page) => $page
            ->has('rows.data', 25)->where('rows.total', 30)->where('totals.all', ['count' => 30, 'outstanding' => 300000]));
        $this->get(route('admin.payments.arrears', ['page' => 2]))->assertInertia(fn ($page) => $page->has('rows.data', 5));
    });
});

describe('the reminder', function () {
    test('opens WhatsApp with the template filled in and the amount owed', function () {
        Setting::put('billing.reminder_template', 'Tagihan {nama}: {sisa}');
        [, , $items] = owingChild('Ahmad Fauzi', [100000, 50000]);
        allocatedPayment($items[0], 30000);

        $url = $this->get(route('admin.payments.arrears'))->viewData('page')['props']['rows']['data'][0]['whatsapp_url'];

        expect($url)->toStartWith('https://wa.me/6281234567890?text=')
            ->and(urldecode(explode('?text=', $url)[1]))->toBe('Tagihan Ahmad Fauzi: Rp120.000')
            ->and($url)->toContain(rawurlencode('Rp120.000'));
    });

    test('is missing when nobody has a phone number', function () {
        [$registrant] = owingChild('Tanpa HP', [100000]);
        Person::query()->update(['phone' => null]);

        $this->get(route('admin.payments.arrears'))->assertInertia(fn ($page) => $page->where('rows.data.0.whatsapp_url', null));
    });
});

describe('the export', function () {
    test('lists the rows of the filters as CSV with safe cells', function () {
        owingChild('=HYPERLINK("x")', [100000]);
        owingChild('Budi', [200000]);

        $response = $this->get(route('admin.payments.arrears.export', ['cari' => 'Budi']))->assertOk();
        $csv = $response->streamedContent();

        expect($response->headers->get('content-type'))->toContain('text/csv')
            ->and($csv)->toContain('Budi')->toContain('200000')->not->toContain('HYPERLINK');

        $all = $this->get(route('admin.payments.arrears.export'))->streamedContent();

        expect($all)->toContain("'=HYPERLINK")->toContain('Pendaftaran')->toContain('Sisa (Rp)');
    });

    test('needs the export ability on top of seeing payments', function () {
        $this->seed(RolesAndPermissionsSeeder::class);
        $viewer = tap(User::factory()->create())->givePermissionTo([Ability::AdminAccess->value, Ability::PaymentView->value]);
        $exporterOnly = tap(User::factory()->create())->givePermissionTo([Ability::AdminAccess->value, Ability::PaymentExport->value]);

        $this->actingAs($viewer)->get(route('admin.payments.arrears.export'))->assertForbidden();
        $this->get(route('admin.payments.arrears'))->assertInertia(fn ($page) => $page->where('can.export', false));
        $this->actingAs($exporterOnly)->get(route('admin.payments.arrears.export'))->assertForbidden();
    });
});

describe('access', function () {
    test('needs the right to see payments', function () {
        $this->seed(RolesAndPermissionsSeeder::class);
        $staff = tap(User::factory()->create())->givePermissionTo([Ability::AdminAccess->value, Ability::RegistrantView->value]);

        $this->actingAs($staff)->get(route('admin.payments.arrears'))->assertForbidden();
        $this->actingAs(User::factory()->create())->get(route('admin.payments.arrears'))->assertForbidden();
    });

    test('guests are sent to log in', function () {
        auth()->logout();

        $this->get(route('admin.payments.arrears'))->assertRedirect(route('login'));
    });
});
