<?php

namespace App\Support;

use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SyncRows
{
    /**
     * Make the children of a relation match the submitted rows. Rows with the id of an existing child update it
     * (so references to it stay valid), rows without one create a child, and children not submitted are deleted.
     *
     * @template TChild of Model
     * @template TParent of Model
     *
     * @param  HasMany<TChild, TParent>  $relation
     * @param  list<array<string, mixed>>  $rows
     * @param  Closure(array<string, mixed>, int): array<string, mixed>  $attributes
     * @return list<TChild>
     */
    public static function handle(HasMany $relation, array $rows, Closure $attributes): array
    {
        $existing = $relation->get()->keyBy('id');
        $kept = [];

        foreach ($rows as $index => $row) {
            $child = isset($row['id']) ? $existing->get($row['id']) : null;

            if ($child !== null) {
                $child->update($attributes($row, $index));
            } else {
                $child = $relation->create($attributes($row, $index));
            }

            $kept[] = $child;
        }

        $relation->getRelated()->newQuery()
            ->where($relation->getForeignKeyName(), $relation->getParentKey())
            ->whereNotIn('id', array_map(fn (Model $child) => $child->getKey(), $kept))
            ->delete();

        return $kept;
    }
}
