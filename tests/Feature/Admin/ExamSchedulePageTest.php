<?php

use App\Enums\DefaultRole;
use App\Models\ExamDay;
use App\Models\ExamSession;
use App\Models\Organization;
use App\Models\Registrant;
use App\Models\User;
use App\Models\Wave;
use Inertia\Testing\AssertableInertia;

beforeEach(function () {
    $this->withoutVite();

    $this->actingAs(userWithRole(DefaultRole::Admin));

    $this->smp = Organization::factory()->create(['name' => 'SMP Mustaka']);
    $this->sd = Organization::factory()->create(['name' => 'SD Mustaka']);
    $this->waveOne = Wave::factory()->create(['name' => 'Gelombang 1']);
    $this->waveTwo = Wave::factory()->create(['name' => 'Gelombang 2']);

    $this->save = fn (array $days, ?Organization $organization = null, ?Wave $wave = null) => $this->put(route('admin.exams.schedule.update'), [
        'organization_id' => $organization?->id,
        'wave_id' => $wave?->id,
        'days' => $days,
    ]);

    $this->sessionOn = fn (string $date, ?Organization $organization = null, ?Wave $wave = null, string $label = 'Sesi 1'): ExamSession => ExamSession::factory()->create([
        'label' => $label,
        'exam_day_id' => ExamDay::factory()->create(['organization_id' => $organization?->id, 'wave_id' => $wave?->id, 'date' => $date])->id,
    ]);

    $this->sessionRow = fn (int $id, int $quota = 30): array => ['id' => $id, 'label' => 'Sesi 1', 'start_time' => '08:00', 'end_time' => '10:00', 'quota' => $quota];
});

