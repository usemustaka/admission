<?php

use App\Enums\DefaultRole;
use App\Models\User;

test('a deactivated account cannot log in even with the right password', function () {
    $user = userWithRole(DefaultRole::Admin);
    $user->assignRole(DefaultRole::Inactive->value);

    $this->from(route('login'))
        ->post(route('login.store'), ['email' => $user->email, 'password' => 'password'])
        ->assertSessionHasErrors(['email' => 'Akun ini sudah dinonaktifkan. Hubungi admin.'])
        ->assertRedirect(route('login'));

    $this->assertGuest();
});

test('a deactivated account with a wrong password gets the usual error', function () {
    $user = userWithRole(DefaultRole::Admin);
    $user->assignRole(DefaultRole::Inactive->value);

    $this->from(route('login'))
        ->post(route('login.store'), ['email' => $user->email, 'password' => 'wrong-password'])
        ->assertSessionHasErrors('email')
        ->assertSessionMissing('errors.default', 'Akun ini sudah dinonaktifkan. Hubungi admin.');

    $this->assertGuest();
});

test('a session that outlives the deactivation is signed out', function () {
    $user = userWithRole(DefaultRole::Admin);
    $user->assignRole(DefaultRole::Inactive->value);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertRedirect(route('login'))
        ->assertSessionHasErrors(['email' => 'Akun ini sudah dinonaktifkan. Hubungi admin.']);

    $this->assertGuest();
});

test('an active account is not signed out', function () {
    $this->actingAs(userWithRole(DefaultRole::Admin))
        ->get(route('profile.edit'))
        ->assertOk();

    $this->assertAuthenticated();
});

test('registration and password reset requests are rate limited', function () {
    foreach (range(1, 6) as $attempt) {
        $this->post(route('register.store'), []);
        $this->post(route('password.email'), ['email' => User::factory()->make()->email]);
    }

    $this->post(route('register.store'), [])->assertTooManyRequests();
    $this->post(route('password.email'), ['email' => 'someone@example.com'])->assertTooManyRequests();
});
