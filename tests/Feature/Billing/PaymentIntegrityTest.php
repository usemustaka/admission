<?php

use App\Actions\Billing\IssueBill;
use App\Actions\Billing\RejectPayment;
use App\Actions\Billing\VerifyPayment;
use App\Enums\BillStatus;
use App\Enums\BillType;
use App\Enums\DefaultRole;
use App\Enums\PaymentStatus;
use App\Models\Bill;
use App\Models\BillItem;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Models\Registrant;
use App\Models\User;
use App\Support\RegistrantBillSummary;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    Storage::fake('local');
    $this->parent = User::factory()->create();
    [$this->kidA, $this->billA] = billedChild($this->parent, [150000, 50000]);
    [$this->kidB, $this->billB] = billedChild($this->parent, [200000]);
    [$this->itemA1, $this->itemA2] = $this->billA->items->all();
    [$this->itemB1] = $this->billB->items->all();
    $this->admin = userWithRole(DefaultRole::Admin);
    $this->actingAs($this->admin);

    /** A payment of the parent that waits for verification, divided as `$rows` says: [[item, amount], ...]. */
    $this->submit = function (array $rows, array $attributes = []): Payment {
        $payment = Payment::factory()->create([
            'payer_person_id' => $this->parent->person_id,
            'amount' => array_sum(array_column($rows, 1)),
            'proof_path' => 'payment-proofs/bukti.jpg',
            ...$attributes,
        ]);

        foreach ($rows as [$item, $amount]) {
            PaymentAllocation::factory()->create(['payment_id' => $payment->id, 'bill_item_id' => $item->id, 'amount' => $amount]);
        }

        return $payment;
    };
    $this->paidOf = fn (BillItem $item): int => BillItem::query()->withPaymentTotals()->findOrFail($item->id)->paidAmount();
});

describe('a payment is handled only once', function () {
    test('a second verify of the same payment is refused and allocates nothing twice', function () {
        $payment = ($this->submit)([[$this->itemA1, 150000], [$this->itemB1, 80000]]);
        $stale = Payment::findOrFail($payment->id);
        $secondStaff = User::factory()->create();

        app(VerifyPayment::class)->handle($payment, $this->admin);

        expect(fn () => app(VerifyPayment::class)->handle($stale, $secondStaff, [
            ['bill_item_id' => $this->itemA1->id, 'amount' => 100000],
            ['bill_item_id' => $this->itemB1->id, 'amount' => 130000],
        ]))->toThrow(ValidationException::class);

        $payment->refresh();

        expect($payment->status)->toBe(PaymentStatus::Verified)
            ->and($payment->verified_by_user_id)->toBe($this->admin->id)
            ->and($payment->allocations()->orderBy('id')->pluck('amount')->all())->toBe([150000, 80000])
            ->and(($this->paidOf)($this->itemA1))->toBe(150000)
            ->and(($this->paidOf)($this->itemB1))->toBe(80000);
    });

    test('rejecting a payment that was verified a moment ago changes nothing', function () {
        $payment = ($this->submit)([[$this->itemA1, 150000]]);
        $stale = Payment::findOrFail($payment->id);

        app(VerifyPayment::class)->handle($payment, $this->admin);

        expect(fn () => app(RejectPayment::class)->handle($stale, User::factory()->create(), 'Terlambat'))->toThrow(ValidationException::class);

        $payment->refresh();

        expect($payment->status)->toBe(PaymentStatus::Verified)
            ->and($payment->verified_by_user_id)->toBe($this->admin->id)
            ->and($payment->rejection_reason)->toBeNull()
            ->and(($this->paidOf)($this->itemA1))->toBe(150000);
    });

    test('verifying a payment that was rejected a moment ago changes nothing', function () {
        $payment = ($this->submit)([[$this->itemA1, 150000]]);
        $stale = Payment::findOrFail($payment->id);

        app(RejectPayment::class)->handle($payment, $this->admin, 'Buktinya buram');

        expect(fn () => app(VerifyPayment::class)->handle($stale, User::factory()->create()))->toThrow(ValidationException::class);

        $payment->refresh();

        expect($payment->status)->toBe(PaymentStatus::Rejected)
            ->and($payment->rejection_reason)->toBe('Buktinya buram')
            ->and(($this->paidOf)($this->itemA1))->toBe(0);
    });
});

