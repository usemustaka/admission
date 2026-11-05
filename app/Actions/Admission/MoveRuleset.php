<?php

namespace App\Actions\Admission;

use App\Models\Ruleset;
use Illuminate\Support\Facades\DB;

class MoveRuleset
{
    /**
     * Move a ruleset one step up (checked earlier) or down (checked later), and renumber the priorities.
     */
    public function handle(Ruleset $ruleset, string $direction): void
    {
        DB::transaction(function () use ($ruleset, $direction): void {
            $ids = Ruleset::query()->byPriority()->get()->map(fn (Ruleset $item): int => $item->id)->values()->all();
            $index = array_search($ruleset->id, $ids, true);

            if ($index === false) {
                return;
            }

            $target = $direction === 'up' ? $index - 1 : $index + 1;

            if (! isset($ids[$target])) {
                return;
            }

            [$ids[$index], $ids[$target]] = [$ids[$target], $ids[$index]];

            foreach ($ids as $position => $id) {
                Ruleset::query()->whereKey($id)->update(['priority' => $position + 1]);
            }
        });
    }
}
