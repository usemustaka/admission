<?php

use App\Enums\BillType;
use App\Enums\DefaultRole;
use App\Models\ExamAspect;
use App\Models\ExamSession;
use App\Models\Ruleset;
use App\Models\RulesetBill;
use App\Models\RulesetBillItem;
use Illuminate\Support\Facades\Route;

beforeEach(function () {
    $this->actingAs(userWithRole(DefaultRole::Admin));
    $this->ruleset = Ruleset::factory()->create();
});

describe('bills', function () {
    test('registration and settlement bills are saved with their items', function () {
        $this->put(route('admin.rulesets.bills', $this->ruleset), ['bills' => [
            'registration' => [['name' => 'Formulir', 'amount' => 150000]],
            'settlement' => [['name' => 'Uang pangkal', 'amount' => 4000000], ['name' => 'Seragam', 'amount' => 750000]],
        ]])->assertRedirect();

        $settlement = $this->ruleset->bills()->where('type', BillType::Settlement)->firstOrFail();

        expect($this->ruleset->bills)->toHaveCount(2)
            ->and($settlement->items->pluck('name')->all())->toBe(['Uang pangkal', 'Seragam'])
            ->and($settlement->items->sum('amount'))->toBe(4750000);
    });

    test('a bill type without items means no bill at all', function () {
        $bill = RulesetBill::factory()->create(['ruleset_id' => $this->ruleset->id, 'type' => BillType::Registration]);
        RulesetBillItem::factory()->create(['ruleset_bill_id' => $bill->id]);

        $this->put(route('admin.rulesets.bills', $this->ruleset), ['bills' => ['registration' => [], 'settlement' => []]])->assertRedirect();

        expect($this->ruleset->bills()->count())->toBe(0);
    });

    test('items keep their id when edited and disappear when removed', function () {
        $bill = RulesetBill::factory()->create(['ruleset_id' => $this->ruleset->id, 'type' => BillType::Settlement]);
        $keep = RulesetBillItem::factory()->create(['ruleset_bill_id' => $bill->id, 'name' => 'Lama', 'amount' => 100]);
        RulesetBillItem::factory()->create(['ruleset_bill_id' => $bill->id]);

        $this->put(route('admin.rulesets.bills', $this->ruleset), ['bills' => [
            'registration' => [],
            'settlement' => [['id' => $keep->id, 'name' => 'Diubah', 'amount' => 250]],
        ]]);

        expect($bill->items()->count())->toBe(1)
            ->and($keep->refresh()->name)->toBe('Diubah')
            ->and($keep->amount)->toBe(250);
    });

    test('an id from another ruleset is not touched', function () {
        $foreign = RulesetBillItem::factory()->create(['name' => 'Milik lain']);

        $this->put(route('admin.rulesets.bills', $this->ruleset), ['bills' => [
            'registration' => [['id' => $foreign->id, 'name' => 'Baru', 'amount' => 1000]],
            'settlement' => [],
        ]]);

        expect($foreign->refresh()->name)->toBe('Milik lain')
            ->and($this->ruleset->bills()->firstOrFail()->items()->count())->toBe(1);
    });

    test('names and amounts are required and amounts cannot be negative', function () {
        $this->put(route('admin.rulesets.bills', $this->ruleset), ['bills' => [
            'registration' => [['name' => '', 'amount' => -5]],
            'settlement' => [],
        ]])->assertSessionHasErrors(['bills.registration.0.name', 'bills.registration.0.amount']);
    });
});

describe('exam aspects', function () {
    test('aspects are saved with their KKM, in order', function () {
        $this->put(route('admin.rulesets.exam-aspects', $this->ruleset), ['aspects' => [
            ['name' => 'Tes Tulis', 'passing_score' => 70],
            ['name' => 'Wawancara', 'passing_score' => 60],
        ]])->assertRedirect();

        expect($this->ruleset->examAspects->map(fn ($a) => [$a->name, $a->passing_score])->all())
            ->toBe([['Tes Tulis', 70], ['Wawancara', 60]]);
    });

    test('the KKM has to be between 0 and 100 and names cannot repeat', function () {
        $this->put(route('admin.rulesets.exam-aspects', $this->ruleset), ['aspects' => [
            ['name' => 'Tes Tulis', 'passing_score' => 101],
            ['name' => 'tes tulis', 'passing_score' => 50],
        ]])->assertSessionHasErrors(['aspects.0.passing_score', 'aspects.0.name', 'aspects.1.name']);
    });

    test('removed aspects are deleted and existing ones keep their id', function () {
        $keep = ExamAspect::factory()->create(['ruleset_id' => $this->ruleset->id, 'name' => 'A']);
        ExamAspect::factory()->create(['ruleset_id' => $this->ruleset->id, 'name' => 'B']);

        $this->put(route('admin.rulesets.exam-aspects', $this->ruleset), ['aspects' => [['id' => $keep->id, 'name' => 'A2', 'passing_score' => 80]]]);

        expect($this->ruleset->examAspects()->count())->toBe(1)->and($keep->refresh()->name)->toBe('A2');
    });
});

test('operators cannot edit the content of a ruleset', function () {
    $this->actingAs(userWithRole(DefaultRole::Operator));

    $this->put(route('admin.rulesets.bills', $this->ruleset), ['bills' => ['registration' => [], 'settlement' => []]])->assertForbidden();
    $this->put(route('admin.rulesets.exam-aspects', $this->ruleset), ['aspects' => []])->assertForbidden();
    $this->put(route('admin.exams.schedule.update'), ['days' => []])->assertForbidden();
});

test('the detail page shows what the ruleset contains, and no exam schedule', function () {
    $bill = RulesetBill::factory()->create(['ruleset_id' => $this->ruleset->id, 'type' => BillType::Settlement]);
    RulesetBillItem::factory()->create(['ruleset_bill_id' => $bill->id, 'name' => 'Uang pangkal', 'amount' => 1000]);
    ExamSession::factory()->create(['start_time' => '08:00:00']);

    $this->get(route('admin.rulesets.show', $this->ruleset))->assertInertia(fn ($page) => $page
        ->where('bills.settlement.0.name', 'Uang pangkal')
        ->where('bills.registration', [])
        ->missing('days'));
});

test('the exam schedule is no longer edited through a ruleset', function () {
    expect(Route::has('admin.rulesets.exam-schedule'))->toBeFalse()
        ->and(method_exists(Ruleset::class, 'examDays'))->toBeFalse();
});