describe('an item is never paid beyond what it asks', function () {
    test('two waiting payments cannot both fill the same item', function () {
        $first = ($this->submit)([[$this->itemA2, 50000]]);
        $second = ($this->submit)([[$this->itemA2, 50000]]);

        $this->put(route('admin.payments.verify', $first))->assertSessionHasNoErrors();
        $this->put(route('admin.payments.verify', $second))->assertSessionHasErrors('allocations.0.amount');

        expect($second->refresh()->status)->toBe(PaymentStatus::Pending)
            ->and(($this->paidOf)($this->itemA2))->toBe(50000);
    });

    test('recording directly stops at what verified payments left', function () {
        verifiedPayment($this->itemA2, 30000);
        $record = fn (int $amount) => $this->post(route('admin.payments.store'), [
            'payer_person_id' => $this->parent->person_id,
            'items' => [['bill_item_id' => $this->itemA2->id, 'amount' => $amount]],
            'paid_on' => today()->toDateString(),
            'method' => 'cash',
        ]);

        $record(20001)->assertSessionHasErrors('items.0.amount');
        $record(20000)->assertSessionHasNoErrors();

        expect(($this->paidOf)($this->itemA2))->toBe(50000);
    });

    test('money beyond an item shows as overpaid and never covers another item', function () {
        verifiedPayment($this->itemA2, 70000);

        $item = BillItem::query()->withPaymentTotals()->findOrFail($this->itemA2->id);
        $bills = Bill::query()->whereKey($this->billA->id)->with(['items' => fn ($items) => $items->withPaymentTotals()])->get();

        expect($item->excessAmount())->toBe(20000)
            ->and($item->outstandingAmount())->toBe(0)
            ->and(RegistrantBillSummary::totals($bills))->toMatchArray(['paid' => 70000, 'outstanding' => 150000, 'excess' => 20000])
            ->and($bills->first()->status())->toBe(BillStatus::Partial);

        $this->get(route('admin.payments.index'))->assertInertia(fn ($page) => $page
            ->where('summary.overpaid', 20000)
            // 150k of A1 and 200k of B1 are still asked; the 20k surplus on A2 does not lower that.
            ->where('summary.outstanding', 350000));
    });
});

describe('the same bill item twice in one request', function () {
    test('is a validation error for the committee and leaves no proof file behind', function () {
        $this->post(route('admin.payments.store'), [
            'payer_person_id' => $this->parent->person_id,
            'items' => [
                ['bill_item_id' => $this->itemA2->id, 'amount' => 10000],
                ['bill_item_id' => $this->itemA2->id, 'amount' => 10000],
            ],
            'paid_on' => today()->toDateString(),
            'method' => 'transfer',
            'proof' => UploadedFile::fake()->image('bukti.png'),
        ])->assertSessionHasErrors('items.1.bill_item_id');

        expect(Payment::count())->toBe(0)->and(Storage::disk('local')->allFiles())->toBe([]);
    });

    test('is a validation error for a parent too', function () {
        $this->actingAs($this->parent)->post(route('portal.payments.store'), [
            'items' => [
                ['bill_item_id' => $this->itemA2->id, 'amount' => 10000],
                ['bill_item_id' => $this->itemA2->id, 'amount' => 10000],
            ],
            'paid_on' => today()->toDateString(),
            'method' => 'transfer',
            'proof' => UploadedFile::fake()->image('bukti.jpg'),
        ])->assertSessionHasErrors('items.1.bill_item_id');

        expect(Payment::count())->toBe(0)->and(Storage::disk('local')->allFiles())->toBe([]);
    });

    test('is a validation error when dividing a payment', function () {
        $payment = ($this->submit)([[$this->itemA1, 150000], [$this->itemB1, 80000]]);

        $this->put(route('admin.payments.verify', $payment), ['allocations' => [
            ['bill_item_id' => $this->itemA1->id, 'amount' => 150000],
            ['bill_item_id' => $this->itemA1->id, 'amount' => 80000],
        ]])->assertSessionHasErrors('allocations.1.bill_item_id');

        expect($payment->refresh()->status)->toBe(PaymentStatus::Pending)->and($payment->allocations()->count())->toBe(2);
    });

    test('the proof is removed again when saving the payment fails after it was stored', function () {
        Payment::updating(fn () => throw new RuntimeException('Disk penuh'));

        try {
            $this->withoutExceptionHandling();

            expect(fn () => $this->post(route('admin.payments.store'), [
                'payer_person_id' => $this->parent->person_id,
                'items' => [['bill_item_id' => $this->itemA2->id, 'amount' => 10000]],
                'paid_on' => today()->toDateString(),
                'method' => 'transfer',
                'proof' => UploadedFile::fake()->image('bukti.png'),
            ]))->toThrow(RuntimeException::class, 'Disk penuh');
        } finally {
            Payment::flushEventListeners();
        }

        expect(Payment::count())->toBe(0)->and(Storage::disk('local')->allFiles())->toBe([]);
    });
});

