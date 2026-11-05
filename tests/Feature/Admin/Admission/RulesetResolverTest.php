<?php

use App\Actions\Admission\ResolveRuleset;
use App\Enums\Gender;
use App\Models\Organization;
use App\Models\Ruleset;
use App\Models\Wave;

beforeEach(function () {
    $this->wave = Wave::factory()->create();
    $this->smp = Organization::factory()->create();
    $this->resolve = fn (?Gender $gender = Gender::Male, ?Wave $wave = null, ?Organization $organization = null) => app(ResolveRuleset::class)
        ->handle(($wave ?? $this->wave)->id, ($organization ?? $this->smp)->id, $gender);
});

test('a ruleset with no conditions applies to everyone', function () {
    $general = Ruleset::factory()->create();

    expect(($this->resolve)()->is($general))->toBeTrue();
});

test('the highest priority ruleset wins when several match', function () {
    $second = Ruleset::factory()->create(['priority' => 2]);
    $first = Ruleset::factory()->create(['priority' => 1, 'organization_id' => $this->smp->id]);

    expect(($this->resolve)()->is($first))->toBeTrue();

    $first->update(['priority' => 3]);

    expect(($this->resolve)()->is($second))->toBeTrue();
});

test('conditions that do not match are skipped', function () {
    $general = Ruleset::factory()->create(['priority' => 9]);
    Ruleset::factory()->create(['priority' => 1, 'organization_id' => Organization::factory()->create()->id]);
    Ruleset::factory()->create(['priority' => 2, 'gender' => Gender::Female]);
    Ruleset::factory()->create(['priority' => 3, 'wave_id' => Wave::factory()->create()->id]);

    expect(($this->resolve)(Gender::Male)->is($general))->toBeTrue();
});

test('all conditions have to match together', function () {
    $boysSmp = Ruleset::factory()->create(['priority' => 1, 'organization_id' => $this->smp->id, 'gender' => Gender::Male, 'wave_id' => $this->wave->id]);

    expect(($this->resolve)(Gender::Male)->is($boysSmp))->toBeTrue()
        ->and(($this->resolve)(Gender::Female))->toBeNull()
        ->and(($this->resolve)(Gender::Male, organization: Organization::factory()->create()))->toBeNull();
});

test('inactive rulesets are ignored', function () {
    Ruleset::factory()->create(['priority' => 1, 'is_active' => false]);
    $active = Ruleset::factory()->create(['priority' => 2]);

    expect(($this->resolve)()->is($active))->toBeTrue();
});

test('an applicant without a known gender only matches rulesets that do not care about gender', function () {
    Ruleset::factory()->create(['priority' => 1, 'gender' => Gender::Male]);

    expect(($this->resolve)(null))->toBeNull();

    $general = Ruleset::factory()->create(['priority' => 2]);

    expect(($this->resolve)(null)->is($general))->toBeTrue();
});

test('nothing is returned when no ruleset matches', function () {
    expect(($this->resolve)())->toBeNull();
});
