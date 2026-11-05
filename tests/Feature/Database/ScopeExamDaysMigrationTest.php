<?php

use App\Models\ExamDay;
use App\Models\Organization;
use App\Models\Registrant;
use App\Models\Ruleset;
use App\Models\Wave;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * The migration is run on data shaped like the old schema. The test database is migrated already, so the
 * migration is taken back first and the old-shaped rows are written with the query builder, as the models no
 * longer know `ruleset_id`.
 *
 * SQLite rebuilds a table to drop a column or a foreign key, and with foreign keys on, dropping `exam_days` would
 * cascade to its sessions. Foreign keys can only be switched off outside a transaction, so these tests leave the
 * one that RefreshDatabase opened, and migrate the database afresh afterwards because the schema was changed.
 */
beforeEach(function () {
    DB::rollBack();
    Schema::disableForeignKeyConstraints();

    $this->migration = require database_path('migrations/2026_11_06_000001_scope_exam_days_to_organization_and_wave.php');
    $this->migration->down();

    $this->organization = Organization::factory()->create();
    $this->otherOrganization = Organization::factory()->create();
    $this->wave = Wave::factory()->create();

    $this->rulesetFor = fn (?Organization $organization, ?Wave $wave): Ruleset => Ruleset::factory()->create(['organization_id' => $organization?->id, 'wave_id' => $wave?->id]);

    $this->oldDay = fn (Ruleset $ruleset, string $date): int => DB::table('exam_days')->insertGetId([
        'ruleset_id' => $ruleset->id,
        'date' => $date,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $this->oldSession = fn (int $dayId, string $start, string $end, int $quota = 30, string $label = 'Sesi'): int => DB::table('exam_sessions')->insertGetId([
        'exam_day_id' => $dayId,
        'label' => $label,
        'start_time' => $start,
        'end_time' => $end,
        'quota' => $quota,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $this->pick = fn (int $sessionId, int $count = 1): array => Registrant::factory()->count($count)->create(['exam_session_id' => $sessionId])->modelKeys();
});

test('the old shape has the ruleset on the day and no scope', function () {
    expect(Schema::hasColumn('exam_days', 'ruleset_id'))->toBeTrue()
        ->and(Schema::hasColumn('exam_days', 'organization_id'))->toBeFalse()
        ->and(Schema::hasColumn('exam_days', 'wave_id'))->toBeFalse();

    $this->migration->up();

    expect(Schema::hasColumn('exam_days', 'ruleset_id'))->toBeFalse()
        ->and(Schema::hasColumn('exam_days', 'organization_id'))->toBeTrue()
        ->and(Schema::hasColumn('exam_days', 'wave_id'))->toBeTrue();
});

test('a day takes the organization and wave of its ruleset', function () {
    $day = ($this->oldDay)(($this->rulesetFor)($this->organization, $this->wave), '2027-01-10');
    ($this->oldSession)($day, '08:00:00', '10:00:00');

    $this->migration->up();

    $migrated = ExamDay::query()->findOrFail($day);

    expect($migrated->organization_id)->toBe($this->organization->id)
        ->and($migrated->wave_id)->toBe($this->wave->id)
        ->and($migrated->sessions)->toHaveCount(1);
});

test('a ruleset for any organization or wave yields a day for all', function () {
    $everyone = ($this->oldDay)(($this->rulesetFor)(null, null), '2027-01-10');
    $anyWave = ($this->oldDay)(($this->rulesetFor)($this->organization, null), '2027-01-11');
    $anyOrganization = ($this->oldDay)(($this->rulesetFor)(null, $this->wave), '2027-01-12');

    $this->migration->up();

    expect(ExamDay::query()->findOrFail($everyone))->organization_id->toBeNull()->wave_id->toBeNull()
        ->and(ExamDay::query()->findOrFail($anyWave))->organization_id->toBe($this->organization->id)->wave_id->toBeNull()
        ->and(ExamDay::query()->findOrFail($anyOrganization))->organization_id->toBeNull()->wave_id->toBe($this->wave->id);
});

test('days of two rulesets with the same organization, wave and date merge into one', function () {
    $first = ($this->oldDay)(($this->rulesetFor)($this->organization, $this->wave), '2027-01-10');
    $second = ($this->oldDay)(($this->rulesetFor)($this->organization, $this->wave), '2027-01-10');
    $third = ($this->oldDay)(($this->rulesetFor)($this->organization, $this->wave), '2027-01-10');

    $this->migration->up();

    expect(ExamDay::query()->count())->toBe(1)
        ->and(ExamDay::query()->whereKey($first)->exists())->toBeTrue()
        ->and(ExamDay::query()->whereKey([$second, $third])->exists())->toBeFalse();
});

test('sessions with the same start and end merge into one with the larger quota, and the registrants move to it', function () {
    $first = ($this->oldDay)(($this->rulesetFor)($this->organization, $this->wave), '2027-01-10');
    $second = ($this->oldDay)(($this->rulesetFor)($this->organization, $this->wave), '2027-01-10');
    $survivor = ($this->oldSession)($first, '08:00:00', '10:00:00', quota: 20, label: 'Sesi A');
    $twin = ($this->oldSession)($second, '08:00:00', '10:00:00', quota: 50, label: 'Sesi B');
    $onSurvivor = ($this->pick)($survivor, 2);
    $onTwin = ($this->pick)($twin, 3);

    $this->migration->up();

    expect(DB::table('exam_sessions')->count())->toBe(1)
        ->and(DB::table('exam_sessions')->where('id', $survivor)->value('quota'))->toBe(50)
        ->and(DB::table('exam_sessions')->where('id', $survivor)->value('label'))->toBe('Sesi A')
        ->and(Registrant::query()->where('exam_session_id', $survivor)->pluck('id')->sort()->values()->all())->toBe(collect([...$onSurvivor, ...$onTwin])->sort()->values()->all())
        ->and(Registrant::query()->where('exam_session_id', $twin)->exists())->toBeFalse();
});

test('a session with other times moves over to the day that survives', function () {
    $first = ($this->oldDay)(($this->rulesetFor)($this->organization, $this->wave), '2027-01-10');
    $second = ($this->oldDay)(($this->rulesetFor)($this->organization, $this->wave), '2027-01-10');
    ($this->oldSession)($first, '08:00:00', '10:00:00');
    $afternoon = ($this->oldSession)($second, '13:00:00', '15:00:00');
    ($this->oldSession)($second, '08:00:00', '09:00:00');
    $picked = ($this->pick)($afternoon);

    $this->migration->up();

    $sessions = DB::table('exam_sessions')->orderBy('start_time')->orderBy('end_time')->get();

    expect($sessions)->toHaveCount(3)
        ->and($sessions->pluck('exam_day_id')->unique()->all())->toBe([$first])
        ->and(Registrant::query()->whereKey($picked)->value('exam_session_id'))->toBe($afternoon);
});

test('days of different organizations, waves or dates stay separate', function () {
    $organization = ($this->rulesetFor)($this->organization, $this->wave);
    $days = [
        ($this->oldDay)($organization, '2027-01-10'),
        ($this->oldDay)($organization, '2027-01-11'),
        ($this->oldDay)(($this->rulesetFor)($this->otherOrganization, $this->wave), '2027-01-10'),
        ($this->oldDay)(($this->rulesetFor)($this->organization, null), '2027-01-10'),
        ($this->oldDay)(($this->rulesetFor)(null, null), '2027-01-10'),
    ];
    foreach ($days as $day) {
        ($this->oldSession)($day, '08:00:00', '10:00:00');
    }

    $this->migration->up();

    expect(ExamDay::query()->pluck('id')->sort()->values()->all())->toBe($days)
        ->and(DB::table('exam_sessions')->count())->toBe(5);
});

test('a quota is lifted to the number of registrants who picked the session', function () {
    $day = ($this->oldDay)(($this->rulesetFor)($this->organization, $this->wave), '2027-01-10');
    $crowded = ($this->oldSession)($day, '08:00:00', '10:00:00', quota: 2);
    $roomy = ($this->oldSession)($day, '10:00:00', '12:00:00', quota: 10);
    ($this->pick)($crowded, 4);
    ($this->pick)($roomy, 3);

    $this->migration->up();

    expect(DB::table('exam_sessions')->where('id', $crowded)->value('quota'))->toBe(4)
        ->and(DB::table('exam_sessions')->where('id', $roomy)->value('quota'))->toBe(10);
});

test('merging lifts the quota to everyone who picked, even above the larger quota', function () {
    $first = ($this->oldDay)(($this->rulesetFor)($this->organization, $this->wave), '2027-01-10');
    $second = ($this->oldDay)(($this->rulesetFor)($this->organization, $this->wave), '2027-01-10');
    $survivor = ($this->oldSession)($first, '08:00:00', '10:00:00', quota: 3);
    $twin = ($this->oldSession)($second, '08:00:00', '10:00:00', quota: 3);
    ($this->pick)($survivor, 3);
    ($this->pick)($twin, 3);

    $this->migration->up();

    expect(DB::table('exam_sessions')->where('id', $survivor)->value('quota'))->toBe(6);
});

test('the schedule can be written again after the migration, with the same date for two scopes', function () {
    $day = ($this->oldDay)(($this->rulesetFor)($this->organization, $this->wave), '2027-01-10');
    ($this->oldSession)($day, '08:00:00', '10:00:00');

    $this->migration->up();

    $other = ExamDay::factory()->create(['organization_id' => $this->otherOrganization->id, 'date' => '2027-01-10']);

    expect(ExamDay::query()->whereDate('date', '2027-01-10')->count())->toBe(2)
        ->and($other->exists)->toBeTrue();
});

afterEach(function () {
    Schema::enableForeignKeyConstraints();
    Artisan::call('migrate:fresh');
});
