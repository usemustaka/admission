<?php

namespace App\Support;

use App\Enums\Ability;
use App\Models\Registrant;
use App\Models\User;

/**
 * The login account of the parent who registered a child, which is the one the committee resets when a parent forgot
 * the password. Staff accounts are never returned, so this can never be used to reach a staff member's login.
 */
class ParentAccount
{
    public static function for(Registrant $registrant): ?User
    {
        $account = User::query()->where('person_id', $registrant->registered_by_person_id)->first();

        return $account === null || $account->can(Ability::AdminAccess->value) ? null : $account;
    }
}
