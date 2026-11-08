<?php

namespace App\Actions\Admission;

use App\Enums\DocumentType;
use App\Models\Ruleset;

class SyncRulesetDocuments
{
    /**
     * Set what the ruleset asks for, per core file type: `none` (not asked), `optional` or `required`. Only
     * registrations made after this follow the change, because each keeps its own copy of the ruleset.
     *
     * @param  array<string, string>  $choices  keyed by the value of a `DocumentType`
     */
    public function handle(Ruleset $ruleset, array $choices): void
    {
        foreach (DocumentType::core() as $type) {
            $choice = $choices[$type->value] ?? 'none';

            if ($choice === 'none') {
                $ruleset->documents()->where('type', $type)->delete();

                continue;
            }

            $ruleset->documents()->updateOrCreate(['type' => $type], ['is_required' => $choice === 'required']);
        }
    }
}
