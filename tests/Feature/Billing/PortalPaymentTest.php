<?php

use App\Enums\DefaultRole;
use App\Enums\PaymentStatus;
use App\Models\BillItem;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
    $this->parent = User::factory()->create();
    [$this->kidA, $this->billA] = billedChild($this->parent, [150000, 50000]);
    [$this->kidB, $this->billB] = billedChild($this->parent, [200000]);
    [$this->itemA1, $this->itemA2] = $this->billA->items->all();
    [$this->itemB1] = $this->billB->items->all();
    $this->actingAs($this->parent);

    $this->pay = fn (array $items, array $overrides = []) => $this->post(route('portal.payments.store'), [
        'items' => $items,
        'paid_on' => today()->toDateString(),
        'method' => 'transfer',
        'proof' => UploadedFile::fake()->image('bukti.jpg'),
        ...$overrides,
    ]);
});

describe('seeing the bills', function () {
    test('all bills of all children are listed together', function () {
        $this->get(route('portal.bills.index'))->assertInertia(fn ($page) => $page
            ->component('portal/bills/index')
            ->where('focus', null)
            ->has('bills', 2)
            ->where('bills.0.total', 200000)
            ->where('bills.0.items.0.outstanding', 150000)
            ->where('canPay', true));
    });

    test('in focus mode only the bills of that child are shown', function () {
        $this->get(route('portal.children.bills', $this->kidB))->assertInertia(fn ($page) => $page
            ->has('bills', 1)
            ->where('bills.0.registrant.id', $this->kidB->id)
            ->where('focus.id', $this->kidB->id));
    });

    test('bills of other families are never shown', function () {
        billedChild(User::factory()->create(), [999000]);

        $this->get(route('portal.bills.index'))->assertInertia(fn ($page) => $page->has('bills', 2));
    });

    test('someone who is not a parent of the child cannot open its bills', function () {
        $this->actingAs(User::factory()->create())->get(route('portal.children.bills', $this->kidA))->assertForbidden();
    });

    test('the payment history shows each payment with its status and reason', function () {
        $payment = Payment::factory()->create(['payer_person_id' => $this->parent->person_id, 'amount' => 1000, 'status' => PaymentStatus::Rejected, 'rejection_reason' => 'Buktinya buram']);
        PaymentAllocation::factory()->create(['payment_id' => $payment->id, 'bill_item_id' => $this->itemA1->id, 'amount' => 1000]);

        $this->get(route('portal.bills.index'))->assertInertia(fn ($page) => $page
            ->has('payments', 1)
            ->where('payments.0.status', 'rejected')
            ->where('payments.0.rejection_reason', 'Buktinya buram'));
    });
});

