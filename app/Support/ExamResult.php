<?php

namespace App\Support;

use App\Enums\ExamScale;
use App\Models\Registrant;

class ExamResult
{
    /**
     * The exam scores of a registration against the KKM (minimum passing score) of each aspect, which comes
     * from the ruleset copy kept on the registration. `passed` is null until every aspect has a score.
     *
     * @return array{aspects: list<array{name: string, scale: string, passing_score: int, score: int|null, score_label: string|null, passing_label: string, passed: bool|null}>, complete: bool, passed: bool|null}
     */
    public static function for(Registrant $registrant): array
    {
        $scores = $registrant->exam_scores ?? [];
        $aspects = [];

        foreach ($registrant->ruleset_snapshot['exam_aspects'] ?? [] as $index => $aspect) {
            $score = $scores[$index] ?? null;

            $scale = ExamScale::tryFrom($aspect['scale'] ?? '') ?? ExamScale::Number;

            $aspects[] = [
                'name' => $aspect['name'],
                'scale' => $scale->value,
                'passing_score' => $aspect['passing_score'],
                'score' => $score,
                'score_label' => $scale->display($score),
                'passing_label' => (string) $scale->display($aspect['passing_score']),
                'passed' => $score === null ? null : $score >= $aspect['passing_score'],
            ];
        }

        $complete = $aspects !== [] && collect($aspects)->every(fn (array $aspect): bool => $aspect['score'] !== null);

        return [
            'aspects' => $aspects,
            'complete' => $complete,
            'passed' => $complete ? collect($aspects)->every(fn (array $aspect): bool => $aspect['passed'] === true) : null,
        ];
    }
}
