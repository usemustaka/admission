<?php

use App\Enums\Ability;
use App\Enums\DefaultRole;
use App\Models\Note;
use App\Models\NoteShare;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

beforeEach(fn () => $this->withoutVite());

/**
 * Names of the abilities a role holds.
 *
 * @return list<string>
 */
function abilitiesOfRole(string $name): array
{
    return Role::findByName($name, 'web')->permissions->pluck('name')->sort()->values()->all();
}

test('role screens are closed to guests, parents, and staff without the role permission', function () {
    $this->get(route('admin.roles.index'))->assertRedirect(route('login'));

    $operator = userWithRole(DefaultRole::Operator);
    $role = Role::findByName('operator', 'web');

    foreach ([User::factory()->create(), $operator] as $outsider) {
        $this->actingAs($outsider);

        $this->get(route('admin.roles.index'))->assertForbidden();
        $this->get(route('admin.roles.create'))->assertForbidden();
        $this->get(route('admin.roles.edit', $role))->assertForbidden();
        $this->post(route('admin.roles.store'), ['name' => 'baru', 'abilities' => []])->assertForbidden();
        $this->put(route('admin.roles.update', $role), ['name' => 'operator', 'abilities' => []])->assertForbidden();
        $this->post(route('admin.roles.duplicate', $role))->assertForbidden();
        $this->delete(route('admin.roles.destroy', $role))->assertForbidden();
    }

    expect(abilitiesOfRole('operator'))->not->toBe([]);
});

test('the role list shows users and ability counts', function () {
    $admin = userWithRole(DefaultRole::Admin);
    userWithRole(DefaultRole::Operator);

    $this->actingAs($admin)->get(route('admin.roles.index'))
        ->assertInertia(fn ($page) => $page
            ->where('roles', fn ($roles) => collect($roles)->firstWhere('name', 'operator')['users_count'] === 1
                && collect($roles)->firstWhere('name', 'operator')['abilities_count'] === count(RolesAndPermissionsSeeder::operatorAbilities())
                && collect($roles)->firstWhere('name', 'super-admin')['abilities_count'] === count(Ability::cases())
                && collect($roles)->firstWhere('name', 'super-admin')['is_locked'] === true
                && collect($roles)->firstWhere('name', 'admin')['is_built_in'] === true));
});

test('the edit screen groups every ability by module', function () {
    $this->actingAs(userWithRole(DefaultRole::Admin))->get(route('admin.roles.edit', Role::findByName('operator', 'web')))
        ->assertInertia(fn ($page) => $page
            ->where('groups.0.module', 'core')
            ->where('groups.0.title', 'Umum')
            ->where('groups.1.title', 'Pendaftaran')
            ->where('groups', fn ($groups) => collect($groups)->sum(fn ($group) => count($group['abilities'])) === count(Ability::cases())));
});

test('a custom role can be created with abilities, and the permission cache is refreshed', function () {
    $this->actingAs(userWithRole(DefaultRole::Admin));
    $user = User::factory()->create();
    $user->assignRole(Role::create(['name' => 'lama']));
    expect($user->can(Ability::PersonView->value))->toBeFalse();

    $this->post(route('admin.roles.store'), ['name' => '  Bendahara ', 'abilities' => [Ability::AdminAccess->value, Ability::PaymentView->value]])
        ->assertRedirect(route('admin.roles.edit', Role::findByName('bendahara', 'web')));

    expect(abilitiesOfRole('bendahara'))->toBe([Ability::PaymentView->value, Ability::AdminAccess->value]);

    $other = User::factory()->create()->assignRole('bendahara');

    expect($other->can(Ability::PaymentView->value))->toBeTrue()
        ->and($other->can(Ability::PersonView->value))->toBeFalse();
});

test('editing a role changes what its users can do right away', function () {
    $this->actingAs(userWithRole(DefaultRole::Admin));
    $role = Role::create(['name' => 'bendahara'])->givePermissionTo(Ability::AdminAccess->value, Ability::PaymentView->value);
    $user = User::factory()->create()->assignRole($role);
    expect($user->can(Ability::PaymentView->value))->toBeTrue();

    $this->put(route('admin.roles.update', $role), ['name' => 'kasir', 'abilities' => [Ability::AdminAccess->value, Ability::PersonView->value]])
        ->assertRedirect(route('admin.roles.edit', $role));

    $user = $user->fresh();

    expect($role->refresh()->name)->toBe('kasir')
        ->and($user->can(Ability::PaymentView->value))->toBeFalse()
        ->and($user->can(Ability::PersonView->value))->toBeTrue();
});