describe('confirming a payment', function () {
    test('one payment can pay items of two children at once, with proof', function () {
        ($this->pay)([
            ['bill_item_id' => $this->itemA1->id, 'amount' => 150000],
            ['bill_item_id' => $this->itemB1->id, 'amount' => 80000],
        ], ['note' => 'Transfer dari rekening ayah'])->assertRedirect(route('portal.bills.index'));

        $payment = Payment::firstOrFail();

        expect($payment->amount)->toBe(230000)
            ->and($payment->status)->toBe(PaymentStatus::Pending)
            ->and($payment->payer_person_id)->toBe($this->parent->person_id)
            ->and($payment->created_by_user_id)->toBe($this->parent->id)
            ->and($payment->allocations->pluck('amount', 'bill_item_id')->all())->toBe([$this->itemA1->id => 150000, $this->itemB1->id => 80000]);

        Storage::disk('local')->assertExists($payment->proof_path);
    });

    test('nothing counts as paid until the committee verifies it', function () {
        ($this->pay)([['bill_item_id' => $this->itemA1->id, 'amount' => 150000]]);

        $this->get(route('portal.bills.index'))->assertInertia(fn ($page) => $page
            ->where('bills.0.status', 'unpaid')
            ->where('bills.0.pending', 150000)
            ->where('bills.0.items.0.outstanding', 0));
    });

    test('an item can be paid in part', function () {
        ($this->pay)([['bill_item_id' => $this->itemA1->id, 'amount' => 50000]])->assertSessionHasNoErrors();

        expect(Payment::firstOrFail()->amount)->toBe(50000);
    });

    test('an amount beyond what is left of an item is refused', function () {
        ($this->pay)([['bill_item_id' => $this->itemA2->id, 'amount' => 50001]])->assertSessionHasErrors('items.0.amount');

        expect(Payment::count())->toBe(0);
    });

    test('the same money cannot be submitted twice while it waits for verification', function () {
        ($this->pay)([['bill_item_id' => $this->itemA2->id, 'amount' => 50000]])->assertSessionHasNoErrors();

        ($this->pay)([['bill_item_id' => $this->itemA2->id, 'amount' => 1]])->assertSessionHasErrors('items.0.amount');
    });

    test('a rejected payment frees the item to be paid again', function () {
        ($this->pay)([['bill_item_id' => $this->itemA2->id, 'amount' => 50000]]);
        Payment::firstOrFail()->update(['status' => PaymentStatus::Rejected]);

        ($this->pay)([['bill_item_id' => $this->itemA2->id, 'amount' => 50000]])->assertSessionHasNoErrors();
    });

    test('items of someone elses child cannot be paid', function () {
        [, $foreignBill] = billedChild(User::factory()->create(), [100000]);

        ($this->pay)([['bill_item_id' => $foreignBill->items->first()->id, 'amount' => 1000]])->assertSessionHasErrors('items.0.bill_item_id');

        expect(Payment::count())->toBe(0);
    });

    test('one failing item stops the whole payment', function () {
        ($this->pay)([
            ['bill_item_id' => $this->itemA1->id, 'amount' => 150000],
            ['bill_item_id' => $this->itemA2->id, 'amount' => 999999],
        ])->assertSessionHasErrors('items.1.amount');

        expect(Payment::count())->toBe(0);
    });

    test('proof, date, method and at least one item are required', function () {
        $this->post(route('portal.payments.store'), [])->assertSessionHasErrors(['items', 'paid_on', 'method', 'proof']);

        ($this->pay)([['bill_item_id' => $this->itemA1->id, 'amount' => 1000]], ['paid_on' => today()->addDay()->toDateString(), 'proof' => UploadedFile::fake()->create('virus.exe', 10)])
            ->assertSessionHasErrors(['paid_on', 'proof']);
    });

    test('the form offers only what is still open, for all children or one', function () {
        verifiedPaymentFor($this->itemA2);

        $this->get(route('portal.payments.create'))->assertInertia(fn ($page) => $page
            ->component('portal/payments/create')
            ->has('groups', 2)
            ->has('groups.0.items', 1));

        $this->get(route('portal.payments.create', ['anak' => $this->kidB->id]))->assertInertia(fn ($page) => $page->has('groups', 1)->where('focus.id', $this->kidB->id));
    });
});

function verifiedPaymentFor(BillItem $item): void
{
    verifiedPayment($item, $item->amount);
}

describe('proof files', function () {
    test('are private to the payer and the committee', function () {
        ($this->pay)([['bill_item_id' => $this->itemA1->id, 'amount' => 1000]]);
        $payment = Payment::firstOrFail();

        $this->get(route('payments.proof', $payment))->assertOk();

        $this->actingAs(User::factory()->create())->get(route('payments.proof', $payment))->assertForbidden();
        $this->actingAs(userWithRole(DefaultRole::Operator))->get(route('payments.proof', $payment))->assertOk();
    });

    test('a payment without proof has nothing to open', function () {
        $payment = Payment::factory()->create(['payer_person_id' => $this->parent->person_id, 'proof_path' => null]);

        $this->get(route('payments.proof', $payment))->assertNotFound();
    });
});
