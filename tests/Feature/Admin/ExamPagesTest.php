<?php

use App\Enums\Ability;
use App\Enums\DefaultRole;
use App\Enums\RegistrantStatus;
use App\Models\ExamDay;
use App\Models\ExamSession;
use App\Models\Organization;
use App\Models\Person;
use App\Models\Registrant;
use App\Models\Ruleset;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Inertia\Testing\AssertableInertia;

beforeEach(function () {
    $this->withoutVite();

    $this->sessionOn = function (string $date, ?Ruleset $ruleset = null, int $quota = 30, string $label = 'Sesi 1'): ExamSession {
        $ruleset ??= Ruleset::factory()->create();

        return ExamSession::factory()->create([
            'label' => $label,
            'quota' => $quota,
            'exam_day_id' => (ExamDay::query()->where('ruleset_id', $ruleset->id)->whereDate('date', $date)->first()
                ?? ExamDay::factory()->create(['ruleset_id' => $ruleset->id, 'date' => $date]))->id,
        ]);
    };

    $this->join = function (ExamSession $session, ?array $scores = null, array $attributes = []): Registrant {
        return admissionRegistrant(RegistrantStatus::Review, [
            'ruleset_id' => $session->day->ruleset_id,
            'exam_session_id' => $session->id,
            'exam_scores' => $scores,
            ...$attributes,
        ]);
    };
});

describe('the exams page', function () {
    test('staff who may view registrants can open it, others cannot', function () {
        $this->actingAs(registrantViewer())->get(route('admin.exams.index'))->assertOk();

        $noViewRight = User::factory()->create()->givePermissionTo(Ability::AdminAccess->value);

        $this->actingAs($noViewRight)->get(route('admin.exams.index'))->assertForbidden();
        $this->actingAs(User::factory()->create())->get(route('admin.exams.index'))->assertForbidden();
    });

    test('by default it lists the sessions from today onward, grouped by day, and "semua" adds the past', function () {
        $this->actingAs(registrantViewer());
        $ruleset = Ruleset::factory()->create();
        ($this->sessionOn)(now()->subDays(3)->toDateString(), $ruleset, label: 'Sesi lampau');
        $today = ($this->sessionOn)(now()->toDateString(), $ruleset, label: 'Sesi 2');
        ($this->sessionOn)(now()->toDateString(), $ruleset, label: 'Sesi 1');
        ($this->sessionOn)(now()->addDays(2)->toDateString(), $ruleset, label: 'Sesi nanti');
        $today->update(['start_time' => '13:00:00', 'end_time' => '15:00:00']);

        $this->get(route('admin.exams.index'))->assertInertia(fn (AssertableInertia $page) => $page
            ->component('admin/exams/index')
            ->has('days', 2)
            ->where('days.0.date', now()->toDateString())
            ->has('days.0.sessions', 2)
            ->where('days.0.sessions.0.label', fn (string $label) => str_starts_with($label, 'Sesi 1'))
            ->where('days.0.sessions.1.id', $today->id)
            ->where('summary.sessions', 3));

        $this->get(route('admin.exams.index', ['tanggal' => 'semua']))->assertInertia(fn (AssertableInertia $page) => $page
            ->has('days', 3)
            ->where('filters.tanggal', 'semua'));
    });

    test('a date or a range narrows the sessions, and a bad date falls back to the default', function () {
        $this->actingAs(registrantViewer());
        $ruleset = Ruleset::factory()->create();
        $first = ($this->sessionOn)('2026-12-01', $ruleset);
        ($this->sessionOn)('2026-12-02', $ruleset);
        ($this->sessionOn)('2026-12-05', $ruleset);

        $this->get(route('admin.exams.index', ['tanggal' => '2026-12-01']))->assertInertia(fn (AssertableInertia $page) => $page
            ->has('days', 1)
            ->where('days.0.sessions.0.id', $first->id));

        $this->get(route('admin.exams.index', ['tanggal' => '2026-12-01', 'sampai' => '2026-12-02']))->assertInertia(fn (AssertableInertia $page) => $page
            ->has('days', 2)
            ->where('filters.sampai', '2026-12-02'));

        $this->get(route('admin.exams.index', ['tanggal' => '2026-02-31']))->assertInertia(fn (AssertableInertia $page) => $page
            ->where('filters.tanggal', ''));
    });

    test('the organization filter keeps sessions of rulesets for that organization or for any', function () {
        $this->actingAs(registrantViewer());
        $date = now()->addDay()->toDateString();
        $sd = Organization::factory()->create();
        $smp = Organization::factory()->create();
        $forSd = ($this->sessionOn)($date, Ruleset::factory()->create(['organization_id' => $sd->id]));
        $forSmp = ($this->sessionOn)($date, Ruleset::factory()->create(['organization_id' => $smp->id]));
        $forAny = ($this->sessionOn)($date, Ruleset::factory()->create(['organization_id' => null]));

        $this->get(route('admin.exams.index', ['lembaga' => $sd->id]))->assertInertia(fn (AssertableInertia $page) => $page
            ->has('days.0.sessions', 2)
            ->where('days.0.sessions', fn ($sessions) => collect($sessions)->pluck('id')->sort()->values()->all() === collect([$forSd->id, $forAny->id])->sort()->values()->all()));

        expect($forSmp->id)->not->toBe($forSd->id);
    });

    test('a session counts who picked it, who still lacks scores, and who passed or not', function () {
        $this->actingAs(registrantViewer());
        $session = ($this->sessionOn)(now()->addDay()->toDateString(), quota: 10);
        ($this->join)($session, [80, 70]);
        ($this->join)($session, [80, 50]);
        ($this->join)($session, [80, null]);
        ($this->join)($session);

        $this->get(route('admin.exams.index'))->assertInertia(fn (AssertableInertia $page) => $page
            ->where('days.0.sessions.0.taken', 4)
            ->where('days.0.sessions.0.quota', 10)
            ->where('days.0.sessions.0.missing', 2)
            ->where('days.0.sessions.0.passed', 1)
            ->where('days.0.sessions.0.failed', 1)
            ->where('days.0.sessions.0.needs_grading', false));
    });

    test('"perlu dinilai" keeps the sessions of today or the past that still lack scores', function () {
        $this->actingAs(registrantViewer());
        $ruleset = Ruleset::factory()->create();
        $pastMissing = ($this->sessionOn)(now()->subDays(2)->toDateString(), $ruleset);
        $pastDone = ($this->sessionOn)(now()->subDays(3)->toDateString(), $ruleset);
        $todayMissing = ($this->sessionOn)(now()->toDateString(), $ruleset);
        $futureMissing = ($this->sessionOn)(now()->addDays(2)->toDateString(), $ruleset);

        ($this->join)($pastMissing, [80, null]);
        ($this->join)($pastDone, [80, 70]);
        ($this->join)($todayMissing);
        ($this->join)($futureMissing);

        $this->get(route('admin.exams.index', ['perlu_dinilai' => 1]))->assertInertia(fn (AssertableInertia $page) => $page
            ->has('days', 2)
            ->where('days', fn ($days) => collect($days)->flatMap(fn ($day) => $day['sessions'])->pluck('id')->sort()->values()->all() === collect([$pastMissing->id, $todayMissing->id])->sort()->values()->all())
            ->where('summary.needs_grading', 2)
            ->where('filters.perlu_dinilai', true));
    });
});

