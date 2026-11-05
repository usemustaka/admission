<?php

use App\Enums\BillType;
use App\Enums\DefaultRole;
use App\Models\ExamAspect;
use App\Models\ExamDay;
use App\Models\ExamSession;
use App\Models\Registrant;
use App\Models\Ruleset;
use App\Models\RulesetBill;
use App\Models\RulesetBillItem;

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

describe('exam schedule', function () {
    test('days are saved with labelled sessions, times, and quota', function () {
        $this->put(route('admin.rulesets.exam-schedule', $this->ruleset), ['days' => [[
            'date' => '2026-11-04',
            'sessions' => [
                ['label' => 'Sesi 1', 'start_time' => '08:00', 'end_time' => '10:00', 'quota' => 30],
                ['label' => 'Sesi 2', 'start_time' => '10:00', 'end_time' => '12:00', 'quota' => 25],
            ],
        ]]])->assertRedirect();

        $day = $this->ruleset->examDays()->with('sessions')->firstOrFail();

        expect($day->date->toDateString())->toBe('2026-11-04')
            ->and($day->sessions->map(fn ($s) => [$s->label, $s->start_time, $s->end_time, $s->quota])->all())
            ->toBe([['Sesi 1', '08:00:00', '10:00:00', 30], ['Sesi 2', '10:00:00', '12:00:00', 25]]);
    });

    test('sessions and days keep their id when edited, so seats already booked stay valid', function () {
        $day = ExamDay::factory()->create(['ruleset_id' => $this->ruleset->id]);
        $session = ExamSession::factory()->create(['exam_day_id' => $day->id]);
        $dropped = ExamSession::factory()->create(['exam_day_id' => $day->id]);

        $this->put(route('admin.rulesets.exam-schedule', $this->ruleset), ['days' => [[
            'id' => $day->id,
            'date' => '2027-01-10',
            'sessions' => [['id' => $session->id, 'label' => 'Sesi pagi', 'start_time' => '09:00', 'end_time' => '11:00', 'quota' => 40]],
        ]]]);

        expect($day->refresh()->date->toDateString())->toBe('2027-01-10')
            ->and($session->refresh()->label)->toBe('Sesi pagi')
            ->and($session->quota)->toBe(40)
            ->and(ExamSession::find($dropped->id))->toBeNull();
    });

    test('a day that is removed takes its sessions with it', function () {
        $day = ExamDay::factory()->create(['ruleset_id' => $this->ruleset->id]);
        ExamSession::factory()->create(['exam_day_id' => $day->id]);

        $this->put(route('admin.rulesets.exam-schedule', $this->ruleset), ['days' => []]);

        expect(ExamDay::count())->toBe(0)->and(ExamSession::count())->toBe(0);
    });

    test('a session must end after it starts, have a label, and have at least one seat', function () {
        $this->put(route('admin.rulesets.exam-schedule', $this->ruleset), ['days' => [[
            'date' => '2026-11-04',
            'sessions' => [['label' => '', 'start_time' => '10:00', 'end_time' => '09:00', 'quota' => 0]],
        ]]])->assertSessionHasErrors(['days.0.sessions.0.label', 'days.0.sessions.0.end_time', 'days.0.sessions.0.quota']);
    });

    test('the same date cannot be used twice', function () {
        $this->put(route('admin.rulesets.exam-schedule', $this->ruleset), ['days' => [
            ['date' => '2026-11-04', 'sessions' => []],
            ['date' => '2026-11-04', 'sessions' => []],
        ]])->assertSessionHasErrors('days.0.date');
    });

    test('a quota above what the database can hold is a validation error', function () {
        $this->put(route('admin.rulesets.exam-schedule', $this->ruleset), ['days' => [[
            'date' => '2026-11-04',
            'sessions' => [['label' => 'Sesi 1', 'start_time' => '08:00', 'end_time' => '10:00', 'quota' => 70000]],
        ]]])->assertSessionHasErrors('days.0.sessions.0.quota');

        expect(ExamSession::count())->toBe(0);
    });

    test('a quota can be as high as 65535', function () {
        $this->put(route('admin.rulesets.exam-schedule', $this->ruleset), ['days' => [[
            'date' => '2026-11-04',
            'sessions' => [['label' => 'Sesi 1', 'start_time' => '08:00', 'end_time' => '10:00', 'quota' => 65535]],
        ]]])->assertSessionHasNoErrors();

        expect(ExamSession::firstOrFail()->quota)->toBe(65535);
    });

    test('a session cannot get a quota below the number of registrants who picked it', function () {
        $day = ExamDay::factory()->create(['ruleset_id' => $this->ruleset->id, 'date' => '2026-11-04']);
        $session = ExamSession::factory()->create(['exam_day_id' => $day->id, 'quota' => 10]);
        Registrant::factory()->count(3)->create(['ruleset_id' => $this->ruleset->id, 'exam_session_id' => $session->id]);

        $payload = fn (int $quota) => ['days' => [[
            'id' => $day->id,
            'date' => '2026-11-04',
            'sessions' => [['id' => $session->id, 'label' => 'Sesi 1', 'start_time' => '08:00', 'end_time' => '10:00', 'quota' => $quota]],
        ]]];

        $this->put(route('admin.rulesets.exam-schedule', $this->ruleset), $payload(2))
            ->assertSessionHasErrors(['days.0.sessions.0.quota' => 'Kuota tidak boleh di bawah 3, jumlah pendaftar yang sudah memilih sesi ini.']);
        expect($session->refresh()->quota)->toBe(10);

        $this->put(route('admin.rulesets.exam-schedule', $this->ruleset), $payload(3))->assertSessionHasNoErrors();
        expect($session->refresh()->quota)->toBe(3);
    });

    test('a session that registrants picked cannot be deleted, and the error is reported on days', function () {
        $day = ExamDay::factory()->create(['ruleset_id' => $this->ruleset->id, 'date' => '2026-11-04']);
        $busy = ExamSession::factory()->create(['exam_day_id' => $day->id, 'label' => 'Sesi ramai']);
        Registrant::factory()->create(['ruleset_id' => $this->ruleset->id, 'exam_session_id' => $busy->id]);

        $this->put(route('admin.rulesets.exam-schedule', $this->ruleset), ['days' => [['id' => $day->id, 'date' => '2026-11-04', 'sessions' => []]]])
            ->assertSessionHasErrors('days');
        $this->put(route('admin.rulesets.exam-schedule', $this->ruleset), ['days' => []])
            ->assertSessionHasErrors('days');

        expect(ExamSession::find($busy->id))->not->toBeNull()->and(ExamDay::find($day->id))->not->toBeNull();
    });

    test('a picked session cannot be slipped under another day to escape the check', function () {
        $dayA = ExamDay::factory()->create(['ruleset_id' => $this->ruleset->id, 'date' => '2026-11-04']);
        $dayB = ExamDay::factory()->create(['ruleset_id' => $this->ruleset->id, 'date' => '2026-11-05']);
        $busy = ExamSession::factory()->create(['exam_day_id' => $dayA->id]);
        Registrant::factory()->create(['ruleset_id' => $this->ruleset->id, 'exam_session_id' => $busy->id]);

        $session = ['id' => $busy->id, 'label' => 'Sesi 1', 'start_time' => '08:00', 'end_time' => '10:00', 'quota' => 30];

        $this->put(route('admin.rulesets.exam-schedule', $this->ruleset), ['days' => [
            ['id' => $dayA->id, 'date' => '2026-11-04', 'sessions' => []],
            ['id' => $dayB->id, 'date' => '2026-11-05', 'sessions' => [$session]],
        ]])->assertSessionHasErrors('days');

        expect($busy->refresh()->exam_day_id)->toBe($dayA->id);
    });

    test('the detail page tells how many registrants took each session', function () {
        $day = ExamDay::factory()->create(['ruleset_id' => $this->ruleset->id]);
        $session = ExamSession::factory()->create(['exam_day_id' => $day->id]);
        Registrant::factory()->count(2)->create(['ruleset_id' => $this->ruleset->id, 'exam_session_id' => $session->id]);

        $this->get(route('admin.rulesets.show', $this->ruleset))
            ->assertInertia(fn ($page) => $page->where('days.0.sessions.0.taken', 2));
    });

    test('two days can swap their dates', function () {
        $first = ExamDay::factory()->create(['ruleset_id' => $this->ruleset->id, 'date' => '2026-11-04']);
        $second = ExamDay::factory()->create(['ruleset_id' => $this->ruleset->id, 'date' => '2026-11-05']);
        $session = ExamSession::factory()->create(['exam_day_id' => $first->id]);

        $this->put(route('admin.rulesets.exam-schedule', $this->ruleset), ['days' => [
            ['id' => $first->id, 'date' => '2026-11-05', 'sessions' => [['id' => $session->id, 'label' => 'Sesi 1', 'start_time' => '08:00', 'end_time' => '10:00', 'quota' => 30]]],
            ['id' => $second->id, 'date' => '2026-11-04', 'sessions' => []],
        ]])->assertSessionHasNoErrors()->assertRedirect();

        expect($first->refresh()->date->toDateString())->toBe('2026-11-05')
            ->and($second->refresh()->date->toDateString())->toBe('2026-11-04')
            ->and($session->refresh()->exam_day_id)->toBe($first->id);
    });

    test('days can shift onto the next days dates and a new day can take a freed date', function () {
        $first = ExamDay::factory()->create(['ruleset_id' => $this->ruleset->id, 'date' => '2026-11-04']);
        $second = ExamDay::factory()->create(['ruleset_id' => $this->ruleset->id, 'date' => '2026-11-05']);

        $this->put(route('admin.rulesets.exam-schedule', $this->ruleset), ['days' => [
            ['id' => $first->id, 'date' => '2026-11-05', 'sessions' => []],
            ['id' => $second->id, 'date' => '2026-11-06', 'sessions' => []],
            ['date' => '2026-11-04', 'sessions' => []],
        ]])->assertSessionHasNoErrors();

        expect($this->ruleset->examDays()->pluck('date')->map->toDateString()->all())->toBe(['2026-11-04', '2026-11-05', '2026-11-06'])
            ->and($first->refresh()->date->toDateString())->toBe('2026-11-05')
            ->and($second->refresh()->date->toDateString())->toBe('2026-11-06');
    });

    test('a new day on a date an existing day keeps is a validation error, not a crash', function () {
        $existing = ExamDay::factory()->create(['ruleset_id' => $this->ruleset->id, 'date' => '2026-11-04']);

        $this->put(route('admin.rulesets.exam-schedule', $this->ruleset), ['days' => [
            ['id' => $existing->id, 'date' => '2026-11-04', 'sessions' => []],
            ['date' => '2026-11-04', 'sessions' => []],
        ]])->assertSessionHasErrors('days.0.date');

        expect(ExamDay::count())->toBe(1);
    });
});

