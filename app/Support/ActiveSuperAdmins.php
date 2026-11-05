<?php

namespace App\Support;

use App\Enums\DefaultRole;
use App\Models\User;

/**
 * Guards the last way into the roles and accounts screens: there must always be one active super admin.
 */
class ActiveSuperAdmins
{
    /**
     * Whether the user is the only active super admin.
     */
    public static function isLast(User $user): bool
    {
        if (! $user->hasRole(DefaultRole::SuperAdmin->value)) {
            return false;
        }

        return ! User::query()
            ->role(DefaultRole::SuperAdmin->value)
            ->whereKeyNot($user->id)
            ->whereDoesntHave('roles', fn ($roles) => $roles->where('name', DefaultRole::Inactive->value))
            ->exists();
    }
}
