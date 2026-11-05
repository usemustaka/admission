<?php

use App\Enums\DefaultRole;
use App\Models\Organization;
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
