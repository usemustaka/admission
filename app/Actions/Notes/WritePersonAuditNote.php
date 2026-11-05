<?php

namespace App\Actions\Notes;

use App\Enums\NoteUrgency;
use App\Enums\NoteVisibility;
use App\Models\Note;
use App\Models\Person;
use App\Models\User;

class WritePersonAuditNote
{
    public function __construct(private SaveNote $saveNote) {}

    /**
     * Leave a trace of a correction on a person: a private note written by the staff member who made it.
     */
    public function handle(Person $person, User $by, string $body): Note
    {
        return $this->saveNote->handle(
            new NoteData($body, NoteUrgency::Normal, NoteVisibility::Private),
            author: $by,
            noteable: $person,
        );
    }
}
