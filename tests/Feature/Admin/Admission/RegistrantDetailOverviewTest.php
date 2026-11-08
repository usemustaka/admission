<?php

use App\Enums\BillType;
use App\Enums\DefaultRole;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\RegistrantStatus;
use App\Models\Bill;
use App\Models\BillItem;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Models\Registrant;
use Inertia\Testing\AssertableInertia;

beforeEach(function () {
    $this->withoutVite();
    $this->admin = userWithRole(DefaultRole::Admin);
    $this->registrant = admissionRegistrant(RegistrantStatus::Validated);
    $this->bill = Bill::factory()->create(['registrant_id' => $this->registrant->id, 'type' => BillType::Settlement]);
    $this->item = BillItem::factory()->create(['bill_id' => $this->bill->id, 'name' => 'Uang pangkal', 'amount' => 1000000]);
});

test('the ruleset the registration was made under is shown', function () {
    $this->actingAs($this->admin)->get(route('admin.registrants.show', $this->registrant))->assertInertia(fn (AssertableInertia $page) => $page
        ->where('registrant.ruleset', $this->registrant->ruleset_snapshot['name']));
});

describe('payments of the child', function () {
    test('every payment that touched its bills is listed with what went to this child, newest first', function () {
        $other = BillItem::factory()->create(['amount' => 500000]);
        $transfer = Payment::factory()->create(['amount' => 1200000, 'status' => PaymentStatus::Verified, 'method' => PaymentMethod::Transfer, 'paid_on' => today()->subDay()]);
        PaymentAllocation::factory()->create(['payment_id' => $transfer->id, 'bill_item_id' => $this->item->id, 'amount' => 700000]);
        PaymentAllocation::factory()->create(['payment_id' => $transfer->id, 'bill_item_id' => $other->id, 'amount' => 500000]);
        $rejected = Payment::factory()->create(['amount' => 300000, 'status' => PaymentStatus::Rejected, 'rejection_reason' => 'Bukti buram', 'paid_on' => today()]);
        PaymentAllocation::factory()->create(['payment_id' => $rejected->id, 'bill_item_id' => $this->item->id, 'amount' => 300000]);
        Payment::factory()->create(['status' => PaymentStatus::Verified]);

        $this->actingAs($this->admin)->get(route('admin.registrants.show', $this->registrant))->assertInertia(fn (AssertableInertia $page) => $page
            ->has('payments', 2)
            ->where('payments.0.id', $rejected->id)
            ->where('payments.0.status', 'rejected')
            ->where('payments.0.rejection_reason', 'Bukti buram')
            ->where('payments.0.receipt', false)
            ->where('payments.1.id', $transfer->id)
            ->where('payments.1.for_child', 700000)
            ->where('payments.1.amount', 1200000)
            ->where('payments.1.items', ['Uang pangkal'])
            ->where('payments.1.receipt', true));
    });

    test('a scholarship has no receipt and is listed as such', function () {
        $scholarship = Payment::factory()->create(['amount' => 400000, 'status' => PaymentStatus::Verified, 'method' => PaymentMethod::Scholarship]);
        PaymentAllocation::factory()->create(['payment_id' => $scholarship->id, 'bill_item_id' => $this->item->id, 'amount' => 400000]);

        $this->actingAs($this->admin)->get(route('admin.registrants.show', $this->registrant))->assertInertia(fn (AssertableInertia $page) => $page
            ->where('payments.0.method', 'Beasiswa')
            ->where('payments.0.receipt', false));
    });

    test('staff who may not see payments get none', function () {
        $payment = Payment::factory()->create(['status' => PaymentStatus::Verified]);
        PaymentAllocation::factory()->create(['payment_id' => $payment->id, 'bill_item_id' => $this->item->id, 'amount' => 1000]);

        $this->actingAs(registrantViewer())->get(route('admin.registrants.show', $this->registrant))->assertInertia(fn (AssertableInertia $page) => $page
            ->where('payments', []));
    });
});

describe('the parent account', function () {
    beforeEach(function () {
        $this->parent = userWithRole(DefaultRole::User);
        $this->registrant->update(['registered_by_person_id' => $this->parent->person_id]);
    });

    test('its email and whether it is verified are shown, with the reset button only for those who may reset', function () {
        $this->parent->forceFill(['email_verified_at' => null])->save();

        $this->actingAs($this->admin)->get(route('admin.registrants.show', $this->registrant))->assertInertia(fn (AssertableInertia $page) => $page
            ->where('parentAccount', ['email' => $this->parent->email, 'verified' => false, 'can_reset' => true]));

        $this->actingAs(registrantViewer())->get(route('admin.registrants.show', $this->registrant))->assertInertia(fn (AssertableInertia $page) => $page
            ->where('parentAccount.verified', false)
            ->where('parentAccount.can_reset', false));
    });

    test('it is hidden while the NIK check is open', function () {
        $this->registrant->update(['needs_identity_review' => true]);

        $this->actingAs($this->admin)->get(route('admin.registrants.show', $this->registrant))->assertInertia(fn (AssertableInertia $page) => $page
            ->where('parentAccount', null));
    });
});

test('the page still opens for a registration with nothing on it yet', function () {
    $this->actingAs($this->admin)->get(route('admin.registrants.show', Registrant::factory()->create()))->assertOk();
});
