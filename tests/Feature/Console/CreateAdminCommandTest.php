<?php

use App\Enums\DefaultRole;
use App\Models\Person;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;

test('the command creates a verified admin with the given password and role', function () {
    Notification::fake();

    $this->artisan('app:create-admin', ['email' => 'Kepala@Example.com', 'name' => 'Kepala Sekolah', '--role' => 'admin', '--password' => 'rahasia-1234'])
        ->expectsOutputToContain('kepala@example.com sudah dibuat dengan peran admin')
        ->assertSuccessful();

    $user = User::where('email', 'kepala@example.com')->firstOrFail();

    expect($user->name)->toBe('Kepala Sekolah')
        ->and($user->email_verified_at)->not->toBeNull()
        ->and($user->getRoleNames()->all())->toBe(['admin'])
        ->and(Hash::check('rahasia-1234', $user->password))->toBeTrue();

    Notification::assertNothingSent();
});

test('the command defaults to the super admin role', function () {
    $this->artisan('app:create-admin', ['email' => 'owner@example.com', 'name' => 'Owner', '--password' => 'rahasia-1234'])->assertSuccessful();

    expect(User::where('email', 'owner@example.com')->firstOrFail()->hasRole(DefaultRole::SuperAdmin->value))->toBeTrue();
});

test('a weak password is refused and nothing is created', function () {
    $this->artisan('app:create-admin', ['email' => 'lemah@example.com', 'name' => 'Lemah', '--password' => '123'])->assertFailed();

    expect(User::where('email', 'lemah@example.com')->exists())->toBeFalse()
        ->and(Person::where('email', 'lemah@example.com')->exists())->toBeFalse();
});

test('without a password the command asks for it twice when it can prompt', function () {
    $this->artisan('app:create-admin', ['email' => 'prompt@example.com', 'name' => 'Prompt'])
        ->expectsQuestion('Kata sandi', 'rahasia-1234')
        ->expectsQuestion('Ulangi kata sandi', 'rahasia-1234')
        ->assertSuccessful();

    expect(Hash::check('rahasia-1234', User::where('email', 'prompt@example.com')->firstOrFail()->password))->toBeTrue();
});

test('without a password and without a prompt the command explains what to do', function () {
    $this->artisan('app:create-admin', ['email' => 'deploy@example.com', 'name' => 'Deploy', '--no-interaction' => true])
        ->expectsOutputToContain('--password')
        ->assertFailed();

    expect(User::where('email', 'deploy@example.com')->exists())->toBeFalse();
});

test('the command reuses a person with the same email', function () {
    $person = Person::factory()->create(['email' => 'ada@example.com']);
    $people = Person::count();

    $this->artisan('app:create-admin', ['email' => 'ada@example.com', 'name' => 'Ada', '--password' => 'rahasia-1234'])->assertSuccessful();

    expect(Person::count())->toBe($people)
        ->and(User::where('email', 'ada@example.com')->firstOrFail()->person_id)->toBe($person->id);
});

test('the command refuses a taken email, an unknown role, and roles that are not for staff', function () {
    User::factory()->create(['email' => 'ada@example.com']);
    $users = User::count();

    $this->artisan('app:create-admin', ['email' => 'ada@example.com', 'name' => 'Ada', '--password' => 'rahasia-1234'])->assertFailed();

    foreach (['tidak-ada', 'user', 'nonaktif'] as $role) {
        $this->artisan('app:create-admin', ['email' => 'baru@example.com', 'name' => 'Baru', '--role' => $role, '--password' => 'rahasia-1234'])->assertFailed();
    }

    expect(User::count())->toBe($users);
});

test('the command can run again without breaking the roles that already exist', function () {
    $this->artisan('app:create-admin', ['email' => 'satu@example.com', 'name' => 'Satu', '--password' => 'rahasia-1234'])->assertSuccessful();
    $this->artisan('app:create-admin', ['email' => 'dua@example.com', 'name' => 'Dua', '--role' => 'operator', '--password' => 'rahasia-1234'])->assertSuccessful();

    expect(User::role('operator')->count())->toBe(1)->and(User::role('super-admin')->count())->toBe(1);
});
