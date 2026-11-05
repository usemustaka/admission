<?php

use App\Enums\Ability;
use App\Enums\DefaultRole;
use App\Models\Person;
use App\Models\User;
use App\Support\PasswordChange;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Spatie\Permission\Models\Role;

/**
 * Staff with the given extra abilities on top of admin access, without any role.
 *
 * @param  list<Ability>  $abilities
 */
function staffWith(array $abilities, ?DefaultRole $role = null): User
{
    test()->seed(RolesAndPermissionsSeeder::class);

    $user = User::factory()->create();
    $user->givePermissionTo([Ability::AdminAccess->value, ...array_map(fn (Ability $ability): string => $ability->value, $abilities)]);

    if ($role !== null) {
        $user->assignRole($role->value);
    }

    return $user;
}

beforeEach(fn () => $this->withoutVite());

test('staff screens are closed to guests, parents, and staff without the user permission', function () {
    $this->get(route('admin.users.index'))->assertRedirect(route('login'));

    $target = userWithRole(DefaultRole::Operator);
    $parent = User::factory()->create();

    foreach ([$parent, userWithRole(DefaultRole::Operator)] as $outsider) {
        $this->actingAs($outsider);

        $this->get(route('admin.users.index'))->assertForbidden();
        $this->get(route('admin.users.create'))->assertForbidden();
        $this->get(route('admin.users.show', $target))->assertForbidden();
        $this->post(route('admin.users.store'), ['name' => 'Baru', 'email' => 'baru@example.com', 'role' => 'operator'])->assertForbidden();
        $this->put(route('admin.users.update', $target), ['roles' => ['admin']])->assertForbidden();
        $this->post(route('admin.users.deactivate', $target))->assertForbidden();
        $this->post(route('admin.users.reactivate', $target))->assertForbidden();
        $this->post(route('admin.users.password-change', $target))->assertForbidden();
        $this->post(route('admin.users.setup-link', $target))->assertForbidden();
    }

    expect($target->refresh()->hasRole(DefaultRole::Inactive->value))->toBeFalse();
});

test('the staff list shows staff only, with search and filters', function () {
    $admin = userWithRole(DefaultRole::Admin);
    $operator = userWithRole(DefaultRole::Operator);
    $operator->person->update(['name' => 'Budi Operator']);
    $inactive = userWithRole(DefaultRole::Operator);
    $inactive->assignRole(DefaultRole::Inactive->value);
    User::factory()->create()->assignRole(DefaultRole::User->value);

    $this->actingAs($admin)->get(route('admin.users.index'))
        ->assertInertia(fn ($page) => $page->has('users.data', 3)->where('counts.status.aktif', 2)->where('counts.status.nonaktif', 1));

    $this->get(route('admin.users.index', ['cari' => 'budi']))
        ->assertInertia(fn ($page) => $page->has('users.data', 1)->where('users.data.0.id', $operator->id)->where('users.data.0.is_active', true));

    $this->get(route('admin.users.index', ['status' => 'nonaktif']))
        ->assertInertia(fn ($page) => $page->has('users.data', 1)->where('users.data.0.id', $inactive->id)->where('users.data.0.roles', ['operator']));

    $this->get(route('admin.users.index', ['peran' => 'admin']))
        ->assertInertia(fn ($page) => $page->has('users.data', 1)->where('users.data.0.id', $admin->id));
});

test('a parent account cannot be opened as staff', function () {
    $admin = userWithRole(DefaultRole::Admin);
    $parent = User::factory()->create()->assignRole(DefaultRole::User->value);

    $this->actingAs($admin)->get(route('admin.users.show', $parent))->assertNotFound();
});

test('creating staff makes the person and login, and mails a link to choose a password', function () {
    Notification::fake();
    $this->actingAs(userWithRole(DefaultRole::Admin));

    $this->post(route('admin.users.store'), ['name' => 'Sari Operator', 'email' => 'Sari@Example.com', 'role' => 'operator'])
        ->assertRedirect();

    $user = User::where('email', 'sari@example.com')->firstOrFail();

    expect($user->name)->toBe('Sari Operator')
        ->and($user->person->email)->toBe('sari@example.com')
        ->and($user->email_verified_at)->not->toBeNull()
        ->and($user->hasRole(DefaultRole::Operator->value))->toBeTrue()
        ->and($user->getRoleNames()->all())->toBe(['operator']);

    Notification::assertSentTo($user, ResetPassword::class);
});

