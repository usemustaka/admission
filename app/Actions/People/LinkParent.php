<?php

namespace App\Actions\People;

use App\Enums\ParentKind;
use App\Enums\ParentRelation;
use App\Models\Parentship;
use App\Models\Person;
use Illuminate\Support\Facades\DB;

class LinkParent
{
    /**
     * Make `$parent` a father, mother, or guardian of `$child`. The child's first parent becomes the primary contact.
     */
    public function handle(Person $child, Person $parent, ParentRelation $relation, ?ParentKind $kind = null, bool $isPrimaryContact = false): Parentship
    {
        return DB::transaction(function () use ($child, $parent, $relation, $kind, $isPrimaryContact): Parentship {
            $hasPrimary = $child->parentships()->where('is_primary_contact', true)->exists();

            $parentship = $child->parentships()->create([
                'related_person_id' => $parent->id,
                'relation' => $relation,
                'kind' => $relation->hasKind() ? $kind : null,
                'is_primary_contact' => false,
            ]);

            if ($isPrimaryContact || ! $hasPrimary) {
                app(SetPrimaryContact::class)->handle($parentship);
            }

            return $parentship->refresh();
        });
    }
}
