<?php

use App\Enums\DefaultRole;
use App\Models\User;
use App\Models\Wave;

test('only staff who may view waves can open the list', function () {
    $this->actingAs(User::factory()->create())->get(route('admin.waves.index'))->assertForbidden();

    $this->actingAs(userWithRole(DefaultRole::Operator))->get(route('admin.waves.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('admin/waves/index')->where('can.manage', false));
});

test('admins can create and update a wave, and open or close it', function () {
    $this->actingAs(userWithRole(DefaultRole::Admin));

    $this->post(route('admin.waves.store'), ['name' => 'Gelombang 1', 'is_open' => true])->assertRedirect(route('admin.waves.index'));

    $wave = Wave::firstOrFail();
    expect($wave->is_open)->toBeTrue();

    $this->put(route('admin.waves.update', $wave), ['name' => 'Gelombang 1 (revisi)', 'is_open' => false])->assertRedirect();

    expect($wave->refresh()->name)->toBe('Gelombang 1 (revisi)')->and($wave->is_open)->toBeFalse();
});

test('operators can look at waves but not change them', function () {
    $this->actingAs(userWithRole(DefaultRole::Operator));
    $wave = Wave::factory()->create();

    $this->post(route('admin.waves.store'), ['name' => 'Baru'])->assertForbidden();
    $this->put(route('admin.waves.update', $wave), ['name' => 'Ubah'])->assertForbidden();
});

test('the end date cannot be before the start date', function () {
    $this->actingAs(userWithRole(DefaultRole::Admin));

    $this->post(route('admin.waves.store'), ['name' => 'Gelombang', 'starts_on' => '2026-12-01', 'ends_on' => '2026-11-01'])
        ->assertSessionHasErrors('ends_on');
});

test('a wave only accepts applicants while it is switched on and inside its dates', function () {
    $open = Wave::factory()->create();
    Wave::factory()->create(['is_open' => false]);
    Wave::factory()->create(['starts_on' => now()->addWeek()]);
    Wave::factory()->create(['ends_on' => now()->subWeek()]);
    $windowed = Wave::factory()->create(['starts_on' => now()->subWeek(), 'ends_on' => now()->addWeek()]);

    expect(Wave::open()->pluck('id')->all())->toEqualCanonicalizing([$open->id, $windowed->id]);
});
