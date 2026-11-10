<?php

namespace App\Actions\Admission;

use App\Models\Ruleset;
use App\Support\RegistrationFields;

class SyncRulesetRequiredFields
{
    /**
     * Set which details a parent has to fill in before sending the data. Only registrations made after this follow the
     * change, because each keeps its own copy of the ruleset.
     *
     * @param  list<string>  $fields  keys from `RegistrationFields::allKeys()`
     */
    public function handle(Ruleset $ruleset, array $fields): void
    {
        $ruleset->update(['required_fields' => array_values(array_intersect(RegistrationFields::allKeys(), $fields))]);
    }
}