test('role names must be lowercase slugs, unique, and not reserved', function () {
    $this->actingAs(userWithRole(DefaultRole::Admin));
    Role::create(['name' => 'bendahara']);
    $roles = Role::count();

    $cases = [
        'kosong' => '',
        'karakter aneh' => 'Bend@hara!',
        'terlalu panjang' => str_repeat('a', 51),
        'sudah ada' => 'bendahara',
        'bawaan' => 'operator',
        'terkunci' => 'super-admin',
        'peran orang tua' => 'user',
        'nonaktif' => 'nonaktif',
    ];

    foreach ($cases as $name) {
        $this->post(route('admin.roles.store'), ['name' => $name, 'abilities' => []])->assertSessionHasErrors('name');
    }

    expect(Role::count())->toBe($roles);
});

test('only abilities that exist in the app are accepted', function () {
    $this->actingAs(userWithRole(DefaultRole::Admin));
    Permission::create(['name' => 'sembarang::izin']);

    $this->post(route('admin.roles.store'), ['name' => 'baru', 'abilities' => ['sembarang::izin']])->assertSessionHasErrors('abilities.0');

    expect(Role::where('name', 'baru')->exists())->toBeFalse();
});

test('locked roles cannot be edited, renamed, or deleted', function () {
    $this->actingAs(userWithRole(DefaultRole::SuperAdmin));

    foreach (['super-admin', 'user', 'nonaktif'] as $name) {
        $role = Role::findByName($name, 'web');

        $this->put(route('admin.roles.update', $role), ['name' => 'lain', 'abilities' => [Ability::AdminAccess->value]])->assertForbidden();
        $this->delete(route('admin.roles.destroy', $role))->assertSessionHasErrors('role');

        expect(Role::where('name', $name)->exists())->toBeTrue()
            ->and(abilitiesOfRole($name))->not->toContain(Ability::AdminAccess->value);
    }

    $this->get(route('admin.roles.edit', Role::findByName('super-admin', 'web')))
        ->assertInertia(fn ($page) => $page->where('role.is_locked', true)->where('role.abilities', array_column(Ability::cases(), 'value')));
});

test('built-in roles may have their abilities edited but not be renamed or deleted', function () {
    $this->actingAs(userWithRole(DefaultRole::SuperAdmin));
    $operator = Role::findByName('operator', 'web');

    $this->put(route('admin.roles.update', $operator), ['name' => 'petugas', 'abilities' => [Ability::AdminAccess->value]])->assertSessionHasErrors('name');
    $this->delete(route('admin.roles.destroy', $operator))->assertSessionHasErrors('role');

    $this->put(route('admin.roles.update', $operator), ['name' => 'operator', 'abilities' => [Ability::AdminAccess->value, Ability::PersonView->value]])
        ->assertSessionHasNoErrors();

    expect(abilitiesOfRole('operator'))->toBe([Ability::AdminAccess->value, Ability::PersonView->value]);
});

test('admin access cannot be taken from a role whose accounts still use it', function () {
    $this->actingAs(userWithRole(DefaultRole::SuperAdmin));
    $operator = Role::findByName('operator', 'web');
    userWithRole(DefaultRole::Operator);

    $this->put(route('admin.roles.update', $operator), ['name' => 'operator', 'abilities' => [Ability::PersonView->value]])
        ->assertSessionHasErrors('abilities');

    expect(abilitiesOfRole('operator'))->toContain(Ability::AdminAccess->value);

    $empty = Role::create(['name' => 'kosong'])->givePermissionTo(Ability::AdminAccess->value);

    $this->put(route('admin.roles.update', $empty), ['name' => 'kosong', 'abilities' => []])->assertSessionHasNoErrors();
});

test('abilities the editor lacks cannot be given or taken away', function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $editor = User::factory()->create();
    $editor->givePermissionTo(Ability::AdminAccess->value, Ability::RoleManage->value, Ability::PersonView->value);
    $role = Role::create(['name' => 'keuangan'])->givePermissionTo(Ability::PaymentVerify->value);
    $this->actingAs($editor);

    $this->post(route('admin.roles.store'), ['name' => 'baru', 'abilities' => [Ability::PaymentExport->value]])->assertSessionHasErrors('abilities');
    $this->put(route('admin.roles.update', $role), ['name' => 'keuangan', 'abilities' => [Ability::PaymentVerify->value, Ability::PaymentExport->value]])->assertSessionHasErrors('abilities');
    $this->put(route('admin.roles.update', $role), ['name' => 'keuangan', 'abilities' => []])->assertSessionHasErrors('abilities');

    $this->put(route('admin.roles.update', $role), ['name' => 'keuangan', 'abilities' => [Ability::PaymentVerify->value, Ability::PersonView->value]])
        ->assertSessionHasNoErrors();

    expect(abilitiesOfRole('keuangan'))->toBe([Ability::PaymentVerify->value, Ability::PersonView->value]);
    expect(Role::where('name', 'baru')->exists())->toBeFalse();
});

