<?php

use App\Enums\DefaultRole;
use App\Enums\PaymentStatus;
use App\Models\Bill;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Models\User;
use Illuminate\Http\UploadedFile;

beforeEach(function () {
    $this->parent = User::factory()->create();
    [$this->kidA, $this->billA] = billedChild($this->parent, [150000, 50000]);
    [$this->kidB, $this->billB] = billedChild($this->parent, [200000]);
    [$this->itemA1, $this->itemA2] = $this->billA->items->all();
    [$this->itemB1] = $this->billB->items->all();

    $this->actingAs(userWithRole(DefaultRole::Admin));

    $this->record = fn (array $overrides = []) => $this->post(route('admin.payments.store'), [
        'payer_person_id' => $this->parent->person_id,
        'items' => [['bill_item_id' => $this->itemA1->id, 'amount' => 30000]],
        'paid_on' => today()->toDateString(),
        'method' => 'cash',
        ...$overrides,
    ]);
    $this->existing = fn (PaymentStatus $status, array $attributes = []): Payment => tap(Payment::factory()->create([
        'payer_person_id' => $this->parent->person_id,
        'amount' => 30000,
        'status' => $status,
        ...$attributes,
    ]), fn (Payment $payment) => PaymentAllocation::factory()->create(['payment_id' => $payment->id, 'bill_item_id' => $this->itemA2->id, 'amount' => 30000]));
});

describe('recording the same payment twice', function () {
    test('is refused until the clerk confirms, when the payer, amount and day match a waiting payment', function () {
        ($this->existing)(PaymentStatus::Pending);

        ($this->record)()->assertSessionHasErrors('confirm_duplicate');

        expect(Payment::count())->toBe(1);

        ($this->record)(['confirm_duplicate' => true])->assertSessionHasNoErrors();

        expect(Payment::count())->toBe(2);
    });

    test('is also refused against a verified payment, and the message names it', function () {
        $verified = ($this->existing)(PaymentStatus::Verified);

        ($this->record)()->assertSessionHasErrors('confirm_duplicate');

        expect(session('errors')->first('confirm_duplicate'))->toContain("(#{$verified->id}, terverifikasi)");
    });

    test('is fine when the amount, the day or the payer differs, or the other payment was rejected', function () {
        ($this->existing)(PaymentStatus::Pending);

        ($this->record)(['items' => [['bill_item_id' => $this->itemA1->id, 'amount' => 30001]]])->assertSessionHasNoErrors();
        ($this->record)(['paid_on' => today()->subDay()->toDateString()])->assertSessionHasNoErrors();

        $otherParent = User::factory()->create();
        [, $otherBill] = billedChild($otherParent, [100000]);
        ($this->record)(['payer_person_id' => $otherParent->person_id, 'items' => [['bill_item_id' => $otherBill->items->first()->id, 'amount' => 30000]]])->assertSessionHasNoErrors();

        Payment::query()->delete();
        ($this->existing)(PaymentStatus::Rejected);
        ($this->record)()->assertSessionHasNoErrors();
    });

    test('never blocks a parent submitting through the portal', function () {
        ($this->existing)(PaymentStatus::Pending, ['created_by_user_id' => $this->parent->id]);

        $this->actingAs($this->parent)->post(route('portal.payments.store'), [
            'items' => [['bill_item_id' => $this->itemA1->id, 'amount' => 30000]],
            'paid_on' => today()->toDateString(),
            'method' => 'transfer',
            'proof' => UploadedFile::fake()->image('bukti.jpg'),
        ])->assertSessionHasNoErrors();
    });
});

describe('the form for recording a payment', function () {
    test('gets the pending payments of the payer next to the open items', function () {
        $waiting = ($this->existing)(PaymentStatus::Pending, ['paid_on' => '2026-11-03']);
        ($this->existing)(PaymentStatus::Verified);
        ($this->existing)(PaymentStatus::Rejected);
        Payment::factory()->create(['status' => PaymentStatus::Pending]);

        $this->getJson(route('admin.payments.open-items', ['payer' => $this->parent->person_id]))
            ->assertOk()
            ->assertJsonCount(2, 'groups')
            ->assertJsonCount(1, 'pending_payments')
            ->assertJsonPath('pending_payments.0.id', $waiting->id)
            ->assertJsonPath('pending_payments.0.amount', 30000)
            ->assertJsonPath('pending_payments.0.paid_on', '2026-11-03')
            ->assertJsonPath('pending_payments.0.method', 'Transfer bank');
    });

    test('has no pending payments for a payer who has none', function () {
        $this->getJson(route('admin.payments.open-items', ['payer' => $this->parent->person_id]))->assertJsonPath('pending_payments', []);
    });
});

describe('dividing a payment over other items of the family', function () {
    beforeEach(function () {
        $this->pending = Payment::factory()->create(['payer_person_id' => $this->parent->person_id, 'amount' => 230000]);
        PaymentAllocation::factory()->create(['payment_id' => $this->pending->id, 'bill_item_id' => $this->itemA1->id, 'amount' => 150000]);
        PaymentAllocation::factory()->create(['payment_id' => $this->pending->id, 'bill_item_id' => $this->itemB1->id, 'amount' => 80000]);
    });

    test('the detail page offers every open item of the family, not just the submitted ones', function () {
        $this->get(route('admin.payments.show', $this->pending))->assertInertia(fn ($page) => $page
            ->has('open_groups', 2)
            ->where('open_groups.0.items.1.id', $this->itemA2->id)
            ->where('open_groups.0.items.1.outstanding', 50000));
    });

    test('an item outside the submission can receive part of it', function () {
        $this->put(route('admin.payments.verify', $this->pending), ['allocations' => [
            ['bill_item_id' => $this->itemA1->id, 'amount' => 150000],
            ['bill_item_id' => $this->itemB1->id, 'amount' => 30000],
            ['bill_item_id' => $this->itemA2->id, 'amount' => 50000],
        ]])->assertSessionHasNoErrors();

        expect($this->pending->refresh()->status)->toBe(PaymentStatus::Verified)
            ->and($this->pending->allocations()->pluck('amount', 'bill_item_id')->sortKeys()->all())->toBe([
                $this->itemA1->id => 150000,
                $this->itemA2->id => 50000,
                $this->itemB1->id => 30000,
            ])
            ->and(Bill::find($this->billA->id)->loadTotals()->status()->value)->toBe('paid');
    });

    test('an error on an added item lands on its own row', function () {
        verifiedPayment($this->itemA2, 40000);

        $this->put(route('admin.payments.verify', $this->pending), ['allocations' => [
            ['bill_item_id' => $this->itemA1->id, 'amount' => 150000],
            ['bill_item_id' => $this->itemB1->id, 'amount' => 20000],
            ['bill_item_id' => $this->itemA2->id, 'amount' => 60000],
        ]])->assertSessionHasErrors('allocations.2.amount');

        expect($this->pending->refresh()->status)->toBe(PaymentStatus::Pending);
    });

    test('a payment that is no longer pending gets no open items', function () {
        $this->pending->update(['status' => PaymentStatus::Verified]);

        $this->get(route('admin.payments.show', $this->pending))->assertInertia(fn ($page) => $page->where('open_groups', []));
    });
});
