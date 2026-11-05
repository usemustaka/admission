<?php

use App\Enums\DefaultRole;
use App\Enums\PaymentStatus;
use App\Models\Bill;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Storage::fake('local');
    $this->parent = User::factory()->create();
    [$this->kidA, $this->billA] = billedChild($this->parent, [150000, 50000]);
    [$this->kidB, $this->billB] = billedChild($this->parent, [200000]);
    [$this->itemA1, $this->itemA2] = $this->billA->items->all();
    [$this->itemB1] = $this->billB->items->all();

    $this->pending = Payment::factory()->create([
        'payer_person_id' => $this->parent->person_id,
        'amount' => 230000,
        'status' => PaymentStatus::Pending,
        'created_by_user_id' => $this->parent->id,
    ]);
    PaymentAllocation::factory()->create(['payment_id' => $this->pending->id, 'bill_item_id' => $this->itemA1->id, 'amount' => 150000]);
    PaymentAllocation::factory()->create(['payment_id' => $this->pending->id, 'bill_item_id' => $this->itemB1->id, 'amount' => 80000]);

    $this->admin = userWithRole(DefaultRole::Admin);
    $this->actingAs($this->admin);
    $this->billStatus = fn (Bill $bill) => Bill::find($bill->id)->loadTotals()->status()->value;
});

test('only staff can open the payments', function () {
    $this->actingAs(User::factory()->create())->get(route('admin.payments.index'))->assertForbidden();
    $this->actingAs(userWithRole(DefaultRole::Operator))->get(route('admin.payments.index'))->assertOk();
});

test('the list can be filtered by status and counts what waits', function () {
    Payment::factory()->create(['status' => PaymentStatus::Verified]);

    $this->get(route('admin.payments.index', ['status' => 'pending']))->assertInertia(fn ($page) => $page
        ->has('payments.data', 1)
        ->where('counts.pending', 1));

    $this->get(route('admin.payments.index'))->assertInertia(fn ($page) => $page->has('payments.data', 2));
});

describe('verifying', function () {
    test('as submitted, it counts towards the bills of both children', function () {
        $this->put(route('admin.payments.verify', $this->pending))->assertRedirect(route('admin.payments.show', $this->pending));

        $this->pending->refresh();

        expect($this->pending->status)->toBe(PaymentStatus::Verified)
            ->and($this->pending->verified_by_user_id)->toBe($this->admin->id)
            ->and($this->pending->verified_at)->not->toBeNull()
            ->and(($this->billStatus)($this->billA))->toBe('partial')
            ->and(($this->billStatus)($this->billB))->toBe('partial');
    });

    test('the committee can divide it differently, as long as the total stays the same', function () {
        $this->put(route('admin.payments.verify', $this->pending), ['allocations' => [
            ['bill_item_id' => $this->itemA1->id, 'amount' => 150000],
            ['bill_item_id' => $this->itemA2->id, 'amount' => 50000],
            ['bill_item_id' => $this->itemB1->id, 'amount' => 30000],
        ]])->assertSessionHasNoErrors();

        expect(($this->billStatus)($this->billA))->toBe('paid')
            ->and(PaymentAllocation::where('payment_id', $this->pending->id)->sum('amount'))->toBe(230000)
            ->and(PaymentAllocation::where('payment_id', $this->pending->id)->count())->toBe(3);
    });

    test('an item can be dropped from the division by giving it nothing', function () {
        $this->put(route('admin.payments.verify', $this->pending), ['allocations' => [
            ['bill_item_id' => $this->itemA1->id, 'amount' => 150000],
            ['bill_item_id' => $this->itemB1->id, 'amount' => 80000],
            ['bill_item_id' => $this->itemA2->id, 'amount' => 0],
        ]])->assertSessionHasNoErrors();

        expect(PaymentAllocation::where('payment_id', $this->pending->id)->count())->toBe(2);
    });

    test('a division with a different total is refused and nothing changes', function () {
        $this->put(route('admin.payments.verify', $this->pending), ['allocations' => [
            ['bill_item_id' => $this->itemA1->id, 'amount' => 150000],
        ]])->assertSessionHasErrors('allocations');

        expect($this->pending->refresh()->status)->toBe(PaymentStatus::Pending)->and($this->pending->allocations()->count())->toBe(2);
    });

    test('no item can be paid beyond what is left of it', function () {
        verifiedPayment($this->itemB1, 150000);

        $this->put(route('admin.payments.verify', $this->pending))->assertSessionHasErrors('allocations.1.amount');

        expect($this->pending->refresh()->status)->toBe(PaymentStatus::Pending);
    });

    test('an item of another family cannot be given money', function () {
        [, $foreignBill] = billedChild(User::factory()->create(), [500000]);

        $this->put(route('admin.payments.verify', $this->pending), ['allocations' => [
            ['bill_item_id' => $foreignBill->items->first()->id, 'amount' => 230000],
        ]])->assertSessionHasErrors('allocations.0.amount');
    });

    test('a payment that was already handled cannot be verified again', function () {
        $this->put(route('admin.payments.verify', $this->pending));

        $this->put(route('admin.payments.verify', $this->pending))->assertSessionHasErrors('payment');
    });
});