test('creating staff reuses a person with the same email instead of duplicating them', function () {
    Notification::fake();
    $this->actingAs(userWithRole(DefaultRole::Admin));
    $person = Person::factory()->create(['name' => 'Rina Lama', 'email' => 'rina@example.com']);
    $people = Person::count();

    $this->post(route('admin.users.store'), ['name' => 'Rina Baru', 'email' => 'rina@example.com', 'role' => 'operator']);

    expect(Person::count())->toBe($people)
        ->and(User::where('email', 'rina@example.com')->firstOrFail()->person_id)->toBe($person->id)
        ->and($person->refresh()->name)->toBe('Rina Lama');
});

test('creating staff rejects an email that is already an account', function () {
    Notification::fake();
    $this->actingAs(userWithRole(DefaultRole::Admin));
    $existing = User::factory()->create();
    $users = User::count();

    $this->post(route('admin.users.store'), ['name' => 'Ganda', 'email' => strtoupper($existing->email), 'role' => 'operator'])
        ->assertSessionHasErrors('email');

    expect(User::count())->toBe($users);
    Notification::assertNothingSent();
});

test('creating staff rejects a person who already has a login under another email', function () {
    $this->actingAs(userWithRole(DefaultRole::Admin));
    $person = Person::factory()->create(['email' => 'pribadi@example.com']);
    User::factory()->create(['person_id' => $person->id, 'email' => 'kantor@example.com']);

    $this->post(route('admin.users.store'), ['name' => 'Ganda', 'email' => 'pribadi@example.com', 'role' => 'operator'])
        ->assertSessionHasErrors('email');

    expect(User::where('email', 'pribadi@example.com')->exists())->toBeFalse();
});

test('only super admins can give the super admin role, and app-only roles are refused', function () {
    $this->actingAs(userWithRole(DefaultRole::Admin));

    foreach (['super-admin', 'user', 'nonaktif', 'tidak-ada'] as $role) {
        $this->post(route('admin.users.store'), ['name' => 'Baru', 'email' => "{$role}@example.com", 'role' => $role])->assertSessionHasErrors('role');
    }

    $this->actingAs(userWithRole(DefaultRole::SuperAdmin))
        ->post(route('admin.users.store'), ['name' => 'Baru', 'email' => 'super@example.com', 'role' => 'super-admin'])
        ->assertSessionHasNoErrors();
});

test('a role holding abilities the creator lacks cannot be handed out', function () {
    $creator = staffWith([Ability::UserManage, Ability::PersonView]);
    Role::create(['name' => 'keuangan'])->givePermissionTo(Ability::PaymentVerify->value);
    Role::create(['name' => 'pelihat'])->givePermissionTo(Ability::PersonView->value);

    $this->actingAs($creator)
        ->post(route('admin.users.store'), ['name' => 'Baru', 'email' => 'keu@example.com', 'role' => 'keuangan'])
        ->assertSessionHasErrors('role');

    $this->post(route('admin.users.store'), ['name' => 'Baru', 'email' => 'pel@example.com', 'role' => 'pelihat'])
        ->assertSessionHasNoErrors();
});

test('roles of a staff member can be changed and the app-only roles stay', function () {
    $this->actingAs(userWithRole(DefaultRole::Admin));
    $staff = userWithRole(DefaultRole::Operator);
    $staff->assignRole(DefaultRole::User->value);

    $this->put(route('admin.users.update', $staff), ['roles' => ['admin', 'operator']])->assertSessionHasNoErrors();

    expect($staff->refresh()->getRoleNames()->sort()->values()->all())->toBe(['admin', 'operator', 'user']);

    $this->put(route('admin.users.update', $staff), ['roles' => ['admin']]);

    expect($staff->refresh()->getRoleNames()->sort()->values()->all())->toBe(['admin', 'user']);
});

