<?php

namespace App\Actions\Admission;

use App\Enums\Gender;
use App\Models\Ruleset;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class ResolveRuleset
{
    /**
     * Find the ruleset that applies to an applicant: the active one with the highest priority whose conditions
     * all match. A ruleset with an empty condition matches any value for it.
     */
    public function handle(?int $waveId, ?int $organizationId, ?Gender $gender): ?Ruleset
    {
        return $this->matching($waveId, $organizationId, $gender)->first();
    }

    /**
     * Every active ruleset whose conditions match an applicant, in the order they are checked. The first one wins.
     *
     * @return Collection<int, Ruleset>
     */
    public function matching(?int $waveId, ?int $organizationId, ?Gender $gender): Collection
    {
        return Ruleset::query()
            ->active()
            ->where(fn (Builder $query) => $query->whereNull('wave_id')->when($waveId, fn (Builder $q) => $q->orWhere('wave_id', $waveId)))
            ->where(fn (Builder $query) => $query->whereNull('organization_id')->when($organizationId, fn (Builder $q) => $q->orWhere('organization_id', $organizationId)))
            ->where(fn (Builder $query) => $query->whereNull('gender')->when($gender, fn (Builder $q) => $q->orWhere('gender', $gender)))
            ->byPriority()
            ->get();
    }
}
