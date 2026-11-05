<?php

use App\Enums\DefaultRole;
use App\Models\User;

test('guests are redirected to the login page', function () {
    $response = $this->get(route('dashboard'));
    $response->assertRedirect(route('login'));
});

test('staff see the admin dashboard', function () {
    $this->actingAs(userWithRole(DefaultRole::Admin));

    $this->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('admin/dashboard')->has('attention')->missing('stats'));
});

test('users without staff access are sent to the parent portal', function () {
    $this->actingAs(User::factory()->create());

    $this->get(route('dashboard'))->assertRedirect(route('portal'));
});

test('the parent portal is open to any signed-in user', function () {
    $this->actingAs(User::factory()->create());

    $this->get(route('portal'))->assertOk()->assertInertia(fn ($page) => $page->component('portal/home'));
});

test('signed-in users receive their abilities', function () {
    $this->actingAs(userWithRole(DefaultRole::Operator));

    $this->get(route('dashboard'))->assertInertia(fn ($page) => $page
        ->where('auth.abilities', fn ($abilities) => collect($abilities)->contains('core::person.view')
            && ! collect($abilities)->contains('core::note.manage')));
});
