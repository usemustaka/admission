<?php

namespace App\Models;

use App\Enums\NoteUrgency;
use App\Enums\NoteVisibility;
use Database\Factories\NoteFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * A note attached to any entity. `person_id` is the child the note is about, which decides who counts as "the family".
 *
 * @property int $id
 * @property string $noteable_type
 * @property int $noteable_id
 * @property int|null $person_id
 * @property int $author_id
 * @property string $body
 * @property NoteUrgency $urgency
 * @property NoteVisibility $visibility
 * @property bool $shared_with_family
 * @property Carbon|null $created_at
 */
#[Fillable(['noteable_type', 'noteable_id', 'person_id', 'author_id', 'body', 'urgency', 'visibility', 'shared_with_family'])]
class Note extends Model
{
    /** @use HasFactory<NoteFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'urgency' => NoteUrgency::class,
            'visibility' => NoteVisibility::class,
            'shared_with_family' => 'boolean',
        ];
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function noteable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * The child this note is about.
     *
     * @return BelongsTo<Person, $this>
     */
    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    /**
     * @return HasMany<NoteShare, $this>
     */
    public function shares(): HasMany
    {
        return $this->hasMany(NoteShare::class);
    }

    /**
     * Notes the user is allowed to read: their own, public ones (for staff who may view notes),
     * ones shared with them or one of their roles, and ones shared with the family of the child.
     *
     * @param  Builder<Note>  $query
     */
    #[Scope]
    protected function visibleTo(Builder $query, User $user): void
    {
        $query->where(function (Builder $query) use ($user): void {
            $query->where('author_id', $user->id)
                ->orWhereHas('shares', function (Builder $shares) use ($user): void {
                    $shares->where(function (Builder $target) use ($user): void {
                        $target->where(fn (Builder $q) => $q->where('shareable_type', 'user')->where('shareable_id', $user->id))
                            ->orWhere(fn (Builder $q) => $q->where('shareable_type', 'role')
                                ->whereIn('shareable_id', $user->roles()->pluck('roles.id')));
                    });
                });

            if ($user->can('core::note.view')) {
                $query->orWhere('visibility', NoteVisibility::Public);
            }

            if ($user->person_id !== null) {
                $query->orWhere(function (Builder $family) use ($user): void {
                    $family->where('shared_with_family', true)
                        ->whereIn('person_id', Parentship::query()
                            ->where('related_person_id', $user->person_id)
                            ->select('person_id'));
                });
            }
        });
    }

    public function isVisibleTo(User $user): bool
    {
        return static::query()->whereKey($this->getKey())->visibleTo($user)->exists();
    }

    /**
     * Replace who this note is shared with.
     *
     * @param  list<int>  $userIds
     * @param  list<int>  $roleIds
     */
    public function syncShares(array $userIds, array $roleIds): void
    {
        $this->shares()->delete();

        $rows = [
            ...array_map(fn (int $id): array => ['shareable_type' => 'user', 'shareable_id' => $id], array_unique($userIds)),
            ...array_map(fn (int $id): array => ['shareable_type' => 'role', 'shareable_id' => $id], array_unique($roleIds)),
        ];

        $this->shares()->createMany($rows);
    }
}
