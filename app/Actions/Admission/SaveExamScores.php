<?php

namespace App\Actions\Admission;

use App\Models\Registrant;
use Illuminate\Validation\ValidationException;

class SaveExamScores
{
    /**
     * Save the score of each exam aspect, in the order of the aspects in the ruleset copy on the registration.
     * An empty score means not entered yet.
     *
     * @param  list<int|null>  $scores
     *
     * @throws ValidationException
     */
    public function handle(Registrant $registrant, array $scores): void
    {
        $aspects = $registrant->ruleset_snapshot['exam_aspects'] ?? [];

        if (count($scores) !== count($aspects)) {
            throw ValidationException::withMessages(['scores' => 'Jumlah nilai tidak sama dengan jumlah aspek ujian.']);
        }

        $registrant->update(['exam_scores' => $scores]);
    }
}
