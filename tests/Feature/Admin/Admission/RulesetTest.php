<?php

use App\Enums\DefaultRole;
use App\Models\ExamDay;
use App\Models\ExamSession;
use App\Models\Organization;
use App\Models\Registrant;
use App\Models\Ruleset;
use App\Models\User;
use App\Models\Wave;

test('only staff who may view rulesets can open them', function () {
    $this->actingAs(User::factory()->create())->get(route('admin.rulesets.index'))->assertForbidden();

    $this->actingAs(userWithRole(DefaultRole::Operator))->get(route('admin.rulesets.index'))->assertOk();
});

test('admins can create a ruleset with conditions and it goes last in the priority order', function () {
    $this->actingAs(userWithRole(DefaultRole::Admin));
    Ruleset::factory()->create(['priority' => 5]);
    $wave = Wave::factory()->create();
    $organization = Organization::factory()->create();

    $response = $this->post(route('admin.rulesets.store'), [
        'name' => 'SMP putra',
        'wave_id' => $wave->id,
        'organization_id' => $organization->id,
        'gender' => 'male',
        'is_active' => true,
    ]);

    $ruleset = Ruleset::where('name', 'SMP putra')->firstOrFail();

    $response->assertRedirect(route('admin.rulesets.show', $ruleset));
    expect($ruleset->priority)->toBe(6)
        ->and($ruleset->wave_id)->toBe($wave->id)
        ->and($ruleset->organization_id)->toBe($organization->id)
        ->and($ruleset->gender->value)->toBe('male');
});

test('empty conditions are saved as "any"', function () {
    $this->actingAs(userWithRole(DefaultRole::Admin));

    $this->post(route('admin.rulesets.store'), ['name' => 'Umum', 'wave_id' => '', 'organization_id' => '', 'gender' => '', 'is_active' => true]);

    $ruleset = Ruleset::firstOrFail();

    expect($ruleset->wave_id)->toBeNull()->and($ruleset->organization_id)->toBeNull()->and($ruleset->gender)->toBeNull();
});

test('conditions must point at existing records', function () {
    $this->actingAs(userWithRole(DefaultRole::Admin));

    $this->post(route('admin.rulesets.store'), ['name' => 'X', 'wave_id' => 999, 'gender' => 'other'])
        ->assertSessionHasErrors(['wave_id', 'gender']);
});

test('conditions can be updated and the ruleset deleted', function () {
    $this->actingAs(userWithRole(DefaultRole::Admin));
    $ruleset = Ruleset::factory()->create(['gender' => 'male']);

    $this->put(route('admin.rulesets.update', $ruleset), ['name' => 'Baru', 'gender' => '', 'is_active' => false])->assertRedirect();

    expect($ruleset->refresh()->name)->toBe('Baru')->and($ruleset->gender)->toBeNull()->and($ruleset->is_active)->toBeFalse();

    $this->delete(route('admin.rulesets.destroy', $ruleset))->assertRedirect(route('admin.rulesets.index'));
    expect(Ruleset::count())->toBe(0);
});

test('operators cannot change rulesets', function () {
    $this->actingAs(userWithRole(DefaultRole::Operator));
    $ruleset = Ruleset::factory()->create();

    $this->post(route('admin.rulesets.store'), ['name' => 'X'])->assertForbidden();
    $this->put(route('admin.rulesets.update', $ruleset), ['name' => 'X'])->assertForbidden();
    $this->patch(route('admin.rulesets.move', $ruleset), ['direction' => 'up'])->assertForbidden();
    $this->delete(route('admin.rulesets.destroy', $ruleset))->assertForbidden();
});

