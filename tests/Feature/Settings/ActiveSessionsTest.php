<?php

use App\Models\User;
use App\Support\PasswordChange;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;

const MAC_CHROME = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0 Safari/537.36';
const IPHONE_SAFARI = 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.0 Mobile/15E148 Safari/604.1';

/**
 * Stores a session row for the user. The password is marked as just confirmed, so the security page opens.
 */
function storedSession(User $user, string $userAgent = MAC_CHROME, string $ip = '10.0.0.1', ?int $lastActivity = null): string
{
    $id = str(fake()->unique()->regexify('[A-Za-z0-9]{40}'))->toString();

    DB::table('sessions')->insert([
        'id' => $id,
        'user_id' => $user->id,
        'ip_address' => $ip,
        'user_agent' => $userAgent,
        'payload' => base64_encode(json_encode(['auth.password_confirmed_at' => time()])),
        'last_activity' => $lastActivity ?? time(),
    ]);

    return $id;
}

/**
 * Makes the next requests arrive with the given session.
 */
function usingSession(string $sessionId): void
{
    config(['session.driver' => 'database']);

    test()->withCookie(config('session.cookie'), $sessionId);
}

test('the security page lists the sessions of the account with this device marked', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    storedSession($other);
    storedSession($user, IPHONE_SAFARI, '10.0.0.2', time() - 3600);
    $current = storedSession($user);
    usingSession($current);

    $this->actingAs($user)
        ->get(route('security.edit'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('settings/security')
            ->has('sessions', 2)
            ->where('sessions.0.is_current', true)
            ->where('sessions.0.browser', 'Chrome')
            ->where('sessions.0.platform', 'macOS')
            ->where('sessions.0.ip_address', '10.0.0.1')
            ->where('sessions.1.is_current', false)
            ->where('sessions.1.browser', 'Safari')
            ->where('sessions.1.platform', 'iPhone')
            ->missing('sessions.0.id')
            ->missing('sessions.1.id'),
        );
});

test('the sessions list is hidden when sessions are not kept in the database', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->withSession(['auth.password_confirmed_at' => time()])
        ->get(route('security.edit'))
        ->assertInertia(fn (Assert $page) => $page->where('sessions', null));
});

test('signing out the other devices needs the current password', function () {
    $user = User::factory()->create();
    $current = storedSession($user);
    $other = storedSession($user, IPHONE_SAFARI);
    usingSession($current);

    $this->actingAs($user)
        ->from(route('security.edit'))
        ->delete(route('sessions.destroy-others'), ['password' => 'wrong-password'])
        ->assertSessionHasErrors('password');

    expect(DB::table('sessions')->where('id', $other)->exists())->toBeTrue();
});

test('signing out the other devices keeps only this one and the accounts of others', function () {
    $user = User::factory()->create(['remember_token' => 'old-token']);
    $stranger = User::factory()->create();
    $strangerSession = storedSession($stranger);
    $current = storedSession($user);
    $other = storedSession($user, IPHONE_SAFARI);
    usingSession($current);

    $this->actingAs($user)
        ->from(route('security.edit'))
        ->delete(route('sessions.destroy-others'), ['password' => 'password'])
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('security.edit'));

    expect(DB::table('sessions')->where('user_id', $user->id)->pluck('id')->all())->toBe([$current])
        ->and(DB::table('sessions')->where('id', $other)->exists())->toBeFalse()
        ->and(DB::table('sessions')->where('id', $strangerSession)->exists())->toBeTrue()
        ->and($user->fresh()->remember_token)->not->toBe('old-token');
});

test('one other device can be signed out', function () {
    $user = User::factory()->create();
    $current = storedSession($user);
    $other = storedSession($user, IPHONE_SAFARI);
    $kept = storedSession($user);
    usingSession($current);

    $handle = collect(
        $this->actingAs($user)->get(route('security.edit'))->viewData('page')['props']['sessions'],
    )->firstWhere('platform', 'iPhone')['handle'];

    $this->from(route('security.edit'))
        ->delete(route('sessions.destroy', $handle))
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('security.edit'));

    expect(DB::table('sessions')->where('id', $other)->exists())->toBeFalse()
        ->and(DB::table('sessions')->where('id', $kept)->exists())->toBeTrue()
        ->and(DB::table('sessions')->where('id', $current)->exists())->toBeTrue();
});

test('a session of another account or an unknown one cannot be signed out', function () {
    $user = User::factory()->create();
    $stranger = User::factory()->create();
    $strangerSession = storedSession($stranger);
    $current = storedSession($user);
    usingSession($current);

    $strangerHandle = hash_hmac('sha256', $strangerSession, (string) config('app.key'));
    $currentHandle = hash_hmac('sha256', $current, (string) config('app.key'));

    $this->actingAs($user)->delete(route('sessions.destroy', $strangerHandle))->assertNotFound();
    $this->actingAs($user)->delete(route('sessions.destroy', 'not-a-handle'))->assertNotFound();
    $this->actingAs($user)->delete(route('sessions.destroy', $currentHandle))->assertNotFound();

    expect(DB::table('sessions')->where('id', $strangerSession)->exists())->toBeTrue();
});

test('signing out one device needs a recently confirmed password', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->delete(route('sessions.destroy', 'whatever'))
        ->assertRedirect(route('password.confirm'));
});

test('changing the password signs out the other devices and clears a forced change', function () {
    $user = User::factory()->create(['remember_token' => 'old-token']);
    $stranger = User::factory()->create();
    $strangerSession = storedSession($stranger);
    $current = storedSession($user);
    $other = storedSession($user, IPHONE_SAFARI);
    PasswordChange::require($user);
    usingSession($current);

    $this->actingAs($user)
        ->from(route('security.edit'))
        ->put(route('user-password.update'), [
            'current_password' => 'password',
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ])
        ->assertSessionHasNoErrors();

    expect(DB::table('sessions')->where('id', $other)->exists())->toBeFalse()
        ->and(DB::table('sessions')->where('id', $current)->exists())->toBeTrue()
        ->and(DB::table('sessions')->where('id', $strangerSession)->exists())->toBeTrue()
        ->and($user->fresh()->remember_token)->not->toBe('old-token')
        ->and(PasswordChange::isRequiredFor($user))->toBeFalse();
});

test('a failed password change keeps the other devices signed in', function () {
    $user = User::factory()->create();
    $current = storedSession($user);
    $other = storedSession($user, IPHONE_SAFARI);
    usingSession($current);

    $this->actingAs($user)
        ->from(route('security.edit'))
        ->put(route('user-password.update'), [
            'current_password' => 'wrong-password',
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ])
        ->assertSessionHasErrors('current_password');

    expect(DB::table('sessions')->where('id', $other)->exists())->toBeTrue();
});

test('resetting a forgotten password signs out every device', function () {
    Notification::fake();
    $user = User::factory()->create(['remember_token' => 'old-token']);
    $session = storedSession($user);
    PasswordChange::require($user);
    config(['session.driver' => 'database']);

    $this->post(route('password.email'), ['email' => $user->email]);

    Notification::assertSentTo($user, ResetPassword::class, function ($notification) use ($user) {
        $this->post(route('password.update'), [
            'token' => $notification->token,
            'email' => $user->email,
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ])->assertSessionHasNoErrors();

        return true;
    });

    expect(DB::table('sessions')->where('id', $session)->exists())->toBeFalse()
        ->and($user->fresh()->remember_token)->not->toBe('old-token')
        ->and(PasswordChange::isRequiredFor($user))->toBeFalse();
});
