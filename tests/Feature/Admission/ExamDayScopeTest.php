<?php

use App\Actions\Admission\ChooseExamSession;
use App\Enums\DefaultRole;
use App\Enums\RegistrantStatus;
use App\Models\ExamDay;
use App\Models\ExamSession;
use App\Models\Organization;
use App\Models\Registrant;
use App\Models\Ruleset;
use App\Models\User;
use App\Models\Wave;
use App\Support\ExamSchedulePresenter;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $this->organization = Organization::factory()->create();
    $this->otherOrganization = Organization::factory()->create();
    $this->wave = Wave::factory()->create();
    $this->otherWave = Wave::factory()->create();

    $this->registrant = Registrant::factory()->create(['organization_id' => $this->organization->id, 'wave_id' => $this->wave->id]);

    $this->dayFor = fn (?Organization $organization, ?Wave $wave, int $quota = 30, bool $withSession = true): ExamDay => tap(
        ExamDay::factory()->create(['organization_id' => $organization?->id, 'wave_id' => $wave?->id]),
        fn (ExamDay $day) => $withSession ? ExamSession::factory()->create(['exam_day_id' => $day->id, 'quota' => $quota]) : null,
    );
});

describe('which days a registrant can pick from', function () {
    test('the scope keeps days for its organization and wave, or for all of either', function () {
        $exact = ($this->dayFor)($this->organization, $this->wave);
        $everyOrganization = ($this->dayFor)(null, $this->wave);
        $everyWave = ($this->dayFor)($this->organization, null);
        $everyone = ($this->dayFor)(null, null);
        ($this->dayFor)($this->otherOrganization, $this->wave);
        ($this->dayFor)($this->organization, $this->otherWave);
        ($this->dayFor)($this->otherOrganization, null);
        ($this->dayFor)(null, $this->otherWave);
        ($this->dayFor)($this->otherOrganization, $this->otherWave);

        $ids = ExamDay::query()->forRegistrant($this->registrant)->pluck('id')->sort()->values()->all();

        expect($ids)->toBe(collect([$exact, $everyOrganization, $everyWave, $everyone])->pluck('id')->sort()->values()->all());
    });

    test('an exact scope matches only the days of that scope, where null means all', function () {
        $exact = ($this->dayFor)($this->organization, $this->wave);
        $everyone = ($this->dayFor)(null, null);
        ($this->dayFor)(null, $this->wave);
        ($this->dayFor)($this->organization, null);

        expect(ExamDay::query()->inScope($this->organization->id, $this->wave->id)->pluck('id')->all())->toBe([$exact->id])
            ->and(ExamDay::query()->inScope(null, null)->pluck('id')->all())->toBe([$everyone->id]);
    });

    test('the picker lists the days of its scope, not those of another organization or wave', function () {
        $mine = ($this->dayFor)($this->organization, $this->wave);
        $everyone = ($this->dayFor)(null, null);
        ($this->dayFor)($this->otherOrganization, $this->wave);
        ($this->dayFor)($this->organization, $this->otherWave);
        ($this->dayFor)($this->organization, $this->wave, withSession: false);

        $days = ExamSchedulePresenter::days($this->registrant);

        expect(collect($days)->pluck('date')->sort()->values()->all())->toBe(collect([$mine, $everyone])->map->date->map->toDateString()->sort()->values()->all())
            ->and($days[0]['sessions'][0])->toHaveKeys(['id', 'label', 'quota', 'taken']);
    });

    test('a registration has an exam schedule when a day with sessions is for its scope', function () {
        ($this->dayFor)($this->otherOrganization, $this->wave);
        ($this->dayFor)($this->organization, $this->otherWave);
        ($this->dayFor)($this->organization, $this->wave, withSession: false);

        expect($this->registrant->hasExamSchedule())->toBeFalse();

        ($this->dayFor)($this->organization, null);

        expect($this->registrant->hasExamSchedule())->toBeTrue()
            ->and(Registrant::factory()->create()->hasExamSchedule())->toBeFalse();
    });

    test('a schedule for all organizations and waves is open to everyone', function () {
        ($this->dayFor)(null, null);

        expect($this->registrant->hasExamSchedule())->toBeTrue()
            ->and(Registrant::factory()->create()->hasExamSchedule())->toBeTrue();
    });

    test('the ruleset of a registration plays no part', function () {
        $session = ExamSession::factory()->create(['exam_day_id' => ($this->dayFor)($this->organization, $this->wave, withSession: false)->id]);
        $withRuleset = Registrant::factory()->create([
            'organization_id' => $this->organization->id,
            'wave_id' => $this->wave->id,
            'ruleset_id' => Ruleset::factory()->create(['organization_id' => $this->otherOrganization->id])->id,
        ]);

        expect($this->registrant->hasExamSchedule())->toBeTrue()
            ->and($withRuleset->hasExamSchedule())->toBeTrue()
            ->and(ExamSchedulePresenter::days($withRuleset)[0]['sessions'][0]['id'])->toBe((string) $session->id);
    });
});

