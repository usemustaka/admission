<?php

namespace App\Actions\Admission;

use App\Models\Ruleset;
use App\Support\SyncRows;

class SyncExamAspects
{
    /**
     * @param  list<array{id?: int|null, name: string, scale?: string, passing_score: int}>  $aspects
     */
    public function handle(Ruleset $ruleset, array $aspects): void
    {
        SyncRows::handle($ruleset->examAspects(), $aspects, fn (array $row, int $index): array => [
            'name' => $row['name'],
            'scale' => $row['scale'] ?? 'number',
            'passing_score' => $row['passing_score'],
            'sort_order' => $index,
        ]);
    }
}