describe('priority order', function () {
    beforeEach(function () {
        $this->actingAs(userWithRole(DefaultRole::Admin));
        $this->a = Ruleset::factory()->create(['priority' => 10]);
        $this->b = Ruleset::factory()->create(['priority' => 20]);
        $this->c = Ruleset::factory()->create(['priority' => 30]);
        $this->order = fn () => Ruleset::query()->byPriority()->pluck('id')->all();
    });

    test('a ruleset can move up and down', function () {
        $this->patch(route('admin.rulesets.move', $this->c), ['direction' => 'up']);
        expect(($this->order)())->toBe([$this->a->id, $this->c->id, $this->b->id]);

        $this->patch(route('admin.rulesets.move', $this->a), ['direction' => 'down']);
        expect(($this->order)())->toBe([$this->c->id, $this->a->id, $this->b->id]);
    });

    test('the first cannot go further up and the last cannot go further down', function () {
        $this->patch(route('admin.rulesets.move', $this->a), ['direction' => 'up']);
        $this->patch(route('admin.rulesets.move', $this->c), ['direction' => 'down']);

        expect(($this->order)())->toBe([$this->a->id, $this->b->id, $this->c->id]);
    });

    test('only up or down is accepted', function () {
        $this->patch(route('admin.rulesets.move', $this->a), ['direction' => 'sideways'])->assertSessionHasErrors('direction');
    });
});

test('the matching test on the list shows which ruleset would apply', function () {
    $this->actingAs(userWithRole(DefaultRole::Operator));
    $organization = Organization::factory()->create();
    Ruleset::factory()->create(['priority' => 1, 'organization_id' => $organization->id, 'name' => 'Khusus lembaga']);

    $this->get(route('admin.rulesets.index', ['uji' => 1, 'lembaga' => $organization->id, 'gender' => 'male']))
        ->assertInertia(fn ($page) => $page->where('test.ran', true)->where('test.match.name', 'Khusus lembaga'));

    $this->get(route('admin.rulesets.index', ['uji' => 1, 'lembaga' => 999]))
        ->assertInertia(fn ($page) => $page->where('test.ran', true)->where('test.match', null));
});

test('the ruleset list and detail show how many registrations were made under each ruleset', function () {
    $this->actingAs(userWithRole(DefaultRole::Operator));
    $busy = Ruleset::factory()->create(['name' => 'Ramai', 'priority' => 1]);
    $quiet = Ruleset::factory()->create(['name' => 'Sepi', 'priority' => 2]);
    Registrant::factory()->count(2)->create(['ruleset_id' => $busy->id]);

    $this->get(route('admin.rulesets.index'))
        ->assertInertia(fn ($page) => $page->where('rulesets.0.registrants_count', 2)->where('rulesets.1.registrants_count', 0));

    $this->get(route('admin.rulesets.show', $busy))
        ->assertInertia(fn ($page) => $page->where('ruleset.registrants_count', 2));

    expect($quiet->registrants()->count())->toBe(0);
});

