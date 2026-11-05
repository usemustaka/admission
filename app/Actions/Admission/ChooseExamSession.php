<?php

namespace App\Actions\Admission;

use App\Models\ExamSession;
use App\Models\Registrant;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ChooseExamSession
{
    /**
     * Pick the exam session of a registration. The session has to belong to the ruleset the registration was
     * made under, and a full one cannot be chosen; the seats taken are counted under a lock so two parents
     * cannot take the last one together.
     *
     * @throws ValidationException
     */
    public function handle(Registrant $registrant, ExamSession $session): void
    {
        DB::transaction(function () use ($registrant, $session): void {
            $locked = ExamSession::query()->with('day')->lockForUpdate()->findOrFail($session->id);

            if ($registrant->ruleset_id === null || $locked->day->ruleset_id !== $registrant->ruleset_id) {
                throw ValidationException::withMessages(['exam_session_id' => 'Sesi ini bukan untuk pendaftaran ini.']);
            }

            if ($registrant->exam_session_id === $locked->id) {
                return;
            }

            $taken = Registrant::query()->where('exam_session_id', $locked->id)->whereKeyNot($registrant->id)->count();

            if ($taken >= $locked->quota) {
                throw ValidationException::withMessages(['exam_session_id' => 'Sesi ini sudah penuh. Pilih sesi lain ya.']);
            }

            $registrant->update(['exam_session_id' => $locked->id]);
        });
    }
}
