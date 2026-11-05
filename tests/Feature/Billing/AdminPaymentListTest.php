<?php

use App\Enums\DefaultRole;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Models\Person;
use App\Models\User;
use Spatie\Permission\Models\Role;

/**
 * A payment from `$payer` to the first item of `$bill`, with the allocation matching its amount.
 *
 * @param  array<string, mixed>  $attributes
 */
function listedPayment(User|Person $payer, $item, int $amount, array $attributes = []): Payment
{
    $payment = Payment::factory()->create([
        'payer_person_id' => $payer instanceof User ? $payer->person_id : $payer->id,
        'amount' => $amount,
        ...$attributes,
    ]);
    PaymentAllocation::factory()->create(['payment_id' => $payment->id, 'bill_item_id' => $item->id, 'amount' => $amount]);

    return $payment;
}

function operatorWhoCannotVerify(): User
{
    $operator = userWithRole(DefaultRole::Operator);
    Role::findByName('operator')->revokePermissionTo('admission::payment.verify');

    return $operator->fresh();
}

beforeEach(function () {
    $this->admin = userWithRole(DefaultRole::Admin);
    $this->actingAs($this->admin);

    $this->parentA = User::factory()->create();
    $this->parentB = User::factory()->create();
    [$this->kidA, $this->billA] = billedChild($this->parentA, [150000, 50000]);
    [$this->kidB, $this->billB] = billedChild($this->parentB, [200000]);
    [$this->itemA1, $this->itemA2] = $this->billA->items->all();
    [$this->itemB1] = $this->billB->items->all();
});

