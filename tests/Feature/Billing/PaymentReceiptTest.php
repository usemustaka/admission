<?php

use App\Enums\DefaultRole;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Models\User;
use App\Support\PaymentReceipt;

beforeEach(function () {
    $this->withoutVite();

    $this->parent = User::factory()->create();
    [$this->kid, $this->bill] = billedChild($this->parent, [1500000, 750500]);
    [$this->item1, $this->item2] = $this->bill->items->all();

    $this->verifier = User::factory()->create();
    $this->verifier->person->update(['name' => 'Bu Rina']);
    $this->payment = Payment::factory()->create([
        'payer_person_id' => $this->parent->person_id,
        'amount' => 2250500,
        'method' => PaymentMethod::Cash,
        'paid_on' => '2026-03-14',
        'status' => PaymentStatus::Verified,
        'created_by_user_id' => $this->parent->id,
        'verified_by_user_id' => $this->verifier->id,
        'verified_at' => '2026-03-15 09:30:00',
    ]);
    PaymentAllocation::factory()->create(['payment_id' => $this->payment->id, 'bill_item_id' => $this->item1->id, 'amount' => 1500000]);
    PaymentAllocation::factory()->create(['payment_id' => $this->payment->id, 'bill_item_id' => $this->item2->id, 'amount' => 750500]);
});

test('the number comes from the year paid and the payment id', function () {
    $earlier = Payment::factory()->make(['paid_on' => '2026-03-14']);
    $earlier->id = 42;

    expect(PaymentReceipt::number($earlier))->toBe('KW-2026-000042');

    $other = Payment::factory()->make(['paid_on' => '2025-12-31']);
    $other->id = 123456;

    expect(PaymentReceipt::number($other))->toBe('KW-2025-123456');
});

test('shows what was paid, for whom, and the total in words', function () {
    $this->actingAs($this->parent)->get(route('payments.receipt', $this->payment))->assertOk()->assertInertia(fn ($page) => $page
        ->component('payments/receipt')
        ->where('receipt.number', sprintf('KW-2026-%06d', $this->payment->id))
        ->where('receipt.paid_on', '2026-03-14')
        ->where('receipt.payer', $this->parent->person->name)
        ->where('receipt.method', 'Tunai')
        ->where('receipt.total', 2250500)
        ->where('receipt.total_words', 'Dua juta dua ratus lima puluh ribu lima ratus rupiah')
        ->where('receipt.verified_by', 'Bu Rina')
        ->has('receipt.lines', 2)
        ->where('receipt.lines.0.item', 'Rincian 0')
        ->where('receipt.lines.0.amount', 1500000)
        ->where('receipt.lines.0.child', $this->kid->person->name)
        ->where('receipt.lines.0.organization', $this->kid->organization->name));
});

test('the payer, the person who submitted it and the committee can open it', function () {
    $submitter = User::factory()->create();
    $this->payment->update(['created_by_user_id' => $submitter->id]);

    foreach ([$this->parent, $submitter, userWithRole(DefaultRole::Operator)] as $user) {
        $this->actingAs($user)->get(route('payments.receipt', $this->payment))->assertOk();
    }
});

test('another parent cannot open it', function () {
    $this->actingAs(User::factory()->create())->get(route('payments.receipt', $this->payment))->assertForbidden();
});

test('a visitor who is not signed in is sent to the login', function () {
    $this->get(route('payments.receipt', $this->payment))->assertRedirect(route('login'));
});

test('a payment that was not accepted has no receipt', function (PaymentStatus $status) {
    $this->payment->update(['status' => $status]);

    $this->actingAs($this->parent)->get(route('payments.receipt', $this->payment))->assertNotFound();
    $this->actingAs(userWithRole(DefaultRole::Admin))->get(route('payments.receipt', $this->payment))->assertNotFound();
})->with([PaymentStatus::Pending, PaymentStatus::Rejected]);

test('another parent learns nothing about a payment that has no receipt', function () {
    $this->payment->update(['status' => PaymentStatus::Pending]);

    $this->actingAs(User::factory()->create())->get(route('payments.receipt', $this->payment))->assertForbidden();
});
