<?php

namespace App\Actions\Admission;

use App\Enums\Gender;
use App\Models\Ruleset;

class SaveRuleset
{
    /**
     * Create or update the conditions of a ruleset. New rulesets go to the end of the priority order.
     */
    public function handle(?Ruleset $ruleset, string $name, ?int $waveId, ?int $organizationId, ?Gender $gender, bool $isActive): Ruleset
    {
        $ruleset ??= new Ruleset(['priority' => (int) Ruleset::query()->max('priority') + 1]);

        $ruleset->fill([
            'name' => $name,
            'wave_id' => $waveId,
            'organization_id' => $organizationId,
            'gender' => $gender,
            'is_active' => $isActive,
        ])->save();

        return $ruleset;
    }
}
