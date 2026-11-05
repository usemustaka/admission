<?php

use App\Actions\Billing\IssueBill;
use App\Enums\BillStatus;
use App\Enums\BillType;
use App\Enums\PaymentStatus;
use App\Models\Bill;
use App\Models\Organization;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Models\Registrant;
use App\Models\Ruleset;
use App\Models\RulesetBill;
use App\Models\RulesetBillItem;
use App\Models\User;
use App\Models\Wave;

describe('issuing', function () {
    function snapshotWith(array $bills): array
    {
        return ['ruleset_id' => 1, 'name' => 'x', 'exam_aspects' => [], 'bills' => $bills];
    }

    test('a bill is issued from the ruleset copy on the registration', function () {
        $registrant = Registrant::factory()->create(['ruleset_snapshot' => snapshotWith([
            ['type' => 'registration', 'items' => [['name' => 'Formulir', 'amount' => 150000], ['name' => 'Seragam', 'amount' => 50000]]],
        ])]);

        $bill = app(IssueBill::class)->handle($registrant, BillType::Registration);

        expect($bill->type)->toBe(BillType::Registration)
            ->and($bill->items->pluck('name')->all())->toBe(['Formulir', 'Seragam'])
            ->and($bill->totalAmount())->toBe(200000);
    });

    test('nothing is issued when the ruleset has no bill of that type', function () {
        $registrant = Registrant::factory()->create(['ruleset_snapshot' => snapshotWith([])]);

        expect(app(IssueBill::class)->handle($registrant, BillType::Registration))->toBeNull()
            ->and(Bill::count())->toBe(0);
    });

    test('a bill is never issued twice', function () {
        $registrant = Registrant::factory()->create(['ruleset_snapshot' => snapshotWith([
            ['type' => 'registration', 'items' => [['name' => 'Formulir', 'amount' => 150000]]],
        ])]);

        app(IssueBill::class)->handle($registrant, BillType::Registration);
        app(IssueBill::class)->handle($registrant, BillType::Registration);

        expect(Bill::count())->toBe(1);
    });

    test('registering a child issues the registration bill, and later ruleset edits do not change it', function () {
        $ruleset = Ruleset::factory()->create();
        $rulesetBill = RulesetBill::factory()->create(['ruleset_id' => $ruleset->id, 'type' => BillType::Registration]);
        RulesetBillItem::factory()->create(['ruleset_bill_id' => $rulesetBill->id, 'name' => 'Formulir', 'amount' => 150000]);

        $this->actingAs(User::factory()->create())->post(route('portal.children.store'), [
            'name' => 'Ahmad Fauzi', 'nik' => '3201123456789012', 'gender' => 'male', 'relation' => 'father', 'kind' => 'biological',
            'organization_id' => Organization::factory()->create()->id, 'wave_id' => Wave::factory()->create()->id,
        ])->assertSessionHasNoErrors();

        $ruleset->bills()->delete();

        $bill = Bill::firstOrFail();

        expect($bill->type)->toBe(BillType::Registration)->and($bill->totalAmount())->toBe(150000)
            ->and(Registrant::firstOrFail()->bills)->toHaveCount(1);
    });

    test('a ruleset without a registration bill gives the registrant no bill at all', function () {
        Ruleset::factory()->create();

        $this->actingAs(User::factory()->create())->post(route('portal.children.store'), [
            'name' => 'Ahmad Fauzi', 'nik' => '3201123456789012', 'gender' => 'male', 'relation' => 'father', 'kind' => 'biological',
            'organization_id' => Organization::factory()->create()->id, 'wave_id' => Wave::factory()->create()->id,
        ])->assertSessionHasNoErrors();

        expect(Bill::count())->toBe(0);
    });
});

describe('status', function () {
    beforeEach(function () {
        [$this->registrant, $this->bill] = billedChild(User::factory()->create(), [100000, 50000]);
        [$this->first, $this->second] = $this->bill->items->all();
        $status = fn () => Bill::find($this->bill->id)->loadTotals()->status();
        $this->status = $status;
    });

    test('a bill nobody paid is unpaid', function () {
        expect(($this->status)())->toBe(BillStatus::Unpaid);
    });

    test('paying one item or part of an item makes the bill partially paid', function () {
        verifiedPayment($this->first, 40000);

        expect(($this->status)())->toBe(BillStatus::Partial)
            ->and(Bill::find($this->bill->id)->loadTotals()->paidAmount())->toBe(40000);
    });

    test('a bill is paid only when every item is paid in full', function () {
        verifiedPayment($this->first, 100000);
        expect(($this->status)())->toBe(BillStatus::Partial);

        verifiedPayment($this->second, 50000);
        expect(($this->status)())->toBe(BillStatus::Paid);
    });

    test('payments waiting for verification or rejected do not count', function () {
        foreach ([PaymentStatus::Pending, PaymentStatus::Rejected] as $status) {
            $payment = Payment::factory()->create(['amount' => 150000, 'status' => $status]);
            PaymentAllocation::factory()->create(['payment_id' => $payment->id, 'bill_item_id' => $this->first->id, 'amount' => 100000]);
        }

        expect(($this->status)())->toBe(BillStatus::Unpaid);
    });

    test('what waits for verification is kept out of what can still be submitted', function () {
        $pending = Payment::factory()->create(['amount' => 60000, 'status' => PaymentStatus::Pending]);
        PaymentAllocation::factory()->create(['payment_id' => $pending->id, 'bill_item_id' => $this->first->id, 'amount' => 60000]);
        verifiedPayment($this->first, 30000);

        $item = $this->first->newQuery()->withPaymentTotals()->find($this->first->id);

        expect($item->paidAmount())->toBe(30000)
            ->and($item->pendingAmount())->toBe(60000)
            ->and($item->outstandingAmount())->toBe(10000)
            ->and($item->outstandingAmount(reservePending: false))->toBe(70000);
    });

    test('the registration step is passed once the registration bill is paid in full', function () {
        $registrant = $this->registrant;
        $registrant->update(['ruleset_snapshot' => snapshotWith([['type' => 'registration', 'items' => [['name' => 'Formulir', 'amount' => 150000]]]])]);

        expect($registrant->fresh()->currentStep())->toBe('registration-bill');

        verifiedPayment($this->first, 100000);
        verifiedPayment($this->second, 50000);

        expect($registrant->fresh()->currentStep())->toBe('data');
    });
});
