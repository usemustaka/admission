<?php

use App\Enums\Ability;
use App\Enums\DefaultRole;
use App\Models\Organization;
use App\Models\Period;
use App\Models\Person;
use App\Models\Setting;
use App\Models\User;
use App\Models\Year;
use Database\Seeders\SystemSeeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

test('registration creates a person linked to the account with the user role', function () {
    $this->post(route('register.store'), [
        'name' => 'Siti Aminah',
        'email' => 'siti@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $user = User::where('email', 'siti@example.com')->firstOrFail();

    expect($user->person)->toBeInstanceOf(Person::class)
        ->and($user->name)->toBe('Siti Aminah')
        ->and($user->hasRole(DefaultRole::User->value))->toBeTrue()
        ->and($user->can(Ability::AdminAccess->value))->toBeFalse();
});

test('updating the profile changes the name of the person', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->patch(route('profile.update'), ['name' => 'Nama Baru', 'email' => $user->email]);

    expect($user->refresh()->name)->toBe('Nama Baru');
});

test('super admins pass every permission check', function () {
    $user = userWithRole(DefaultRole::SuperAdmin);

    expect($user->can(Ability::NoteManage->value))->toBeTrue();
});

test('operators get everyday permissions but not note management', function () {
    $user = userWithRole(DefaultRole::Operator);

    expect($user->can(Ability::AdminAccess->value))->toBeTrue()
        ->and($user->can(Ability::PersonUpdate->value))->toBeTrue()
        ->and($user->can(Ability::NoteManage->value))->toBeFalse();
});

test('the system seeder can run again without duplicating anything', function () {
    $this->seed(SystemSeeder::class);
    $counts = [Permission::count(), Role::count(), Organization::count(), Year::count(), Period::count(), Setting::count()];

    $this->seed(SystemSeeder::class);

    expect([Permission::count(), Role::count(), Organization::count(), Year::count(), Period::count(), Setting::count()])->toBe($counts)
        ->and($counts[0])->toBe(count(Ability::cases()));
});

test('the admin command creates a verified account with the chosen role', function () {
    $this->artisan('app:create-admin', ['email' => 'admin@example.com', 'name' => 'Admin Utama', '--role' => 'admin'])
        ->expectsQuestion('Kata sandi', 'rahasia-banget')
        ->assertSuccessful();

    $user = User::where('email', 'admin@example.com')->firstOrFail();

    expect($user->hasRole('admin'))->toBeTrue()
        ->and($user->email_verified_at)->not->toBeNull()
        ->and($user->name)->toBe('Admin Utama');
});

test('the admin command refuses the plain user role', function () {
    $this->artisan('app:create-admin', ['email' => 'x@example.com', 'name' => 'X', '--role' => 'user'])
        ->assertFailed();

    expect(User::where('email', 'x@example.com')->exists())->toBeFalse();
});
