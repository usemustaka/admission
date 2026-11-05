<?php

namespace App\Policies;

use App\Enums\Ability;
use App\Models\User;

class PersonPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can(Ability::PersonView->value);
    }

    public function view(User $user): bool
    {
        return $user->can(Ability::PersonView->value);
    }

    public function create(User $user): bool
    {
        return $user->can(Ability::PersonCreate->value);
    }

    public function update(User $user): bool
    {
        return $user->can(Ability::PersonUpdate->value);
    }
}