describe('the attendance list of a session', function () {
    test('it lists who picked the session by name with the contact of the family', function () {
        $session = ($this->sessionOn)(now()->addDay()->toDateString(), quota: 5);
        $zaki = ($this->join)($session);
        $zaki->person->update(['name' => 'Zaki']);
        $amir = ($this->join)($session);
        $amir->person->update(['name' => 'Amir', 'nik' => '3201000000000009']);
        ($this->join)(ExamSession::factory()->create(['exam_day_id' => $session->exam_day_id]));

        $this->actingAs(registrantViewer())->get(route('admin.exams.sessions.show', $session))->assertInertia(fn (AssertableInertia $page) => $page
            ->component('admin/exams/session')
            ->where('session.id', $session->id)
            ->where('session.taken', 2)
            ->has('registrants', 2)
            ->where('registrants.0.name', 'Amir')
            ->where('registrants.0.nik', '3201000000000009')
            ->where('registrants.0.contact.name', fn ($name) => is_string($name) && $name !== '')
            ->where('registrants.1.name', 'Zaki')
            ->where('can.manage', false)
            ->where('can.export', false));
    });

    test('a session may be over its quota after an override without breaking the page', function () {
        $session = ($this->sessionOn)(now()->addDay()->toDateString(), quota: 1);
        ($this->join)($session);
        ($this->join)($session);

        $this->actingAs(registrantViewer())->get(route('admin.exams.sessions.show', $session))->assertInertia(fn (AssertableInertia $page) => $page
            ->where('session.taken', 2)
            ->where('session.quota', 1));
    });

    test('only staff who may view registrants open it', function () {
        $session = ($this->sessionOn)(now()->addDay()->toDateString());

        $this->seed(RolesAndPermissionsSeeder::class);

        $this->actingAs(User::factory()->create())->get(route('admin.exams.sessions.show', $session))->assertForbidden();
        $this->actingAs(User::factory()->create()->givePermissionTo(Ability::AdminAccess->value))->get(route('admin.exams.sessions.show', $session))->assertForbidden();
    });

    test('the CSV needs the right to export and keeps names that look like formulas as text', function () {
        $session = ($this->sessionOn)(now()->addDay()->toDateString());
        $registrant = ($this->join)($session);
        $registrant->person->update(['name' => '=HYPERLINK("http://evil")', 'nik' => '3201000000000001']);

        $this->actingAs(registrantViewer())->get(route('admin.exams.sessions.export', $session))->assertForbidden();

        $content = $this->actingAs(userWithRole(DefaultRole::Admin))
            ->get(route('admin.exams.sessions.export', $session))
            ->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=UTF-8')
            ->streamedContent();

        expect($content)->toStartWith("\xEF\xBB\xBF")
            ->and($content)->toContain('No,Nama,NIK,NISN,Lembaga,"Nama kontak","Nomor HP kontak",Pembayaran,"Tanda tangan"')
            ->and($content)->toContain('"\'=HYPERLINK(""http://evil"")"')
            ->and($content)->toContain('"=""3201000000000001"""')
            ->and($content)->not->toContain(',=HYPERLINK');
    });

    test('a registrant with no phone shows without a contact', function () {
        $session = ($this->sessionOn)(now()->addDay()->toDateString());
        $registrant = ($this->join)($session);
        Person::query()->whereKey($registrant->person->parentships()->first()->related_person_id)->update(['phone' => null]);

        $this->actingAs(registrantViewer())->get(route('admin.exams.sessions.show', $session))->assertInertia(fn (AssertableInertia $page) => $page
            ->where('registrants.0.contact', null));
    });
});