describe('choosing a session', function () {
    test('a registrant can choose a session of every organization and wave', function () {
        $session = ($this->dayFor)(null, null)->sessions()->firstOrFail();

        app(ChooseExamSession::class)->handle($this->registrant, $session);

        expect($this->registrant->refresh()->exam_session_id)->toBe($session->id);
    });

    test('a registrant can choose a session of its organization and wave, or of all waves of its organization', function () {
        $exact = ($this->dayFor)($this->organization, $this->wave)->sessions()->firstOrFail();
        $everyWave = ($this->dayFor)($this->organization, null)->sessions()->firstOrFail();
        $everyOrganization = ($this->dayFor)(null, $this->wave)->sessions()->firstOrFail();

        foreach ([$exact, $everyWave, $everyOrganization] as $session) {
            app(ChooseExamSession::class)->handle($this->registrant, $session);

            expect($this->registrant->refresh()->exam_session_id)->toBe($session->id);
        }
    });

    test('a session of another organization or another wave cannot be chosen', function () {
        $otherOrganization = ($this->dayFor)($this->otherOrganization, $this->wave)->sessions()->firstOrFail();
        $otherWave = ($this->dayFor)($this->organization, $this->otherWave)->sessions()->firstOrFail();
        $otherBoth = ($this->dayFor)($this->otherOrganization, null)->sessions()->firstOrFail();

        foreach ([$otherOrganization, $otherWave, $otherBoth] as $session) {
            expect(fn () => app(ChooseExamSession::class)->handle($this->registrant, $session))
                ->toThrow(ValidationException::class, 'Sesi ini bukan untuk pendaftaran ini.');
        }

        expect($this->registrant->refresh()->exam_session_id)->toBeNull();
    });

    test('a parent cannot pick a session of another organization through the portal', function () {
        $parent = User::factory()->create();
        $registrant = admissionRegistrant(RegistrantStatus::Draft, ['organization_id' => $this->organization->id, 'wave_id' => $this->wave->id]);
        DB::table('parentships')->where('person_id', $registrant->person_id)->update(['related_person_id' => $parent->person_id]);
        $foreign = ($this->dayFor)($this->otherOrganization, $this->wave)->sessions()->firstOrFail();

        $this->actingAs($parent)
            ->post(route('portal.children.exam.update', $registrant), ['exam_session_id' => $foreign->id])
            ->assertSessionHasErrors(['exam_session_id' => 'Sesi ini bukan untuk pendaftaran ini.']);

        expect($registrant->refresh()->exam_session_id)->toBeNull();
    });

    test('registrants of different rulesets in the same organization and wave share one quota', function () {
        $session = ($this->dayFor)($this->organization, $this->wave, quota: 1)->sessions()->firstOrFail();
        $first = Registrant::factory()->create([
            'organization_id' => $this->organization->id,
            'wave_id' => $this->wave->id,
            'ruleset_id' => Ruleset::factory()->create()->id,
        ]);
        $second = Registrant::factory()->create([
            'organization_id' => $this->organization->id,
            'wave_id' => $this->wave->id,
            'ruleset_id' => Ruleset::factory()->create()->id,
        ]);

        app(ChooseExamSession::class)->handle($first, $session);

        expect(fn () => app(ChooseExamSession::class)->handle($second, $session))
            ->toThrow(ValidationException::class, 'penuh')
            ->and($second->refresh()->exam_session_id)->toBeNull()
            ->and($first->refresh()->exam_session_id)->toBe($session->id);
    });

    test('a ruleset made without any schedule still lets its registrants pick the sessions of their lembaga', function () {
        $this->actingAs(userWithRole(DefaultRole::Admin));
        $this->post(route('admin.rulesets.store'), ['name' => 'Aturan tanpa jadwal', 'organization_id' => $this->organization->id, 'is_active' => true])->assertSessionHasNoErrors();

        $ruleset = Ruleset::query()->where('name', 'Aturan tanpa jadwal')->firstOrFail();
        $session = ($this->dayFor)($this->organization, null)->sessions()->firstOrFail();
        $this->registrant->update(['ruleset_id' => $ruleset->id]);

        app(ChooseExamSession::class)->handle($this->registrant, $session);

        expect(ExamDay::query()->count())->toBe(1)
            ->and($this->registrant->refresh()->exam_session_id)->toBe($session->id);
    });
});

describe('the list filter for registrants who have not picked a session', function () {
    beforeEach(function () {
        $this->actingAs(userWithRole(DefaultRole::Admin));

        $this->waiting = fn () => collect($this->get(route('admin.registrants.index', ['belum_ujian' => 1]))->inertiaProps('registrants.data'))->pluck('id')->sort()->values()->all();
    });

    test('it follows the schedule of the organization and wave, not the ruleset', function () {
        $sameScopeOtherRuleset = Registrant::factory()->create([
            'organization_id' => $this->organization->id,
            'wave_id' => $this->wave->id,
            'ruleset_id' => Ruleset::factory()->create()->id,
        ]);
        $otherOrganization = Registrant::factory()->create(['organization_id' => $this->otherOrganization->id, 'wave_id' => $this->wave->id]);
        $otherWave = Registrant::factory()->create(['organization_id' => $this->organization->id, 'wave_id' => $this->otherWave->id]);

        expect(($this->waiting)())->toBe([]);

        ($this->dayFor)($this->organization, $this->wave);

        expect(($this->waiting)())->toBe(collect([$this->registrant, $sameScopeOtherRuleset])->pluck('id')->sort()->values()->all())
            ->and(in_array($otherOrganization->id, ($this->waiting)(), true))->toBeFalse()
            ->and(in_array($otherWave->id, ($this->waiting)(), true))->toBeFalse();
    });

    test('a schedule for all organizations and waves puts everyone who has not picked on the list', function () {
        $other = Registrant::factory()->create();
        $picked = Registrant::factory()->create();
        $day = ($this->dayFor)(null, null);
        $picked->update(['exam_session_id' => $day->sessions()->firstOrFail()->id]);

        expect(($this->waiting)())->toBe(collect([$this->registrant, $other])->pluck('id')->sort()->values()->all());
    });

    test('a day without sessions does not count as a schedule', function () {
        ($this->dayFor)($this->organization, $this->wave, withSession: false);

        expect(($this->waiting)())->toBe([]);
    });
});
