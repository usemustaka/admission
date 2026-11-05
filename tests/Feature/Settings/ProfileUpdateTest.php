<?php

use App\Enums\DefaultRole;
use App\Models\Note;
use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Facades\Notification;

test('profile page is displayed', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->get(route('profile.edit'));

    $response->assertOk();
});

test('profile information can be updated', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->patch(route('profile.update'), [
            'name' => 'Test User',
            'email' => $user->email,
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('profile.edit'));

    expect($user->refresh()->name)->toBe('Test User');
});

test('changing the email needs the current password', function () {
    Notification::fake();
    $user = User::factory()->create();

    $this->actingAs($user)
        ->from(route('profile.edit'))
        ->patch(route('profile.update'), ['name' => 'Test User', 'email' => 'new@example.com'])
        ->assertSessionHasErrors('current_password');

    $this->actingAs($user)
        ->from(route('profile.edit'))
        ->patch(route('profile.update'), ['name' => 'Test User', 'email' => 'new@example.com', 'current_password' => 'wrong-password'])
        ->assertSessionHasErrors('current_password');

    expect($user->fresh()->email)->not->toBe('new@example.com');
    Notification::assertNothingSent();
});

test('a changed email is verified again and also updates the person', function () {
    Notification::fake();
    $user = User::factory()->create();

    $this->actingAs($user)
        ->patch(route('profile.update'), ['name' => 'Test User', 'email' => 'new@example.com', 'current_password' => 'password'])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('profile.edit'));

    $user->refresh();

    expect($user->email)->toBe('new@example.com')
        ->and($user->email_verified_at)->toBeNull()
        ->and($user->person->name)->toBe('Test User')
        ->and($user->person->email)->toBe('new@example.com');
    Notification::assertSentTo($user, VerifyEmail::class);
});

test('an unverified account can still reach the profile page and resend the verification mail', function () {
    Notification::fake();
    $user = User::factory()->unverified()->create();

    $this->actingAs($user)->get(route('profile.edit'))->assertOk();
    $this->actingAs($user)->post(route('verification.send'))->assertRedirect();

    Notification::assertSentTo($user, VerifyEmail::class);
});

test('email verification status is unchanged when the email address is unchanged', function () {
    Notification::fake();
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->patch(route('profile.update'), [
            'name' => 'Test User',
            'email' => $user->email,
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('profile.edit'));

    expect($user->refresh()->email_verified_at)->not->toBeNull();
    Notification::assertNothingSent();
});

test('staff can deactivate their own account and keep what they wrote', function () {
    $user = userWithRole(DefaultRole::Admin);
    $note = Note::factory()->create(['author_id' => $user->id]);

    $response = $this
        ->actingAs($user)
        ->delete(route('profile.destroy'), [
            'password' => 'password',
        ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('home'));

    $this->assertGuest();
    expect($user->fresh()->hasRole(DefaultRole::Inactive->value))->toBeTrue()
        ->and($note->fresh())->not->toBeNull();
});

test('the last active super admin cannot deactivate themselves', function () {
    $user = userWithRole(DefaultRole::SuperAdmin);

    $this->actingAs($user)
        ->from(route('profile.edit'))
        ->delete(route('profile.destroy'), ['password' => 'password'])
        ->assertSessionHasErrors('user')
        ->assertRedirect(route('profile.edit'));

    $this->assertAuthenticated();
    expect($user->fresh()->hasRole(DefaultRole::Inactive->value))->toBeFalse();
});

test('correct password must be provided to deactivate the account', function () {
    $user = userWithRole(DefaultRole::Admin);

    $response = $this
        ->actingAs($user)
        ->from(route('profile.edit'))
        ->delete(route('profile.destroy'), [
            'password' => 'wrong-password',
        ]);

    $response
        ->assertSessionHasErrors('password')
        ->assertRedirect(route('profile.edit'));

    expect($user->fresh()->hasRole(DefaultRole::Inactive->value))->toBeFalse();
});

test('a parent cannot deactivate their account', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->delete(route('profile.destroy'), ['password' => 'password'])
        ->assertForbidden();

    expect($user->fresh()->hasRole(DefaultRole::Inactive->value))->toBeFalse();
});

test('the profile page tells whether the account can be deactivated', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('profile.edit'))
        ->assertInertia(fn ($page) => $page->where('canDeactivateAccount', false));

    $this->actingAs(userWithRole(DefaultRole::Admin))
        ->get(route('profile.edit'))
        ->assertInertia(fn ($page) => $page->where('canDeactivateAccount', true));
});
