<?php

namespace App\Actions\Academic;

use App\Models\Organization;
use Illuminate\Support\Facades\DB;

class MoveOrganization
{
    /**
     * Move an organization one step up or down the list, and renumber the order so it is always 1, 2, 3 and so on.
     */
    public function handle(Organization $organization, string $direction): void
    {
        DB::transaction(function () use ($organization, $direction): void {
            $ids = Organization::query()->orderBy('sort_order')->orderBy('name')->orderBy('id')->lockForUpdate()->pluck('id')->map(fn (mixed $id): int => (int) $id)->values()->all();
            $index = array_search($organization->id, $ids, true);

            if ($index === false) {
                return;
            }

            $target = $direction === 'up' ? $index - 1 : $index + 1;

            if (isset($ids[$target])) {
                [$ids[$index], $ids[$target]] = [$ids[$target], $ids[$index]];
            }

            foreach ($ids as $position => $id) {
                Organization::query()->whereKey($id)->update(['sort_order' => $position + 1]);
            }
        });
    }
}