describe('rejecting', function () {
    test('needs a reason and never counts towards a bill', function () {
        $this->put(route('admin.payments.reject', $this->pending), ['reason' => ''])->assertSessionHasErrors('reason');

        $this->put(route('admin.payments.reject', $this->pending), ['reason' => 'Buktinya tidak terbaca'])->assertRedirect();

        $this->pending->refresh();

        expect($this->pending->status)->toBe(PaymentStatus::Rejected)
            ->and($this->pending->rejection_reason)->toBe('Buktinya tidak terbaca')
            ->and(($this->billStatus)($this->billA))->toBe('unpaid');
    });

    test('a handled payment cannot be rejected', function () {
        $this->put(route('admin.payments.verify', $this->pending));

        $this->put(route('admin.payments.reject', $this->pending), ['reason' => 'Terlambat'])->assertSessionHasErrors('payment');
    });
});

describe('recording a payment directly', function () {
    test('is verified at once and can cover several children', function () {
        $this->post(route('admin.payments.store'), [
            'payer_person_id' => $this->parent->person_id,
            'items' => [
                ['bill_item_id' => $this->itemA2->id, 'amount' => 50000],
                ['bill_item_id' => $this->itemB1->id, 'amount' => 200000],
            ],
            'paid_on' => today()->toDateString(),
            'method' => 'cash',
        ])->assertSessionHasNoErrors();

        $payment = Payment::latest('id')->firstOrFail();

        expect($payment->status)->toBe(PaymentStatus::Verified)
            ->and($payment->amount)->toBe(250000)
            ->and($payment->created_by_user_id)->toBe($this->admin->id)
            ->and($payment->verified_by_user_id)->toBe($this->admin->id)
            ->and($payment->proof_path)->toBeNull()
            ->and(($this->billStatus)($this->billB))->toBe('paid');
    });

    test('may include a proof', function () {
        $this->post(route('admin.payments.store'), [
            'payer_person_id' => $this->parent->person_id,
            'items' => [['bill_item_id' => $this->itemA2->id, 'amount' => 1000]],
            'paid_on' => today()->toDateString(),
            'method' => 'transfer',
            'proof' => UploadedFile::fake()->image('bukti.png'),
        ])->assertSessionHasNoErrors();

        Storage::disk('local')->assertExists(Payment::latest('id')->firstOrFail()->proof_path);
    });

    test('refuses more than is left, and items of other families', function () {
        $payload = fn (array $items) => [
            'payer_person_id' => $this->parent->person_id, 'items' => $items, 'paid_on' => today()->toDateString(), 'method' => 'cash',
        ];

        $this->post(route('admin.payments.store'), $payload([['bill_item_id' => $this->itemA2->id, 'amount' => 50001]]))->assertSessionHasErrors('items.0.amount');

        [, $foreignBill] = billedChild(User::factory()->create(), [1000]);
        $this->post(route('admin.payments.store'), $payload([['bill_item_id' => $foreignBill->items->first()->id, 'amount' => 1000]]))->assertSessionHasErrors('items.0.bill_item_id');
    });

    test('lists what a payer can still pay', function () {
        $this->getJson(route('admin.payments.open-items', ['payer' => $this->parent->person_id]))
            ->assertOk()
            ->assertJsonCount(2, 'groups')
            ->assertJsonPath('groups.0.items.0.outstanding', 150000);
    });
});

test('the detail page shows the division with what is left of each item', function () {
    $this->get(route('admin.payments.show', $this->pending))->assertInertia(fn ($page) => $page
        ->component('admin/payments/show')
        ->has('allocations', 2)
        ->where('allocations.0.room', 150000)
        ->where('can.verify', true));
});

test('operators who may only view cannot verify', function () {
    $operator = userWithRole(DefaultRole::Operator);
    $operator->revokePermissionTo('admission::payment.verify');
    Role::findByName('operator')->revokePermissionTo('admission::payment.verify');
    $this->actingAs($operator->fresh());

    $this->put(route('admin.payments.verify', $this->pending))->assertForbidden();
    $this->put(route('admin.payments.reject', $this->pending), ['reason' => 'x'])->assertForbidden();
});
