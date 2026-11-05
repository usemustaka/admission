<?php

namespace App\Actions\Users;

use App\Enums\DefaultRole;
use App\Models\User;
use App\Support\ActiveSuperAdmins;
use App\Support\RoleRules;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;

/**
 * Replaces the staff roles of an account. The app-only roles `user` and `nonaktif` are kept as they are, since
 * the first marks the login type and the second is managed by deactivating the account.
 */
class ChangeUserRoles
{
    /**
     * @param  list<string>  $roleNames
     *
     * @throws ValidationException
     */
    public function handle(User $actor, User $user, array $roleNames): void
    {
        $roleNames = array_values(array_unique($roleNames));
        $current = $user->getRoleNames()->all();
        $kept = array_values(array_intersect($current, RoleRules::nonStaffNames()));
        $held = array_values(array_diff($current, $kept));

        $added = array_values(array_diff($roleNames, $held));
        $removed = array_values(array_diff($held, $roleNames));

        if ($added === [] && $removed === []) {
            return;
        }

        $this->guard($actor, $user, $added, $removed);

        $user->syncRoles([...$kept, ...$roleNames]);
    }

    /**
     * @param  list<string>  $added
     * @param  list<string>  $removed
     *
     * @throws ValidationException
     */
    private function guard(User $actor, User $user, array $added, array $removed): void
    {
        $superAdmin = DefaultRole::SuperAdmin->value;
        $isActorSuperAdmin = $actor->hasRole($superAdmin);

        if (in_array($superAdmin, $removed, true)) {
            if (ActiveSuperAdmins::isLast($user)) {
                $this->fail('Super admin terakhir tidak bisa diturunkan. Tambahkan super admin lain dulu.');
            }

            if ($actor->is($user)) {
                $this->fail('Kamu tidak bisa mencabut peran super admin dari dirimu sendiri.');
            }
        }

        if (! $isActorSuperAdmin && ($user->hasRole($superAdmin) || in_array($superAdmin, $added, true))) {
            $this->fail('Hanya super admin yang bisa mengubah akun super admin atau memberi peran super admin.');
        }

        foreach (Role::query()->with('permissions')->whereIn('name', [...$added, ...$removed])->get() as $role) {
            if (RoleRules::unheldBy($actor, RoleRules::abilitiesOf($role)) !== []) {
                $this->fail("Peran {$role->name} berisi izin yang tidak kamu miliki, jadi tidak bisa kamu beri atau cabut.");
            }
        }
    }

    /**
     * @throws ValidationException
     */
    private function fail(string $message): never
    {
        throw ValidationException::withMessages(['roles' => $message]);
    }
}
