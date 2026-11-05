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
use App\Models\Wave;
use Database\Seeders\RolesAndPermissionsSeeder;
use Inertia\Testing\AssertableInertia;

beforeEach(function () {
    $this->withoutVite();

    $this->sessionOn = function (string $date, ?Organization $organization = null, int $quota = 30, string $label = 'Sesi 1', ?Wave $wave = null): ExamSession {
        $day = ExamDay::query()->inScope($organization?->id, $wave?->id)->whereDate('date', $date)->first()
            ?? ExamDay::factory()->create(['organization_id' => $organization?->id, 'wave_id' => $wave?->id, 'date' => $date]);

        return ExamSession::factory()->create(['label' => $label, 'quota' => $quota, 'exam_day_id' => $day->id]);
    };

    $this->join = function (ExamSession $session, ?array $scores = null, array $attributes = []): Registrant {
        return admissionRegistrant(RegistrantStatus::Review, [
            ...array_filter(['organization_id' => $session->day->organization_id, 'wave_id' => $session->day->wave_id]),
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
        ($this->sessionOn)(now()->subDays(3)->toDateString(), label: 'Sesi lampau');
        $today = ($this->sessionOn)(now()->toDateString(), label: 'Sesi 2');
        ($this->sessionOn)(now()->toDateString(), label: 'Sesi 1');
        ($this->sessionOn)(now()->addDays(2)->toDateString(), label: 'Sesi nanti');
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
        $first = ($this->sessionOn)('2026-12-01');
        ($this->sessionOn)('2026-12-02');
        ($this->sessionOn)('2026-12-05');

        $this->get(route('admin.exams.index', ['tanggal' => '2026-12-01']))->assertInertia(fn (AssertableInertia $page) => $page
            ->has('days', 1)
            ->where('days.0.sessions.0.id', $first->id));

        $this->get(route('admin.exams.index', ['tanggal' => '2026-12-01', 'sampai' => '2026-12-02']))->assertInertia(fn (AssertableInertia $page) => $page
            ->has('days', 2)
            ->where('filters.sampai', '2026-12-02'));

        $this->get(route('admin.exams.index', ['tanggal' => '2026-02-31']))->assertInertia(fn (AssertableInertia $page) => $page
            ->where('filters.tanggal', ''));
    });

    test('the organization filter keeps the sessions for that organization or for all', function () {
        $this->actingAs(registrantViewer());
        $date = now()->addDay()->toDateString();
        $sd = Organization::factory()->create();
        $smp = Organization::factory()->create();
        $forSd = ($this->sessionOn)($date, $sd);
        $forSmp = ($this->sessionOn)($date, $smp);
        $forAll = ($this->sessionOn)($date);

        $this->get(route('admin.exams.index', ['lembaga' => $sd->id]))->assertInertia(fn (AssertableInertia $page) => $page
            ->has('days.0.sessions', 2)
            ->where('days.0.sessions', fn ($sessions) => collect($sessions)->pluck('id')->sort()->values()->all() === collect([$forSd->id, $forAll->id])->sort()->values()->all())
            ->where('filters.lembaga', (string) $sd->id));

        $this->get(route('admin.exams.index'))->assertInertia(fn (AssertableInertia $page) => $page
            ->where('summary.sessions', 3));

        expect($forSmp->id)->not->toBe($forSd->id);
    });

    test('a session says which organization and wave it is for, and null when it is for all', function () {
        $this->actingAs(registrantViewer());
        $date = now()->addDay()->toDateString();
        $organization = Organization::factory()->create();
        $wave = Wave::factory()->create();
        ($this->sessionOn)($date, $organization, label: 'Sesi khusus', wave: $wave);
        ($this->sessionOn)($date, label: 'Sesi umum');

        $this->get(route('admin.exams.index'))->assertInertia(fn (AssertableInertia $page) => $page
            ->where('days.0.sessions', fn ($sessions) => collect($sessions)->every(fn (array $row): bool => ! array_key_exists('ruleset', $row))
                && collect($sessions)->contains(fn (array $row): bool => $row['organization'] === $organization->name && $row['wave'] === $wave->name)
                && collect($sessions)->contains(fn (array $row): bool => $row['organization'] === null && $row['wave'] === null)));
    });

    test('a session shared by registrants of two rulesets is listed once with everyone counted', function () {
        $this->actingAs(registrantViewer());
        $organization = Organization::factory()->create();
        $session = ($this->sessionOn)(now()->addDay()->toDateString(), $organization);
        $other = ($this->sessionOn)(now()->addDay()->toDateString(), $organization, label: 'Sesi 2');
        $first = ($this->join)($session);
        $second = ($this->join)($session);
        $second->update(['ruleset_id' => Ruleset::factory()->create()->id]);

        expect($first->ruleset_id)->not->toBe($second->refresh()->ruleset_id);

        $this->get(route('admin.exams.index', ['lembaga' => $organization->id]))->assertInertia(fn (AssertableInertia $page) => $page
            ->has('days.0.sessions', 2)
            ->where('days.0.sessions', fn ($sessions) => collect($sessions)->firstWhere('id', $session->id)['taken'] === 2
                && collect($sessions)->firstWhere('id', $other->id)['taken'] === 0));
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
        $pastMissing = ($this->sessionOn)(now()->subDays(2)->toDateString());
        $pastDone = ($this->sessionOn)(now()->subDays(3)->toDateString());
        $todayMissing = ($this->sessionOn)(now()->toDateString());
        $futureMissing = ($this->sessionOn)(now()->addDays(2)->toDateString());

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
