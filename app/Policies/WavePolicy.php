<?php

namespace App\Policies;

use App\Enums\Ability;
use App\Models\User;

class WavePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Ability::WaveView->value);
    }

    public function create(User $user): bool
    {
        return $user->can(Ability::WaveManage->value);
    }

    public function update(User $user): bool
    {
        return $user->can(Ability::WaveManage->value);
    }
}
