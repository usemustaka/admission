<?php

namespace App\Support;

use App\Enums\NoteUrgency;
use App\Enums\NoteVisibility;
use App\Models\Note;
use App\Models\Person;
use App\Models\User;

/**
 * The note the system leaves on a child when a parent registers them under a NIK that is already on record with
 * another name, and what happens to it once the committee has sorted it out.
 */
class IdentityMismatchNote
{
    /**
     * Warn the committee that the name typed by the parent does not match the person who owns this NIK.
     */
    public static function open(Person $child, string $typedName, string $nik): Note
    {
        return Note::create([
            'noteable_type' => $child->getMorphClass(),
            'noteable_id' => $child->id,
            'person_id' => $child->id,
            'author_id' => null,
            'body' => "Orang tua mendaftarkan anak dengan nama \"{$typedName}\", tetapi NIK {$nik} sudah tercatat atas nama \"{$child->name}\". Mohon dicek apakah NIK atau namanya yang keliru.",
            'urgency' => NoteUrgency::High,
            'visibility' => NoteVisibility::Public,
            'shared_with_family' => false,
        ]);
    }

    /**
     * The open warnings of a child about this typed name, written by the system and not yet closed.
     *
     * @return list<Note>
     */
    private static function openNotes(Person $child, string $typedName): array
    {
        return $child->notes()
            ->whereNull('author_id')
            ->where('urgency', NoteUrgency::High)
            ->get()
            ->filter(fn (Note $note): bool => str_contains($note->body, "dengan nama \"{$typedName}\""))
            ->values()
            ->all();
    }

    /**
     * The committee settled the mismatch: the warning stays as a trace, but is no longer urgent. The system wrote
     * it, so this is done here and not through the note policy.
     */
    public static function close(Person $child, string $typedName, User $by, string $outcome = ''): void
    {
        foreach (self::openNotes($child, $typedName) as $note) {
            $note->update([
                'urgency' => NoteUrgency::Normal,
                'body' => rtrim($note->body)."\n\nSelesai dicocokkan oleh {$by->name}".($outcome === '' ? '.' : ": {$outcome}"),
            ]);
        }
    }
}