test('operators cannot edit the content of a ruleset', function () {
    $this->actingAs(userWithRole(DefaultRole::Operator));

    $this->put(route('admin.rulesets.bills', $this->ruleset), ['bills' => ['registration' => [], 'settlement' => []]])->assertForbidden();
    $this->put(route('admin.rulesets.exam-aspects', $this->ruleset), ['aspects' => []])->assertForbidden();
    $this->put(route('admin.rulesets.exam-schedule', $this->ruleset), ['days' => []])->assertForbidden();
});

test('the detail page shows what the ruleset contains', function () {
    $bill = RulesetBill::factory()->create(['ruleset_id' => $this->ruleset->id, 'type' => BillType::Settlement]);
    RulesetBillItem::factory()->create(['ruleset_bill_id' => $bill->id, 'name' => 'Uang pangkal', 'amount' => 1000]);
    $day = ExamDay::factory()->create(['ruleset_id' => $this->ruleset->id]);
    ExamSession::factory()->create(['exam_day_id' => $day->id, 'start_time' => '08:00:00']);

    $this->get(route('admin.rulesets.show', $this->ruleset))->assertInertia(fn ($page) => $page
        ->where('bills.settlement.0.name', 'Uang pangkal')
        ->where('bills.registration', [])
        ->where('days.0.sessions.0.start_time', '08:00'));
});