test('an error on a division lands on the row it came from, even when rows before it were left at zero', function () {
    $payment = ($this->submit)([[$this->itemA1, 150000], [$this->itemA2, 50000], [$this->itemB1, 30000]]);

    $this->put(route('admin.payments.verify', $payment), ['allocations' => [
        ['bill_item_id' => $this->itemA1->id, 'amount' => 0],
        ['bill_item_id' => $this->itemA2->id, 'amount' => 60000],
        ['bill_item_id' => $this->itemB1->id, 'amount' => 170000],
    ]])->assertSessionHasErrors('allocations.1.amount');

    expect($payment->refresh()->status)->toBe(PaymentStatus::Pending);
});

describe('a bill that asks for nothing', function () {
    $freeRegistrant = function (): Registrant {
        $registrant = Registrant::factory()->create([
            'status' => 'draft',
            'ruleset_snapshot' => ['ruleset_id' => 1, 'name' => 'x', 'exam_aspects' => [], 'bills' => [
                ['type' => 'registration', 'items' => [['name' => 'Beasiswa', 'amount' => 0]]],
            ]],
        ]);
        app(IssueBill::class)->handle($registrant, BillType::Registration);

        return $registrant->fresh();
    };

    test('is paid, and a free registration moves on to filling in the data', function () use ($freeRegistrant) {
        $registrant = $freeRegistrant();

        expect($registrant->bills()->firstOrFail()->loadTotals()->status())->toBe(BillStatus::Paid)
            ->and($registrant->isRegistrationBillSettled())->toBeTrue()
            ->and($registrant->currentStep())->toBe('data');
    });

    test('is only paid when nothing else on it is asked', function () {
        [, $mixed] = billedChild(User::factory()->create(), [0, 100000]);
        [, $free] = billedChild(User::factory()->create(), [0, 0]);

        expect($mixed->loadTotals()->status())->toBe(BillStatus::Unpaid)
            ->and($free->loadTotals()->status())->toBe(BillStatus::Paid);
    });

    test('agrees with the payment filter and the summary of the registrant list', function () {
        [$free] = billedChild(User::factory()->create(), [0]);
        [$open] = billedChild(User::factory()->create(), [100000]);
        [$mixed] = billedChild(User::factory()->create(), [100000]);
        [, $freeSettlement] = billedChild(User::factory()->create(), [0], BillType::Settlement);
        $freeSettlement->update(['registrant_id' => $mixed->id]);

        $this->get(route('admin.registrants.index', ['bayar' => 'lunas']))->assertInertia(fn ($page) => $page
            ->has('registrants.data', 1)
            ->where('registrants.data.0.id', $free->id)
            ->where('registrants.data.0.payment', 'paid'));

        $this->get(route('admin.registrants.index', ['bayar' => 'belum']))->assertInertia(fn ($page) => $page
            ->where('registrants.data', fn ($rows) => collect($rows)->pluck('id')->intersect([$free->id, $open->id, $mixed->id])->sort()->values()->all() === collect([$open->id, $mixed->id])->sort()->values()->all()
                // A free settlement bill next to an unpaid one does not make the registrant look partly paid.
                && collect($rows)->firstWhere('id', $mixed->id)['payment'] === 'unpaid'));
    });
});

