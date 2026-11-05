<?php

namespace App\Actions\Roles;

use App\Enums\Ability;
use App\Models\User;
use App\Support\RoleRules;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Creates a role or changes its name and abilities. Locked roles and built-in names stay as they are, nobody
 * can hand out an ability they do not hold, and a role whose staff still need the panel keeps its admin access.
 */
class SaveRole
{
    /**
     * @param  list<string>  $abilities  permission names from the Ability enum
     *
     * @throws ValidationException
     */
    public function handle(User $actor, ?Role $role, string $name, array $abilities): Role
    {
        $abilities = array_values(array_unique($abilities));
        $before = $role === null ? [] : RoleRules::abilitiesOf($role);

        if ($role !== null && RoleRules::isLocked($role)) {
            $this->fail('name', 'Peran ini terkunci dan tidak bisa diubah.');
        }

        if ($role !== null && RoleRules::isBuiltIn($role) && $name !== $role->name) {
            $this->fail('name', 'Nama peran bawaan tidak bisa diubah.');
        }

        $this->guardUnheldAbilities($actor, $before, $abilities);
        $this->guardAdminAccess($role, $before, $abilities);

        return DB::transaction(function () use ($role, $name, $abilities): Role {
            $role ??= new Role(['guard_name' => 'web']);
            $role->name = $name;
            $role->save();

            foreach ($abilities as $ability) {
                Permission::findOrCreate($ability, 'web');
            }

            $role->syncPermissions($abilities);

            app(PermissionRegistrar::class)->forgetCachedPermissions();

            return $role;
        });
    }

    /**
     * Abilities the actor lacks can neither be added to nor removed from the role.
     *
     * @param  list<string>  $before
     * @param  list<string>  $after
     *
     * @throws ValidationException
     */
    private function guardUnheldAbilities(User $actor, array $before, array $after): void
    {
        $changed = [...array_diff($after, $before), ...array_diff($before, $after)];

        if (RoleRules::unheldBy($actor, $changed) !== []) {
            $this->fail('abilities', 'Kamu tidak bisa menambah atau mencabut izin yang tidak kamu miliki sendiri.');
        }
    }

    /**
     * @param  list<string>  $before
     * @param  list<string>  $after
     *
     * @throws ValidationException
     */
    private function guardAdminAccess(?Role $role, array $before, array $after): void
    {
        $access = Ability::AdminAccess->value;

        if ($role === null || ! in_array($access, $before, true) || in_array($access, $after, true)) {
            return;
        }

        if ($role->users()->exists()) {
            $this->fail('abilities', 'Izin masuk panel admin tidak bisa dicabut selama peran ini masih dipakai akun. Pindahkan akunnya ke peran lain dulu.');
        }
    }

    /**
     * @throws ValidationException
     */
    private function fail(string $field, string $message): never
    {
        throw ValidationException::withMessages([$field => $message]);
    }
}
