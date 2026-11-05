<?php

namespace App\Actions\Portal;

use App\Actions\People\LinkParent;
use App\Models\Registrant;

class LinkRegisteringParent
{
    public function __construct(private LinkParent $linkParent) {}

    /**
     * Make the parent who registered the child a father, mother, or guardian of that child, as they said they
     * were. Does nothing when they are already linked.
     */
    public function handle(Registrant $registrant): void
    {
        $child = $registrant->person;

        if ($child->parentships()->where('related_person_id', $registrant->registered_by_person_id)->exists()) {
            return;
        }

        $this->linkParent->handle(
            $child,
            $registrant->registeredBy,
            $registrant->registered_as_relation,
            $registrant->registered_as_kind,
        );
    }
}
