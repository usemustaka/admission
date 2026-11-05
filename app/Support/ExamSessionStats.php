<?php

namespace App\Support;

use App\Models\ExamSession;
use App\Models\Registrant;
use Illuminate\Support\Collection;

class ExamSessionStats
{
    /**
     * How many registrations picked each session, how many of them still lack scores, and how many passed or
     * did not. A registration whose ruleset has no exam aspects has nothing to score, so it counts as neither.
     *
     * @param  Collection<int, int>|list<int>  $sessionIds
     * @return array<int, array{taken: int, missing: int, passed: int, failed: int}>
     */
    public static function for(Collection|array $sessionIds): array
    {
        $stats = [];

        foreach ($sessionIds as $id) {
            $stats[$id] = ['taken' => 0, 'missing' => 0, 'passed' => 0, 'failed' => 0];
        }

        Registrant::query()
            ->whereIn('exam_session_id', array_keys($stats))
            ->select(['id', 'exam_session_id', 'exam_scores', 'ruleset_snapshot'])
            ->lazy(200)
            ->each(function (Registrant $registrant) use (&$stats): void {
                $id = (int) $registrant->exam_session_id;
                $result = ExamResult::for($registrant);

                $stats[$id]['taken']++;

                if ($result['aspects'] === []) {
                    return;
                }

                match ($result['passed']) {
                    true => $stats[$id]['passed']++,
                    false => $stats[$id]['failed']++,
                    null => $stats[$id]['missing']++,
                };
            });

        return $stats;
    }

    /**
     * A session as the exam pages show it: when, for whom (organization and wave), the quota and the counts.
     *
     * @return array{id: int, date: string, label: string, organization: string|null, wave: string|null, quota: int, taken: int, missing: int, passed: int, failed: int}
     */
    public static function describe(ExamSession $session): array
    {
        $session->loadMissing('day.organization', 'day.wave');

        return [
            'id' => $session->id,
            'date' => $session->day->date->toDateString(),
            'label' => ExamSchedulePresenter::label($session),
            'organization' => $session->day->organization?->name,
            'wave' => $session->day->wave?->name,
            'quota' => $session->quota,
            ...self::for([$session->id])[$session->id],
        ];
    }
}