describe('shadowed rulesets', function () {
    beforeEach(function () {
        $this->actingAs(userWithRole(DefaultRole::Operator));
    });

    test('a specific ruleset placed after a catch-all is flagged as never used', function () {
        $catchAll = Ruleset::factory()->create(['priority' => 1, 'name' => 'Umum']);
        $organization = Organization::factory()->create();
        Ruleset::factory()->create(['priority' => 2, 'name' => 'SMP putra', 'organization_id' => $organization->id, 'gender' => 'male']);

        $this->get(route('admin.rulesets.index'))->assertInertia(fn ($page) => $page
            ->where('rulesets.0.shadowed_by', null)
            ->where('rulesets.1.shadowed_by', ['id' => $catchAll->id, 'name' => 'Umum']));
    });

    test('a specific ruleset above the catch-all is not flagged, and neither is the catch-all', function () {
        $organization = Organization::factory()->create();
        Ruleset::factory()->create(['priority' => 1, 'organization_id' => $organization->id, 'gender' => 'male']);
        Ruleset::factory()->create(['priority' => 2]);

        $this->get(route('admin.rulesets.index'))->assertInertia(fn ($page) => $page
            ->where('rulesets.0.shadowed_by', null)
            ->where('rulesets.1.shadowed_by', null));
    });

    test('a ruleset that only partly overlaps an earlier one is not flagged', function () {
        $organization = Organization::factory()->create();
        Ruleset::factory()->create(['priority' => 1, 'organization_id' => $organization->id]);
        Ruleset::factory()->create(['priority' => 2, 'gender' => 'male']);

        $this->get(route('admin.rulesets.index'))->assertInertia(fn ($page) => $page
            ->where('rulesets.1.shadowed_by', null));
    });

    test('inactive rulesets neither shadow nor get flagged', function () {
        $organization = Organization::factory()->create();
        Ruleset::factory()->create(['priority' => 1, 'is_active' => false]);
        Ruleset::factory()->create(['priority' => 2, 'organization_id' => $organization->id]);
        Ruleset::factory()->create(['priority' => 3, 'organization_id' => $organization->id, 'is_active' => false]);

        $this->get(route('admin.rulesets.index'))->assertInertia(fn ($page) => $page
            ->where('rulesets.1.shadowed_by', null)
            ->where('rulesets.2.shadowed_by', null));
    });

    test('the detail page flags a shadowed ruleset too', function () {
        $catchAll = Ruleset::factory()->create(['priority' => 1, 'name' => 'Umum']);
        $shadowed = Ruleset::factory()->create(['priority' => 2, 'gender' => 'female']);

        $this->get(route('admin.rulesets.show', $shadowed))
            ->assertInertia(fn ($page) => $page->where('ruleset.shadowed_by.id', $catchAll->id));
    });

    test('the matching test lists every matching ruleset in order and marks the winner', function () {
        $first = Ruleset::factory()->create(['priority' => 1, 'name' => 'Umum']);
        $second = Ruleset::factory()->create(['priority' => 2, 'name' => 'Putra', 'gender' => 'male']);
        Ruleset::factory()->create(['priority' => 3, 'name' => 'Putri', 'gender' => 'female']);

        $this->get(route('admin.rulesets.index', ['uji' => 1, 'gender' => 'male']))
            ->assertInertia(fn ($page) => $page
                ->where('test.matches', [
                    ['id' => $first->id, 'name' => 'Umum', 'is_winner' => true],
                    ['id' => $second->id, 'name' => 'Putra', 'is_winner' => false],
                ]));
    });
});

describe('deleting a ruleset', function () {
    beforeEach(function () {
        $this->actingAs(userWithRole(DefaultRole::Admin));
        $this->ruleset = Ruleset::factory()->create();
    });

    test('a ruleset that registrants were made under cannot be deleted', function () {
        $registrant = Registrant::factory()->create(['ruleset_id' => $this->ruleset->id]);

        $this->delete(route('admin.rulesets.destroy', $this->ruleset))
            ->assertRedirect()
            ->assertInertiaFlash('toast.type', 'error');

        expect(Ruleset::find($this->ruleset->id))->not->toBeNull()
            ->and($registrant->refresh()->ruleset_id)->toBe($this->ruleset->id);
    });

    test('a ruleset nobody registered under can be deleted', function () {
        Registrant::factory()->create();

        $this->delete(route('admin.rulesets.destroy', $this->ruleset))->assertRedirect(route('admin.rulesets.index'));

        expect(Ruleset::find($this->ruleset->id))->toBeNull();
    });

    test('the exam sessions of its lembaga do not keep a ruleset from being deleted', function () {
        $organization = Organization::factory()->create();
        $ruleset = Ruleset::factory()->create(['organization_id' => $organization->id]);
        $session = ExamSession::factory()->create(['exam_day_id' => ExamDay::factory()->create(['organization_id' => $organization->id])->id]);
        Registrant::factory()->create(['organization_id' => $organization->id, 'exam_session_id' => $session->id]);

        $this->delete(route('admin.rulesets.destroy', $ruleset))->assertRedirect(route('admin.rulesets.index'));

        expect(Ruleset::find($ruleset->id))->toBeNull()
            ->and(ExamSession::find($session->id))->not->toBeNull();
    });
});