describe('verifying in bulk', function () {
    test('skips payments without proof unless asked, and lists every one of them', function () {
        $withProof = ($this->submit)([[$this->itemA1, 150000]]);
        $withoutProof = collect(range(1, 5))->map(fn () => ($this->submit)([[$this->itemA2, 1000]], ['proof_path' => null]));
        $ids = [$withProof->id, ...$withoutProof->pluck('id')->all()];

        $this->post(route('admin.payments.bulk-verify'), ['ids' => $ids])->assertSessionHasNoErrors();

        $toast = session('inertia.flash_data.toast');
        $skipped = session('inertia.flash_data.bulk')['skipped'];

        expect($withProof->refresh()->status)->toBe(PaymentStatus::Verified)
            ->and(Payment::where('status', PaymentStatus::Pending)->count())->toBe(5)
            ->and($toast['type'])->toBe('warning')
            ->and($toast['message'])->toContain('1 diverifikasi, 5 dilewati')->toContain('Tanpa bukti (5)')
            ->and($skipped)->toHaveCount(5)
            ->and(collect($skipped)->pluck('reason')->unique()->all())->toBe(['Tanpa bukti']);

        $this->post(route('admin.payments.bulk-verify'), ['ids' => $withoutProof->pluck('id')->all(), 'include_without_proof' => true])->assertSessionHasNoErrors();

        expect(Payment::where('status', PaymentStatus::Pending)->count())->toBe(0)
            ->and(($this->paidOf)($this->itemA2))->toBe(5000);
    });

    test('reports an unexpected failure as skipped instead of an error page', function () {
        $payment = ($this->submit)([[$this->itemA1, 150000]]);
        $this->mock(VerifyPayment::class, fn ($mock) => $mock->shouldReceive('handle')->andThrow(new RuntimeException('Koneksi putus')));

        $this->post(route('admin.payments.bulk-verify'), ['ids' => [$payment->id]])->assertRedirect()->assertSessionHasNoErrors();

        expect(session('inertia.flash_data.toast')['message'])->toContain('0 diverifikasi, 1 dilewati')->toContain('Gagal, coba lagi')
            ->and(session('inertia.flash_data.bulk')['skipped'][0]['reason'])->toBe('Gagal, coba lagi')
            ->and($payment->refresh()->status)->toBe(PaymentStatus::Pending);
    });
});

describe('the figures above the list', function () {
    test('add up each status on its own', function () {
        ($this->submit)([[$this->itemA1, 40000]]);
        ($this->submit)([[$this->itemA1, 10000]]);
        ($this->submit)([[$this->itemB1, 100000]], ['status' => PaymentStatus::Verified]);
        ($this->submit)([[$this->itemB1, 999000]], ['status' => PaymentStatus::Rejected]);

        $this->get(route('admin.payments.index'))->assertInertia(fn ($page) => $page
            ->where('totals.by_status', ['pending' => 50000, 'verified' => 100000, 'rejected' => 999000]));

        $this->get(route('admin.payments.index', ['status' => 'pending']))->assertInertia(fn ($page) => $page
            ->where('totals.amount', 50000)
            ->where('totals.by_status.verified', 100000));
    });

    test('count "verified this month" by the day of verification, not the date the parent says they paid', function () {
        ($this->submit)([[$this->itemA1, 1000]], ['status' => PaymentStatus::Verified, 'paid_on' => today()->subMonths(3), 'verified_at' => now()]);
        ($this->submit)([[$this->itemA1, 2000]], ['status' => PaymentStatus::Verified, 'paid_on' => today(), 'verified_at' => now()->startOfMonth()->subSecond()]);
        ($this->submit)([[$this->itemA1, 4000]], ['status' => PaymentStatus::Verified, 'paid_on' => today(), 'verified_at' => now()->startOfMonth()]);

        $this->get(route('admin.payments.index'))->assertInertia(fn ($page) => $page->where('summary.verified_this_month', 5000));
    });
});

test('the quick view moves on to the next payment in the order of the list, newest first', function () {
    $oldest = ($this->submit)([[$this->itemA1, 1000]], ['paid_on' => '2026-11-01']);
    $middle = ($this->submit)([[$this->itemA1, 1000]], ['paid_on' => '2026-11-02']);
    $newest = ($this->submit)([[$this->itemA1, 1000]], ['paid_on' => '2026-11-03']);

    $this->getJson(route('admin.payments.quick-view', $newest))->assertJsonPath('queue', ['next_id' => $middle->id, 'left' => 2]);
    $this->getJson(route('admin.payments.quick-view', [$newest, 'urut' => 'tanggal', 'arah' => 'asc']))->assertJsonPath('queue.next_id', $oldest->id);
});

describe('the proof file', function () {
    test('is served with a header that stops browsers from guessing its type', function () {
        Storage::disk('local')->put('payment-proofs/bukti.png', 'isi');
        $payment = ($this->submit)([[$this->itemA1, 1000]], ['proof_path' => 'payment-proofs/bukti.png']);

        $this->get(route('payments.proof', $payment))->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');
    });

    test('is not found when the file is gone from the disk', function () {
        $payment = ($this->submit)([[$this->itemA1, 1000]], ['proof_path' => 'payment-proofs/hilang.png']);

        $this->get(route('payments.proof', $payment))->assertNotFound();
    });
});
