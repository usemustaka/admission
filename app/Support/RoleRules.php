<?php

namespace App\Support;

use App\Enums\Ability;
use App\Enums\DefaultRole;
use App\Models\User;
use Illuminate\Support\Collection;
use Spatie\Permission\Models\Role;

/**
 * Rules about which roles are fixed and which roles one staff member may hand out, shared by the staff and
 * role screens so both agree.
 */
class RoleRules
{
    /**
     * Roles the app depends on: they cannot be renamed, deleted or have their abilities edited.
     *
     * @return list<string>
     */
    public static function lockedNames(): array
    {
        return [DefaultRole::SuperAdmin->value, DefaultRole::User->value, DefaultRole::Inactive->value];
    }

    /**
     * Roles that come with the app: their abilities can change, but not their name or existence.
     *
     * @return list<string>
     */
    public static function builtInNames(): array
    {
        return [DefaultRole::Admin->value, DefaultRole::Operator->value];
    }

    public static function isLocked(Role $role): bool
    {
        return in_array($role->name, self::lockedNames(), true);
    }

    public static function isBuiltIn(Role $role): bool
    {
        return in_array($role->name, self::builtInNames(), true);
    }

    /**
     * Roles that mean something on their own and are never picked from the staff form.
     *
     * @return list<string>
     */
    public static function nonStaffNames(): array
    {
        return [DefaultRole::User->value, DefaultRole::Inactive->value];
    }

    /**
     * Abilities of a role that exist in the app, as permission names.
     *
     * @return list<string>
     */
    public static function abilitiesOf(Role $role): array
    {
        if ($role->name === DefaultRole::SuperAdmin->value) {
            return array_column(Ability::cases(), 'value');
        }

        return array_values(array_intersect(
            array_column(Ability::cases(), 'value'),
            $role->permissions->pluck('name')->all(),
        ));
    }

    /**
     * The abilities among the given ones that the user does not hold themselves.
     *
     * @param  iterable<string>  $abilities
     * @return list<string>
     */
    public static function unheldBy(User $user, iterable $abilities): array
    {
        $unheld = [];

        foreach ($abilities as $ability) {
            if (! $user->can($ability)) {
                $unheld[] = $ability;
            }
        }

        return $unheld;
    }

    /**
     * Roles the user may give to a staff member: not the app-only roles, `super-admin` only for super admins, and
     * nothing that carries abilities the user does not hold.
     *
     * @return Collection<int, Role>
     */
    public static function assignableBy(User $actor): Collection
    {
        $isSuperAdmin = $actor->hasRole(DefaultRole::SuperAdmin->value);

        return Role::query()
            ->with('permissions')
            ->whereNotIn('name', self::nonStaffNames())
            ->orderBy('name')
            ->get()
            ->filter(fn (Role $role): bool => match (true) {
                $role->name === DefaultRole::SuperAdmin->value => $isSuperAdmin,
                default => self::unheldBy($actor, self::abilitiesOf($role)) === [],
            })
            ->values();
    }
}
