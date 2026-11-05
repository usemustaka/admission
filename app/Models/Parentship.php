<?php

namespace App\Models;

use App\Enums\ParentKind;
use App\Enums\ParentRelation;
use Database\Factories\ParentshipFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A family relation: `related_person_id` is the father, mother, or guardian of `person_id` (the child).
 *
 * @property int $id
 * @property int $person_id
 * @property int $related_person_id
 * @property ParentRelation $relation
 * @property ParentKind|null $kind
 * @property bool $is_primary_contact
 */
#[Fillable(['person_id', 'related_person_id', 'relation', 'kind', 'is_primary_contact'])]
class Parentship extends Model
{
    /** @use HasFactory<ParentshipFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'relation' => ParentRelation::class,
            'kind' => ParentKind::class,
            'is_primary_contact' => 'boolean',
        ];
    }

    /**
     * The child.
     *
     * @return BelongsTo<Person, $this>
     */
    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }

    /**
     * The father, mother, or guardian.
     *
     * @return BelongsTo<Person, $this>
     */
    public function relatedPerson(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'related_person_id');
    }
}
