<?php

namespace App\Actions\Users;

use App\Models\User;
use App\Support\PasswordChange;
use App\Support\UserSessions;

/**
 * Lets an admin choose a password for an account directly, for when the reset email does not arrive or the person
 * cannot read it. The person is asked to choose their own password at the next sign-in, and every device that was
 * signed in with the old password is signed out.
 */
class SetUserPassword
{
    public function handle(User $user, string $password, bool $forceChange = true): void
    {
        $user->forceFill(['password' => $password])->save();

        UserSessions::forgetAll($user);

        if ($forceChange) {
            PasswordChange::require($user);
        }
    }
}
