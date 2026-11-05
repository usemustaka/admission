<?php

namespace App\Actions\Notes;

use App\Contracts\HasNoteSubject;
use App\Models\Note;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class SaveNote
{
    /**
     * Create a note on an entity, or update an existing one, together with who it is shared with.
     */
    public function handle(NoteData $data, ?Note $note = null, ?User $author = null, (Model&HasNoteSubject)|null $noteable = null): Note
    {
        return DB::transaction(function () use ($data, $note, $author, $noteable): Note {
            $note ??= new Note([
                'noteable_type' => $noteable?->getMorphClass(),
                'noteable_id' => $noteable?->getKey(),
                'person_id' => $noteable?->notePersonId(),
                'author_id' => $author?->id,
            ]);

            $note->fill([
                'body' => $data->body,
                'urgency' => $data->urgency,
                'visibility' => $data->visibility,
                'shared_with_family' => $data->sharedWithFamily,
            ])->save();

            $note->syncShares($data->userIds, $data->roleIds);

            return $note;
        });
    }
}