test('a role can be duplicated under a free name', function () {
    $this->actingAs(userWithRole(DefaultRole::Admin));
    $operator = Role::findByName('operator', 'web');

    $this->post(route('admin.roles.duplicate', $operator))->assertSessionHasNoErrors();
    $this->post(route('admin.roles.duplicate', $operator))->assertSessionHasNoErrors();

    expect(abilitiesOfRole('operator (salinan)'))->toBe(abilitiesOfRole('operator'))
        ->and(abilitiesOfRole('operator (salinan 2)'))->toBe(abilitiesOfRole('operator'));

    $copy = Role::findByName('operator (salinan)', 'web');

    $this->put(route('admin.roles.update', $copy), ['name' => 'operator (salinan)', 'abilities' => [Ability::AdminAccess->value]])->assertSessionHasNoErrors();
});

test('a role holding abilities the user lacks cannot be duplicated', function () {
    $this->seed(RolesAndPermissionsSeeder::class);
    $editor = User::factory()->create();
    $editor->givePermissionTo(Ability::AdminAccess->value, Ability::RoleManage->value);

    $this->actingAs($editor)->post(route('admin.roles.duplicate', Role::findByName('operator', 'web')))->assertSessionHasErrors('role');

    expect(Role::where('name', 'like', 'operator (%')->exists())->toBeFalse();
});

test('a role is deleted only when no account uses it, and its note shares go with it', function () {
    $this->actingAs(userWithRole(DefaultRole::Admin));
    $role = Role::create(['name' => 'bendahara']);
    $user = User::factory()->create()->assignRole($role);
    $note = Note::factory()->create();
    NoteShare::create(['note_id' => $note->id, 'shareable_type' => 'role', 'shareable_id' => $role->id]);

    $this->delete(route('admin.roles.destroy', $role))->assertSessionHasErrors('role');
    expect(Role::where('name', 'bendahara')->exists())->toBeTrue();

    $user->removeRole($role);

    $this->delete(route('admin.roles.destroy', $role))->assertRedirect(route('admin.roles.index'));

    expect(Role::where('name', 'bendahara')->exists())->toBeFalse()
        ->and(NoteShare::where('shareable_type', 'role')->count())->toBe(0);
});

test('every role change clears the cached permissions', function () {
    $this->actingAs(userWithRole(DefaultRole::Admin));
    $registrar = app(PermissionRegistrar::class);
    $role = Role::create(['name' => 'bendahara']);

    $registrar->getPermissions();
    $this->put(route('admin.roles.update', $role), ['name' => 'bendahara', 'abilities' => [Ability::PersonView->value]]);

    expect(cache()->has($registrar->cacheKey))->toBeFalse();
});

test('running the seeder again keeps what was edited on screen but hands new abilities to admins', function () {
    $this->actingAs(userWithRole(DefaultRole::SuperAdmin));
    $operator = Role::findByName('operator', 'web');
    $this->put(route('admin.roles.update', $operator), ['name' => 'operator', 'abilities' => [Ability::AdminAccess->value, Ability::PersonView->value]]);
    $this->post(route('admin.roles.store'), ['name' => 'bendahara', 'abilities' => [Ability::AdminAccess->value]]);
    Permission::findByName(Ability::NoteDelete->value, 'web')->delete();
    Role::findByName('admin', 'web')->revokePermissionTo(Ability::RoleManage->value);

    $this->seed(RolesAndPermissionsSeeder::class);

    expect(abilitiesOfRole('operator'))->toBe([Ability::AdminAccess->value, Ability::PersonView->value])
        ->and(abilitiesOfRole('bendahara'))->toBe([Ability::AdminAccess->value])
        ->and(abilitiesOfRole('admin'))->toContain(Ability::NoteDelete->value)
        ->and(abilitiesOfRole('admin'))->not->toContain(Ability::RoleManage->value);
});
