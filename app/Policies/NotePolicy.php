<?php

namespace App\Policies;

use App\Enums\Ability;
use App\Models\Note;
use App\Models\User;

class NotePolicy
{
    public function view(User $user, Note $note): bool
    {
        return $note->isVisibleTo($user);
    }

    public function create(User $user): bool
    {
        return $user->can(Ability::NoteCreate->value);
    }

    public function update(User $user, Note $note): bool
    {
        return $note->author_id === $user->id || $user->can(Ability::NoteManage->value);
    }

    public function delete(User $user, Note $note): bool
    {
        return $this->update($user, $note);
    }
}