test('a staff member needs at least one role and cannot be given the inactive role from here', function () {
    $this->actingAs(userWithRole(DefaultRole::Admin));
    $staff = userWithRole(DefaultRole::Operator);

    $this->put(route('admin.users.update', $staff), ['roles' => []])->assertSessionHasErrors('roles');
    $this->put(route('admin.users.update', $staff), ['roles' => ['nonaktif']])->assertSessionHasErrors('roles.0');

    expect($staff->refresh()->getRoleNames()->all())->toBe(['operator']);
});

test('a super admin cannot remove their own super admin role while another exists', function () {
    $me = userWithRole(DefaultRole::SuperAdmin);
    userWithRole(DefaultRole::SuperAdmin);

    $this->actingAs($me)->put(route('admin.users.update', $me), ['roles' => ['admin']])->assertSessionHasErrors('roles');

    expect($me->refresh()->hasRole(DefaultRole::SuperAdmin->value))->toBeTrue();
});

test('the last active super admin cannot be demoted', function () {
    $only = userWithRole(DefaultRole::SuperAdmin);

    $this->actingAs($only)->put(route('admin.users.update', $only), ['roles' => ['admin']])
        ->assertSessionHasErrors(['roles' => 'Super admin terakhir tidak bisa diturunkan. Tambahkan super admin lain dulu.']);

    expect($only->refresh()->hasRole(DefaultRole::SuperAdmin->value))->toBeTrue();
});

test('one super admin can demote another', function () {
    $me = userWithRole(DefaultRole::SuperAdmin);
    $other = userWithRole(DefaultRole::SuperAdmin);

    $this->actingAs($me)->put(route('admin.users.update', $other), ['roles' => ['admin']])->assertSessionHasNoErrors();

    expect($other->refresh()->hasRole(DefaultRole::SuperAdmin->value))->toBeFalse();
});

test('only a super admin can give the super admin role or change a super admin account', function () {
    $staff = userWithRole(DefaultRole::Operator);
    $superAdmin = userWithRole(DefaultRole::SuperAdmin);
    $this->actingAs(userWithRole(DefaultRole::Admin));

    $this->put(route('admin.users.update', $staff), ['roles' => ['operator', 'super-admin']])->assertSessionHasErrors('roles');
    $this->put(route('admin.users.update', $superAdmin), ['roles' => ['super-admin', 'operator']])->assertSessionHasErrors('roles');

    expect($staff->refresh()->hasRole(DefaultRole::SuperAdmin->value))->toBeFalse()
        ->and($superAdmin->refresh()->hasRole(DefaultRole::Operator->value))->toBeFalse();
});

test('roles with abilities the editor lacks can be neither given nor taken away', function () {
    $editor = staffWith([Ability::UserManage, Ability::PersonView]);
    $staff = userWithRole(DefaultRole::Operator);
    $staff->assignRole(Role::create(['name' => 'keuangan'])->givePermissionTo(Ability::PaymentVerify->value));
    Role::create(['name' => 'pelihat'])->givePermissionTo(Ability::PersonView->value);

    $this->actingAs($editor);

    $this->put(route('admin.users.update', $staff), ['roles' => ['operator', 'keuangan', 'admin']])->assertSessionHasErrors('roles');
    $this->put(route('admin.users.update', $staff), ['roles' => ['operator']])->assertSessionHasErrors('roles');
    $this->put(route('admin.users.update', $staff), ['roles' => ['operator', 'keuangan', 'pelihat']])->assertSessionHasNoErrors();

    expect($staff->refresh()->getRoleNames()->sort()->values()->all())->toBe(['keuangan', 'operator', 'pelihat']);
});

test('deactivating an account refuses every permission, ends its sessions, and keeps it in the list', function () {
    config(['session.driver' => 'database']);
    $this->actingAs(userWithRole(DefaultRole::Admin));
    $staff = userWithRole(DefaultRole::Operator);
    $staff->update(['remember_token' => 'token']);
    DB::table('sessions')->insert(['id' => 'abc', 'user_id' => $staff->id, 'payload' => '', 'last_activity' => now()->timestamp]);

    $this->post(route('admin.users.deactivate', $staff))->assertSessionHasNoErrors();

    $staff = $staff->refresh();

    expect($staff->hasRole(DefaultRole::Inactive->value))->toBeTrue()
        ->and($staff->can(Ability::AdminAccess->value))->toBeFalse()
        ->and($staff->remember_token)->toBeNull()
        ->and(DB::table('sessions')->where('user_id', $staff->id)->exists())->toBeFalse();

    $this->get(route('admin.users.index', ['status' => 'nonaktif']))
        ->assertInertia(fn ($page) => $page->has('users.data', 1)->where('users.data.0.id', $staff->id));
});

