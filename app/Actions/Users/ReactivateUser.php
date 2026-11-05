<?php

namespace App\Actions\Users;

use App\Enums\DefaultRole;
use App\Models\User;

class ReactivateUser
{
    public function handle(User $user): void
    {
        $user->removeRole(DefaultRole::Inactive->value);
    }
}
