<?php

namespace App\Actions\Admission;

use App\Models\ExamSession;
use App\Models\Registrant;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SaveSessionScores
{
    public function __construct(private SaveExamScores $saveExamScores) {}

    /**
     * Save the scores of many registrations of one exam session. Each row is its own transaction under a row
     * lock, so a row that fails (a score out of range, a registration that moved to another session, an
     * announced result that was not confirmed) does not hold back the others.
     *
     * @param  array<int|string, mixed>  $rows  registration id => aspect position => score
     * @return array{saved: int, unchanged: int, errors: array<int, string>} errors are keyed by registration id
     */
    public function handle(ExamSession $session, array $rows, User $by, bool $confirmAfterAnnounce = false): array
    {
        $saved = 0;
        $unchanged = 0;
        $errors = [];

        foreach ($rows as $registrantId => $scores) {
            try {
                $changed = DB::transaction(function () use ($session, $registrantId, $scores, $by, $confirmAfterAnnounce): bool {
                    $registrant = Registrant::query()
                        ->where('exam_session_id', $session->id)
                        ->lockForUpdate()
                        ->find((int) $registrantId);

                    if ($registrant === null) {
                        throw ValidationException::withMessages(['row' => 'Pendaftar ini sudah tidak ada di sesi ini.']);
                    }

                    if (! is_array($scores)) {
                        throw ValidationException::withMessages(['row' => 'Isian baris ini tidak terbaca.']);
                    }

                    return $this->saveExamScores->handle($registrant, $scores, $by, $confirmAfterAnnounce) !== [];
                });

                $changed ? $saved++ : $unchanged++;
            } catch (ValidationException $exception) {
                $errors[(int) $registrantId] = (string) collect($exception->errors())->flatten()->first();
            }
        }

        return ['saved' => $saved, 'unchanged' => $unchanged, 'errors' => $errors];
    }
}
