<?php

namespace App\Actions\Admission;

use App\Models\Ruleset;
use Illuminate\Support\Collection;

class FindShadowedRulesets
{
    /**
     * Find the active rulesets that can never win because a ruleset checked earlier matches every applicant they
     * would match (an empty condition matches anything). Inactive rulesets are never checked, so they never shadow.
     *
     * @param  Collection<int, Ruleset>  $rulesets  in priority order
     * @return array<int, Ruleset> the shadowing ruleset by the id of the shadowed one
     */
    public function handle(Collection $rulesets): array
    {
        $shadowed = [];
        $earlier = [];

        foreach ($rulesets as $ruleset) {
            if (! $ruleset->is_active) {
                continue;
            }

            foreach ($earlier as $candidate) {
                if ($this->covers($candidate, $ruleset)) {
                    $shadowed[$ruleset->id] = $candidate;

                    break;
                }
            }

            $earlier[] = $ruleset;
        }

        return $shadowed;
    }

    private function covers(Ruleset $earlier, Ruleset $later): bool
    {
        return ($earlier->wave_id === null || $earlier->wave_id === $later->wave_id)
            && ($earlier->organization_id === null || $earlier->organization_id === $later->organization_id)
            && ($earlier->gender === null || $earlier->gender === $later->gender);
    }
}
