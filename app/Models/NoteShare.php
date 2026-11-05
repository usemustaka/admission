<?php

namespace App\Models;

use Database\Factories\NoteShareFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * A note shared with one user or one role.
 *
 * @property int $id
 * @property int $note_id
 * @property string $shareable_type
 * @property int $shareable_id
 */
#[Fillable(['note_id', 'shareable_type', 'shareable_id'])]
class NoteShare extends Model
{
    /** @use HasFactory<NoteShareFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<Note, $this>
     */
    public function note(): BelongsTo
    {
        return $this->belongsTo(Note::class);
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function shareable(): MorphTo
    {
        return $this->morphTo();
    }
}