test('a deactivated account cannot open the admin panel, even a super admin', function () {
    $this->actingAs(userWithRole(DefaultRole::Admin));
    $operator = userWithRole(DefaultRole::Operator);
    $superAdmin = userWithRole(DefaultRole::SuperAdmin);
    $this->post(route('admin.users.deactivate', $operator));
    userWithRole(DefaultRole::SuperAdmin);
    $this->actingAs(User::query()->role(DefaultRole::SuperAdmin->value)->whereKeyNot($superAdmin->id)->firstOrFail());
    $this->post(route('admin.users.deactivate', $superAdmin));

    foreach ([$operator->refresh(), $superAdmin->refresh()] as $deactivated) {
        $response = $this->actingAs($deactivated)->get(route('admin.people.index'));

        // Refused outright, or signed out and sent to the login page when a stale session is detected.
        expect($response->status())->toBeIn([302, 403]);
        expect($deactivated->can(Ability::AdminAccess->value))->toBeFalse();
    }
});

test('an account cannot deactivate itself', function () {
    $me = userWithRole(DefaultRole::Admin);

    $this->actingAs($me)->post(route('admin.users.deactivate', $me))
        ->assertSessionHasErrors(['user' => 'Kamu tidak bisa menonaktifkan akunmu sendiri.']);

    expect($me->refresh()->hasRole(DefaultRole::Inactive->value))->toBeFalse();
});

test('the last active super admin cannot be deactivated', function () {
    $only = userWithRole(DefaultRole::SuperAdmin);
    $actor = staffWith([Ability::UserManage]);

    $this->actingAs($actor)->post(route('admin.users.deactivate', $only))->assertSessionHasErrors('user');

    expect($only->refresh()->hasRole(DefaultRole::Inactive->value))->toBeFalse();
});

test('a non super admin cannot act on a super admin account', function () {
    $superAdmin = userWithRole(DefaultRole::SuperAdmin);
    userWithRole(DefaultRole::SuperAdmin);
    $this->actingAs(userWithRole(DefaultRole::Admin));

    $this->post(route('admin.users.deactivate', $superAdmin))->assertSessionHasErrors('user');
    $this->post(route('admin.users.password-change', $superAdmin))->assertSessionHasErrors('user');
    $this->post(route('admin.users.setup-link', $superAdmin))->assertSessionHasErrors('user');

    expect($superAdmin->refresh()->hasRole(DefaultRole::Inactive->value))->toBeFalse()
        ->and(PasswordChange::isRequiredFor($superAdmin))->toBeFalse();
});

test('reactivating restores the permissions of an account', function () {
    $this->actingAs(userWithRole(DefaultRole::Admin));
    $staff = userWithRole(DefaultRole::Operator);
    $this->post(route('admin.users.deactivate', $staff));

    $this->post(route('admin.users.reactivate', $staff))->assertSessionHasNoErrors();

    $staff = $staff->refresh();

    expect($staff->hasRole(DefaultRole::Inactive->value))->toBeFalse()
        ->and($staff->can(Ability::AdminAccess->value))->toBeTrue()
        ->and($staff->hasRole(DefaultRole::Operator->value))->toBeTrue();
});

test('an account can be required to change its password', function () {
    $this->actingAs(userWithRole(DefaultRole::Admin));
    $staff = userWithRole(DefaultRole::Operator);

    $this->post(route('admin.users.password-change', $staff))->assertSessionHasNoErrors();

    expect(PasswordChange::isRequiredFor($staff))->toBeTrue();
});

test('the password setup link can be sent again', function () {
    Notification::fake();
    $this->actingAs(userWithRole(DefaultRole::Admin));
    $staff = userWithRole(DefaultRole::Operator);

    $this->post(route('admin.users.setup-link', $staff))->assertSessionHasNoErrors();

    Notification::assertSentTo($staff, ResetPassword::class);
});