describe('the list', function () {
    test('narrows by status, method and the dates the money was paid', function () {
        listedPayment($this->parentA, $this->itemA1, 100000, ['status' => PaymentStatus::Verified, 'method' => PaymentMethod::Cash, 'paid_on' => '2026-10-10']);
        listedPayment($this->parentA, $this->itemA2, 50000, ['status' => PaymentStatus::Pending, 'method' => PaymentMethod::Transfer, 'paid_on' => '2026-11-02']);
        listedPayment($this->parentB, $this->itemB1, 200000, ['status' => PaymentStatus::Rejected, 'method' => PaymentMethod::Transfer, 'paid_on' => '2026-11-03']);

        $this->get(route('admin.payments.index', ['cara' => 'cash']))
            ->assertInertia(fn ($page) => $page->has('payments.data', 1)->where('payments.data.0.amount', 100000)->where('filters.cara', 'cash'));

        $this->get(route('admin.payments.index', ['dari' => '2026-11-01', 'sampai' => '2026-11-02']))
            ->assertInertia(fn ($page) => $page->has('payments.data', 1)->where('payments.data.0.amount', 50000));

        $this->get(route('admin.payments.index', ['status' => 'rejected', 'dari' => '2026-11-01']))
            ->assertInertia(fn ($page) => $page->has('payments.data', 1)->where('payments.data.0.status', 'rejected'));
    });

    test('searches the payer and the child, even by part of the name', function () {
        $this->parentA->person->update(['name' => 'Siti Aminah']);
        $this->kidB->person->update(['name' => 'Budi Santoso']);
        listedPayment($this->parentA, $this->itemA1, 100000);
        listedPayment($this->parentB, $this->itemB1, 200000);

        $this->get(route('admin.payments.index', ['cari' => 'aminah']))
            ->assertInertia(fn ($page) => $page->has('payments.data', 1)->where('payments.data.0.payer', 'Siti Aminah'));

        $this->get(route('admin.payments.index', ['cari' => 'santoso']))
            ->assertInertia(fn ($page) => $page->has('payments.data', 1)->where('payments.data.0.amount', 200000));

        $this->get(route('admin.payments.index', ['cari' => 'tidak ada orang ini']))
            ->assertInertia(fn ($page) => $page->has('payments.data', 0));
    });

    test('sorts by amount in both directions and by date by default, newest first', function () {
        listedPayment($this->parentA, $this->itemA1, 100000, ['paid_on' => '2026-11-01']);
        listedPayment($this->parentA, $this->itemA2, 50000, ['paid_on' => '2026-11-03']);
        listedPayment($this->parentB, $this->itemB1, 200000, ['paid_on' => '2026-11-02']);

        $amounts = fn (array $query) => collect($this->get(route('admin.payments.index', $query))->viewData('page')['props']['payments']['data'])->pluck('amount')->all();

        expect($amounts([]))->toBe([50000, 200000, 100000])
            ->and($amounts(['urut' => 'tanggal', 'arah' => 'asc']))->toBe([100000, 200000, 50000])
            ->and($amounts(['urut' => 'nominal', 'arah' => 'desc']))->toBe([200000, 100000, 50000])
            ->and($amounts(['urut' => 'nominal', 'arah' => 'asc']))->toBe([50000, 100000, 200000]);
    });

    test('ignores malformed filters instead of failing', function () {
        listedPayment($this->parentA, $this->itemA1, 100000);

        $this->get(route('admin.payments.index', ['dari' => 'kemarin', 'sampai' => '2026-13-45', 'cara' => 'bitcoin', 'status' => 'x', 'urut' => 'drop table']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->has('payments.data', 1)->where('filters.dari', '')->where('filters.cara', ''));
    });

    test('counts every status within the other filters and totals the current filter across pages', function () {
        foreach (range(1, 17) as $number) {
            listedPayment($this->parentA, $this->itemA2, 1000, ['status' => PaymentStatus::Pending, 'method' => PaymentMethod::Transfer]);
        }
        listedPayment($this->parentA, $this->itemA1, 5000, ['status' => PaymentStatus::Verified, 'method' => PaymentMethod::Transfer]);
        listedPayment($this->parentB, $this->itemB1, 7000, ['status' => PaymentStatus::Rejected, 'method' => PaymentMethod::Cash]);

        $this->get(route('admin.payments.index', ['status' => 'pending']))->assertInertia(fn ($page) => $page
            ->has('payments.data', 15)
            ->where('payments.total', 17)
            ->where('totals.amount', 17000)
            ->where('counts', ['all' => 19, 'pending' => 17, 'verified' => 1, 'rejected' => 1]));

        $this->get(route('admin.payments.index', ['status' => 'pending', 'cara' => 'cash']))->assertInertia(fn ($page) => $page
            ->has('payments.data', 0)
            ->where('totals.amount', 0)
            ->where('counts', ['all' => 1, 'pending' => 0, 'verified' => 0, 'rejected' => 1]));
    });

    test('shows what was paid for in each row', function () {
        listedPayment($this->parentA, $this->itemA1, 150000);

        $this->get(route('admin.payments.index'))->assertInertia(fn ($page) => $page
            ->where('payments.data.0.items', [['name' => 'Rincian 0', 'amount' => 150000]])
            ->where('payments.data.0.method_value', 'transfer'));
    });
});

describe('the summary', function () {
    test('adds up the queue, this month, rejections and what is still owed', function () {
        listedPayment($this->parentA, $this->itemA1, 40000, ['status' => PaymentStatus::Pending]);
        listedPayment($this->parentA, $this->itemA2, 10000, ['status' => PaymentStatus::Pending]);
        listedPayment($this->parentA, $this->itemA1, 100000, ['status' => PaymentStatus::Verified, 'verified_at' => now()]);
        listedPayment($this->parentB, $this->itemB1, 30000, ['status' => PaymentStatus::Verified, 'verified_at' => now()->subMonths(2)]);
        listedPayment($this->parentB, $this->itemB1, 999000, ['status' => PaymentStatus::Rejected]);

        $this->get(route('admin.payments.index'))->assertInertia(fn ($page) => $page
            ->where('summary.pending_count', 2)
            ->where('summary.pending_amount', 50000)
            ->where('summary.verified_this_month', 100000)
            ->where('summary.rejected_count', 1)
            // 150k + 50k + 200k billed, 130k verified; pending and rejected money does not count.
            ->where('summary.outstanding', 270000)
            ->where('summary.overpaid', 0)
            ->where('summary.month.dari', today()->startOfMonth()->toDateString())
            ->where('summary.month.sampai', today()->endOfMonth()->toDateString()));
    });
});

describe('the quick view', function () {
    test('gives the payment with its division and the next one waiting', function () {
        $first = listedPayment($this->parentA, $this->itemA1, 150000, ['paid_on' => '2026-11-01']);
        $second = listedPayment($this->parentB, $this->itemB1, 200000, ['paid_on' => '2026-11-02']);
        listedPayment($this->parentA, $this->itemA2, 50000, ['paid_on' => '2026-11-03', 'status' => PaymentStatus::Verified]);

        $this->getJson(route('admin.payments.quick-view', $first))
            ->assertOk()
            ->assertJsonPath('payment.id', $first->id)
            ->assertJsonPath('payment.payer', $this->parentA->person->name)
            ->assertJsonPath('allocations.0.item', 'Rincian 0')
            ->assertJsonPath('allocations.0.room', 150000)
            ->assertJsonPath('can.verify', true)
            ->assertJsonPath('can_verify_as_submitted', true)
            ->assertJsonPath('queue', ['next_id' => $second->id, 'left' => 1]);
    });

    test('keeps the queue inside the narrowing of the list', function () {
        $viewed = listedPayment($this->parentA, $this->itemA1, 150000, ['method' => PaymentMethod::Cash]);
        listedPayment($this->parentB, $this->itemB1, 200000, ['method' => PaymentMethod::Transfer]);

        $this->getJson(route('admin.payments.quick-view', [$viewed, 'cara' => 'cash']))->assertJsonPath('queue', ['next_id' => null, 'left' => 0]);
    });

    test('warns when an item can no longer take what was submitted', function () {
        $payment = listedPayment($this->parentA, $this->itemA2, 50000);
        verifiedPayment($this->itemA2, 30000);

        $this->getJson(route('admin.payments.quick-view', $payment))
            ->assertJsonPath('allocations.0.room', 20000)
            ->assertJsonPath('can_verify_as_submitted', false);
    });

    test('cannot be used to verify what is already handled, and hides the proof for outsiders', function () {
        $handled = listedPayment($this->parentA, $this->itemA1, 150000, ['status' => PaymentStatus::Verified]);

        $this->getJson(route('admin.payments.quick-view', $handled))->assertJsonPath('can.verify', false);

        $this->actingAs($this->parentA)->getJson(route('admin.payments.quick-view', $handled))->assertForbidden();
    });

    test('is read-only for operators who may not verify', function () {
        $payment = listedPayment($this->parentA, $this->itemA1, 150000);

        $this->actingAs(operatorWhoCannotVerify())->getJson(route('admin.payments.quick-view', $payment))
            ->assertOk()
            ->assertJsonPath('can.verify', false);
    });

    test('returns to the list after verifying or rejecting from it', function () {
        $first = listedPayment($this->parentA, $this->itemA1, 150000);
        $second = listedPayment($this->parentB, $this->itemB1, 200000);
        $list = route('admin.payments.index', ['status' => 'pending']);

        $this->from($list)->put(route('admin.payments.verify', $first), ['quick' => 1])->assertRedirect($list);
        $this->from($list)->put(route('admin.payments.reject', $second), ['quick' => 1, 'reason' => 'Buktinya buram'])->assertRedirect($list);

        expect($first->refresh()->status)->toBe(PaymentStatus::Verified)->and($second->refresh()->status)->toBe(PaymentStatus::Rejected);
    });
});

describe('verifying in bulk', function () {
    test('verifies every pending payment and counts them towards the bills', function () {
        $first = listedPayment($this->parentA, $this->itemA1, 150000, ['proof_path' => 'payment-proofs/a.jpg']);
        $second = listedPayment($this->parentB, $this->itemB1, 200000, ['proof_path' => 'payment-proofs/b.jpg']);
        $list = route('admin.payments.index');

        $this->from($list)->post(route('admin.payments.bulk-verify'), ['ids' => [$first->id, $second->id]])
            ->assertRedirect($list)
            ->assertSessionHasNoErrors();

        expect($first->refresh()->status)->toBe(PaymentStatus::Verified)
            ->and($second->refresh()->status)->toBe(PaymentStatus::Verified)
            ->and($second->verified_by_user_id)->toBe($this->admin->id);
    });

    test('skips what cannot be verified with the reason and still handles the rest', function () {
        $good = listedPayment($this->parentA, $this->itemA1, 150000, ['proof_path' => 'payment-proofs/a.jpg']);
        $handled = listedPayment($this->parentA, $this->itemA2, 50000, ['status' => PaymentStatus::Rejected]);
        $tooMuch = listedPayment($this->parentB, $this->itemB1, 200000, ['proof_path' => 'payment-proofs/b.jpg']);
        verifiedPayment($this->itemB1, 100000);

        $response = $this->post(route('admin.payments.bulk-verify'), ['ids' => [$handled->id, $tooMuch->id, $good->id, 999999]]);

        $toast = session('inertia.flash_data.toast') ?? session('toast');

        expect($good->refresh()->status)->toBe(PaymentStatus::Verified)
            ->and($handled->refresh()->status)->toBe(PaymentStatus::Rejected)
            ->and($tooMuch->refresh()->status)->toBe(PaymentStatus::Pending);

        $response->assertSessionHasNoErrors();
        expect($toast['type'] ?? null)->toBe('warning')
            ->and($toast['message'] ?? '')->toContain('1 diverifikasi, 3 dilewati')
            ->toContain('pembayaran ini sudah diproses')
            ->toContain('tidak ditemukan')
            ->toContain('melebihi sisa tagihan');
    });

    test('needs the permission to verify and at least one payment', function () {
        $payment = listedPayment($this->parentA, $this->itemA1, 150000);

        $this->post(route('admin.payments.bulk-verify'), ['ids' => []])->assertSessionHasErrors('ids');

        $this->actingAs(operatorWhoCannotVerify())->post(route('admin.payments.bulk-verify'), ['ids' => [$payment->id]])->assertForbidden();
        $this->actingAs($this->parentA)->post(route('admin.payments.bulk-verify'), ['ids' => [$payment->id]])->assertForbidden();

        expect($payment->refresh()->status)->toBe(PaymentStatus::Pending);
    });
});

describe('the CSV export', function () {
    test('streams every page of the filtered list with a byte order mark', function () {
        foreach (range(1, 20) as $number) {
            listedPayment($this->parentA, $this->itemA2, 1000, ['status' => PaymentStatus::Pending]);
        }
        listedPayment($this->parentB, $this->itemB1, 200000, ['status' => PaymentStatus::Verified, 'note' => 'Lunas']);

        $response = $this->get(route('admin.payments.export', ['status' => 'pending']));
        $response->assertOk()->assertDownload('pembayaran-'.today()->toDateString().'.csv');

        $csv = $response->streamedContent();
        $rows = array_map('str_getcsv', array_filter(explode("\n", substr($csv, 3))));

        expect(str_starts_with($csv, "\xEF\xBB\xBF"))->toBeTrue()
            ->and($response->headers->get('Content-Type'))->toContain('text/csv')
            ->and($rows)->toHaveCount(21)
            ->and($rows[0][0])->toBe('ID')
            ->and($rows[1][7])->toBe('Menunggu verifikasi');
    });

    test('respects the method, search and date filters', function () {
        $this->parentA->person->update(['name' => 'Siti Aminah']);
        $this->parentB->person->update(['name' => 'Rahmat Hidayat']);
        listedPayment($this->parentA, $this->itemA1, 111000, ['method' => PaymentMethod::Cash, 'paid_on' => '2026-11-02']);
        listedPayment($this->parentB, $this->itemB1, 222000, ['method' => PaymentMethod::Cash, 'paid_on' => '2026-11-02']);
        listedPayment($this->parentB, $this->itemB1, 333000, ['method' => PaymentMethod::Transfer, 'paid_on' => '2026-11-02']);
        listedPayment($this->parentB, $this->itemB1, 444000, ['method' => PaymentMethod::Cash, 'paid_on' => '2026-10-01']);

        $csv = $this->get(route('admin.payments.export', ['cari' => 'rahmat', 'cara' => 'cash', 'dari' => '2026-11-01']))->streamedContent();

        expect($csv)->toContain('222000')
            ->not->toContain('111000')
            ->not->toContain('333000')
            ->not->toContain('444000');
    });

    test('keeps spreadsheets from running text as a formula', function () {
        listedPayment($this->parentA, $this->itemA1, 1000, ['note' => '=HYPERLINK("http://x")']);

        expect($this->get(route('admin.payments.export'))->streamedContent())->toContain("\"'=HYPERLINK");
    });

    test('is for staff only', function () {
        $this->actingAs($this->parentA)->get(route('admin.payments.export'))->assertForbidden();
        $this->actingAs(operatorWhoCannotVerify())->get(route('admin.payments.export'))->assertOk();
    });
});