describe('the schedule page', function () {
    test('only staff who may manage the exam open it', function () {
        $this->get(route('admin.exams.schedule.edit'))->assertOk();

        $this->actingAs(userWithRole(DefaultRole::Operator))->get(route('admin.exams.schedule.edit'))->assertForbidden();
        $this->actingAs(registrantViewer())->get(route('admin.exams.schedule.edit'))->assertForbidden();
        $this->actingAs(User::factory()->create())->get(route('admin.exams.schedule.edit'))->assertForbidden();
    });

    test('it shows the days of exactly the chosen scope, with seats taken', function () {
        $mine = ($this->sessionOn)('2027-03-01', $this->smp, $this->waveOne);
        Registrant::factory()->count(2)->create(['exam_session_id' => $mine->id]);
        ($this->sessionOn)('2027-03-02', $this->smp);
        ($this->sessionOn)('2027-03-03', null, $this->waveOne);
        ($this->sessionOn)('2027-03-04', $this->sd, $this->waveOne);
        ($this->sessionOn)('2027-03-05');

        $this->get(route('admin.exams.schedule.edit', ['lembaga' => $this->smp->id, 'gelombang' => $this->waveOne->id]))
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('admin/exams/schedule')
                ->where('scope', ['organization_id' => $this->smp->id, 'wave_id' => $this->waveOne->id])
                ->has('days', 1)
                ->where('days.0.id', $mine->exam_day_id)
                ->where('days.0.date', '2027-03-01')
                ->where('days.0.sessions.0.id', $mine->id)
                ->where('days.0.sessions.0.start_time', '08:00')
                ->where('days.0.sessions.0.taken', 2));

        $this->get(route('admin.exams.schedule.edit'))->assertInertia(fn (AssertableInertia $page) => $page
            ->where('scope', ['organization_id' => null, 'wave_id' => null])
            ->has('days', 1)
            ->where('days.0.date', '2027-03-05'));
    });

    test('it lists every schedule that exists with its label and counts', function () {
        ($this->sessionOn)('2027-03-01', $this->smp, $this->waveOne);
        $busy = ($this->sessionOn)('2027-03-02', $this->smp, $this->waveOne);
        ExamSession::factory()->create(['exam_day_id' => $busy->exam_day_id, 'label' => 'Sesi 2']);
        ($this->sessionOn)('2027-03-03');
        ($this->sessionOn)('2027-03-04', $this->sd);

        $this->get(route('admin.exams.schedule.edit'))->assertInertia(fn (AssertableInertia $page) => $page
            ->has('schedules', 3)
            ->where('schedules.0', ['organization_id' => $this->sd->id, 'wave_id' => null, 'label' => 'SD Mustaka · Semua gelombang', 'days' => 1, 'sessions' => 1])
            ->where('schedules.1', ['organization_id' => $this->smp->id, 'wave_id' => $this->waveOne->id, 'label' => 'SMP Mustaka · Gelombang 1', 'days' => 2, 'sessions' => 3])
            ->where('schedules.2', ['organization_id' => null, 'wave_id' => null, 'label' => 'Semua lembaga · Semua gelombang', 'days' => 1, 'sessions' => 1]));
    });

    test('it tells how many registrations the schedule is open to', function () {
        Registrant::factory()->count(2)->create(['organization_id' => $this->smp->id, 'wave_id' => $this->waveOne->id]);
        Registrant::factory()->create(['organization_id' => $this->smp->id, 'wave_id' => $this->waveTwo->id]);
        Registrant::factory()->create(['organization_id' => $this->sd->id, 'wave_id' => $this->waveOne->id]);

        $audience = fn (array $query) => $this->get(route('admin.exams.schedule.edit', $query))->inertiaProps('audience');

        expect($audience(['lembaga' => $this->smp->id, 'gelombang' => $this->waveOne->id]))->toBe(['label' => 'SMP Mustaka · Gelombang 1', 'registrants' => 2])
            ->and($audience(['lembaga' => $this->smp->id]))->toBe(['label' => 'SMP Mustaka · Semua gelombang', 'registrants' => 3])
            ->and($audience(['gelombang' => $this->waveOne->id]))->toBe(['label' => 'Semua lembaga · Gelombang 1', 'registrants' => 3])
            ->and($audience([]))->toBe(['label' => 'Semua lembaga · Semua gelombang', 'registrants' => 4]);
    });

    test('an organization or wave that does not exist falls back to "all"', function () {
        ($this->sessionOn)('2027-03-05');
        ($this->sessionOn)('2027-03-06', $this->smp);

        $this->get(route('admin.exams.schedule.edit', ['lembaga' => 9999, 'gelombang' => 9999]))->assertInertia(fn (AssertableInertia $page) => $page
            ->where('scope', ['organization_id' => null, 'wave_id' => null])
            ->has('days', 1)
            ->where('days.0.date', '2027-03-05')
            ->where('audience.label', 'Semua lembaga · Semua gelombang'));

        $this->get(route('admin.exams.schedule.edit', ['lembaga' => 'abc', 'gelombang' => '-1']))->assertInertia(fn (AssertableInertia $page) => $page
            ->where('scope', ['organization_id' => null, 'wave_id' => null]));
    });

    test('it offers the organizations and waves to choose from', function () {
        $this->get(route('admin.exams.schedule.edit'))->assertInertia(fn (AssertableInertia $page) => $page
            ->has('options.organizations', 2)
            ->has('options.waves', 2)
            ->where('options.waves', fn ($waves) => collect($waves)->pluck('label')->sort()->values()->all() === ['Gelombang 1', 'Gelombang 2']));
    });
});

