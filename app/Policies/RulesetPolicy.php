<?php

namespace App\Policies;

use App\Enums\Ability;
use App\Models\User;

class RulesetPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Ability::RulesetView->value);
    }

    public function view(User $user): bool
    {
        return $user->can(Ability::RulesetView->value);
    }

    public function create(User $user): bool
    {
        return $user->can(Ability::RulesetManage->value);
    }

    public function update(User $user): bool
    {
        return $user->can(Ability::RulesetManage->value);
    }

    public function delete(User $user): bool
    {
        return $user->can(Ability::RulesetManage->value);
    }
}
