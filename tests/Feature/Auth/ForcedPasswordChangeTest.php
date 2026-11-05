<?php

use App\Models\User;
use App\Support\PasswordChange;
use Inertia\Testing\AssertableInertia as Assert;

test('a flagged account is sent to the security page from anywhere else', function (string $route) {
    $user = User::factory()->create();
    PasswordChange::require($user);

    $this->actingAs($user)->get(route($route))->assertRedirect(route('security.edit'));
})->with(['dashboard', 'portal', 'profile.edit', 'appearance.edit']);

test('a flagged account keeps what it needs to change the password', function () {
    $user = User::factory()->create();
    PasswordChange::require($user);

    $this->actingAs($user)
        ->withSession(['auth.password_confirmed_at' => time()])
        ->get(route('security.edit'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->where('passwordChangeRequired', true));

    $this->actingAs($user)->get(route('password.confirm'))->assertOk();

    $this->actingAs($user)
        ->from(route('security.edit'))
        ->put(route('user-password.update'), [
            'current_password' => 'password',
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('security.edit'));

    $this->actingAs($user)->post(route('logout'))->assertRedirect(route('home'));
});

test('an unverified flagged account can still reach the security page', function () {
    $user = User::factory()->unverified()->create();
    PasswordChange::require($user);

    $this->actingAs($user)
        ->withSession(['auth.password_confirmed_at' => time()])
        ->get(route('security.edit'))
        ->assertOk();
});

test('after the password is changed the account moves freely again', function () {
    $user = User::factory()->create();
    PasswordChange::require($user);

    $this->actingAs($user)->put(route('user-password.update'), [
        'current_password' => 'password',
        'password' => 'new-password',
        'password_confirmation' => 'new-password',
    ]);

    $this->actingAs($user)->get(route('dashboard'))->assertRedirect(route('portal'));
});

test('an account that was not flagged is left alone', function () {
    $user = User::factory()->create();
    PasswordChange::require(User::factory()->create());

    $this->actingAs($user)->get(route('profile.edit'))->assertOk();
});