describe('saving a schedule', function () {
    test('days are saved with labelled sessions, times, and quota for the chosen scope', function () {
        ($this->save)([[
            'date' => '2026-11-04',
            'sessions' => [
                ['label' => 'Sesi 1', 'start_time' => '08:00', 'end_time' => '10:00', 'quota' => 30],
                ['label' => 'Sesi 2', 'start_time' => '10:00', 'end_time' => '12:00', 'quota' => 25],
            ],
        ]], $this->smp, $this->waveOne)->assertSessionHasNoErrors();

        $day = ExamDay::query()->with('sessions')->firstOrFail();

        expect(ExamDay::count())->toBe(1)
            ->and($day->organization_id)->toBe($this->smp->id)
            ->and($day->wave_id)->toBe($this->waveOne->id)
            ->and($day->date->toDateString())->toBe('2026-11-04')
            ->and($day->sessions->map(fn ($s) => [$s->label, $s->start_time, $s->end_time, $s->quota])->all())
            ->toBe([['Sesi 1', '08:00:00', '10:00:00', 30], ['Sesi 2', '10:00:00', '12:00:00', 25]]);
    });

    test('an empty scope saves a schedule for all organizations and waves', function () {
        ($this->save)([['date' => '2026-11-04', 'sessions' => []]])->assertSessionHasNoErrors();

        expect(ExamDay::query()->firstOrFail())->organization_id->toBeNull()->wave_id->toBeNull();
    });

    test('it goes back to the edit page of the scope it saved', function () {
        ($this->save)([], $this->smp, $this->waveOne)
            ->assertRedirect(route('admin.exams.schedule.edit', ['lembaga' => $this->smp->id, 'gelombang' => $this->waveOne->id]));

        ($this->save)([], $this->smp)->assertRedirect(route('admin.exams.schedule.edit', ['lembaga' => $this->smp->id]));
        ($this->save)([])->assertRedirect(route('admin.exams.schedule.edit'));
    });

    test('saving a scope never touches the days of another scope', function () {
        $other = ($this->sessionOn)('2026-11-04', $this->sd, $this->waveOne);
        $everyone = ($this->sessionOn)('2026-11-05');
        $sameOrganization = ($this->sessionOn)('2026-11-06', $this->smp);
        $mine = ($this->sessionOn)('2026-11-07', $this->smp, $this->waveOne);

        ($this->save)([], $this->smp, $this->waveOne)->assertSessionHasNoErrors();

        expect(ExamDay::find($mine->exam_day_id))->toBeNull()
            ->and(ExamSession::find($mine->id))->toBeNull()
            ->and(ExamDay::find($other->exam_day_id))->not->toBeNull()
            ->and(ExamDay::find($everyone->exam_day_id))->not->toBeNull()
            ->and(ExamDay::find($sameOrganization->exam_day_id))->not->toBeNull()
            ->and(ExamSession::count())->toBe(3);
    });

    test('two scopes can both have a session on the same date', function () {
        $existing = ($this->sessionOn)('2026-11-04', $this->sd);

        ($this->save)([['date' => '2026-11-04', 'sessions' => []]], $this->smp)->assertSessionHasNoErrors();
        ($this->save)([['date' => '2026-11-04', 'sessions' => []]])->assertSessionHasNoErrors();

        expect(ExamDay::query()->whereDate('date', '2026-11-04')->count())->toBe(3)
            ->and(ExamDay::find($existing->exam_day_id))->not->toBeNull();
    });

    test('sessions and days keep their id when edited, so seats already booked stay valid', function () {
        $session = ($this->sessionOn)('2026-11-04', $this->smp);
        $day = $session->day;
        $dropped = ExamSession::factory()->create(['exam_day_id' => $day->id]);

        ($this->save)([[
            'id' => $day->id,
            'date' => '2027-01-10',
            'sessions' => [['id' => $session->id, 'label' => 'Sesi pagi', 'start_time' => '09:00', 'end_time' => '11:00', 'quota' => 40]],
        ]], $this->smp);

        expect($day->refresh()->date->toDateString())->toBe('2027-01-10')
            ->and($session->refresh()->label)->toBe('Sesi pagi')
            ->and($session->quota)->toBe(40)
            ->and(ExamSession::find($dropped->id))->toBeNull();
    });

    test('a day that is removed takes its sessions with it', function () {
        ($this->sessionOn)('2026-11-04');

        ($this->save)([]);

        expect(ExamDay::count())->toBe(0)->and(ExamSession::count())->toBe(0);
    });

    test('a day id of another scope is not taken over', function () {
        $foreign = ($this->sessionOn)('2026-11-04', $this->sd);

        ($this->save)([['id' => $foreign->exam_day_id, 'date' => '2026-12-01', 'sessions' => []]], $this->smp)->assertSessionHasNoErrors();

        expect($foreign->day->refresh()->date->toDateString())->toBe('2026-11-04')
            ->and($foreign->day->organization_id)->toBe($this->sd->id)
            ->and(ExamDay::query()->where('organization_id', $this->smp->id)->count())->toBe(1);
    });

    test('a session must end after it starts, have a label, and have at least one seat', function () {
        ($this->save)([[
            'date' => '2026-11-04',
            'sessions' => [['label' => '', 'start_time' => '10:00', 'end_time' => '09:00', 'quota' => 0]],
        ]])->assertSessionHasErrors(['days.0.sessions.0.label', 'days.0.sessions.0.end_time', 'days.0.sessions.0.quota']);
    });

    test('the same date cannot be used twice in one schedule', function () {
        ($this->save)([
            ['date' => '2026-11-04', 'sessions' => []],
            ['date' => '2026-11-04', 'sessions' => []],
        ], $this->smp)->assertSessionHasErrors('days.0.date');

        expect(ExamDay::count())->toBe(0);
    });

    test('a quota above what the database can hold is a validation error', function () {
        ($this->save)([[
            'date' => '2026-11-04',
            'sessions' => [['label' => 'Sesi 1', 'start_time' => '08:00', 'end_time' => '10:00', 'quota' => 70000]],
        ]])->assertSessionHasErrors('days.0.sessions.0.quota');

        expect(ExamSession::count())->toBe(0);
    });

    test('a quota can be as high as 65535', function () {
        ($this->save)([[
            'date' => '2026-11-04',
            'sessions' => [['label' => 'Sesi 1', 'start_time' => '08:00', 'end_time' => '10:00', 'quota' => 65535]],
        ]])->assertSessionHasNoErrors();

        expect(ExamSession::firstOrFail()->quota)->toBe(65535);
    });

    test('an organization or wave that does not exist is refused', function () {
        $this->put(route('admin.exams.schedule.update'), ['organization_id' => 9999, 'wave_id' => $this->waveOne->id, 'days' => []])->assertSessionHasErrors('organization_id');
        $this->put(route('admin.exams.schedule.update'), ['organization_id' => $this->smp->id, 'wave_id' => 9999, 'days' => []])->assertSessionHasErrors('wave_id');
    });

    test('a session cannot get a quota below the number of registrants who picked it', function () {
        $session = ($this->sessionOn)('2026-11-04', $this->smp);
        $session->update(['quota' => 10]);
        Registrant::factory()->count(3)->create(['organization_id' => $this->smp->id, 'exam_session_id' => $session->id]);

        $payload = fn (int $quota) => [[
            'id' => $session->exam_day_id,
            'date' => '2026-11-04',
            'sessions' => [['id' => $session->id, 'label' => 'Sesi 1', 'start_time' => '08:00', 'end_time' => '10:00', 'quota' => $quota]],
        ]];

        ($this->save)($payload(2), $this->smp)
            ->assertSessionHasErrors(['days.0.sessions.0.quota' => 'Kuota tidak boleh di bawah 3, jumlah pendaftar yang sudah memilih sesi ini.']);
        expect($session->refresh()->quota)->toBe(10);

        ($this->save)($payload(3), $this->smp)->assertSessionHasNoErrors();
        expect($session->refresh()->quota)->toBe(3);
    });

    test('a session that registrants picked cannot be deleted, and the error is reported on days', function () {
        $busy = ($this->sessionOn)('2026-11-04', $this->smp, label: 'Sesi ramai');
        Registrant::factory()->create(['organization_id' => $this->smp->id, 'exam_session_id' => $busy->id]);

        ($this->save)([['id' => $busy->exam_day_id, 'date' => '2026-11-04', 'sessions' => []]], $this->smp)->assertSessionHasErrors('days');
        ($this->save)([], $this->smp)->assertSessionHasErrors('days');

        expect(ExamSession::find($busy->id))->not->toBeNull()->and(ExamDay::find($busy->exam_day_id))->not->toBeNull();
    });

    test('a picked session cannot be slipped under another day to escape the check', function () {
        $busy = ($this->sessionOn)('2026-11-04', $this->smp);
        $dayA = $busy->exam_day_id;
        $dayB = ExamDay::factory()->create(['organization_id' => $this->smp->id, 'date' => '2026-11-05']);
        Registrant::factory()->create(['organization_id' => $this->smp->id, 'exam_session_id' => $busy->id]);

        ($this->save)([
            ['id' => $busy->exam_day_id, 'date' => '2026-11-04', 'sessions' => []],
            ['id' => $dayB->id, 'date' => '2026-11-05', 'sessions' => [($this->sessionRow)($busy->id)]],
        ], $this->smp)->assertSessionHasErrors('days');

        expect($busy->refresh()->exam_day_id)->toBe($dayA);
    });

    test('two days can swap their dates', function () {
        $session = ($this->sessionOn)('2026-11-04', $this->smp);
        $second = ExamDay::factory()->create(['organization_id' => $this->smp->id, 'date' => '2026-11-05']);

        ($this->save)([
            ['id' => $session->exam_day_id, 'date' => '2026-11-05', 'sessions' => [($this->sessionRow)($session->id)]],
            ['id' => $second->id, 'date' => '2026-11-04', 'sessions' => []],
        ], $this->smp)->assertSessionHasNoErrors()->assertRedirect();

        expect($session->day->refresh()->date->toDateString())->toBe('2026-11-05')
            ->and($second->refresh()->date->toDateString())->toBe('2026-11-04')
            ->and($session->refresh()->exam_day_id)->toBe($session->day->id);
    });

    test('days can shift onto the next days dates and a new day can take a freed date', function () {
        $first = ExamDay::factory()->create(['date' => '2026-11-04']);
        $second = ExamDay::factory()->create(['date' => '2026-11-05']);

        ($this->save)([
            ['id' => $first->id, 'date' => '2026-11-05', 'sessions' => []],
            ['id' => $second->id, 'date' => '2026-11-06', 'sessions' => []],
            ['date' => '2026-11-04', 'sessions' => []],
        ])->assertSessionHasNoErrors();

        expect(ExamDay::query()->orderBy('date')->get()->map(fn (ExamDay $day) => $day->date->toDateString())->all())->toBe(['2026-11-04', '2026-11-05', '2026-11-06'])
            ->and($first->refresh()->date->toDateString())->toBe('2026-11-05')
            ->and($second->refresh()->date->toDateString())->toBe('2026-11-06');
    });

    test('a new day on a date an existing day of the scope keeps is a validation error, not a crash', function () {
        $existing = ExamDay::factory()->create(['organization_id' => $this->smp->id, 'date' => '2026-11-04']);

        ($this->save)([
            ['id' => $existing->id, 'date' => '2026-11-04', 'sessions' => []],
            ['date' => '2026-11-04', 'sessions' => []],
        ], $this->smp)->assertSessionHasErrors('days.0.date');

        expect(ExamDay::count())->toBe(1);
    });

    test('only staff who may manage the exam save it', function () {
        $session = ($this->sessionOn)('2026-11-04');

        $this->actingAs(userWithRole(DefaultRole::Operator));
        ($this->save)([])->assertForbidden();

        $this->actingAs(User::factory()->create());
        ($this->save)([])->assertForbidden();

        expect(ExamSession::find($session->id))->not->toBeNull();
    });
});
