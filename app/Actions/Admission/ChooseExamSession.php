<?php

namespace App\Actions\Admission;

use App\Actions\Notes\WritePersonAuditNote;
use App\Enums\Ability;
use App\Models\ExamDay;
use App\Models\ExamSession;
use App\Models\Registrant;
use App\Models\User;
use App\Support\ExamSchedulePresenter;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ChooseExamSession
{
    public function __construct(private WritePersonAuditNote $writeAuditNote) {}

    /**
     * Pick the exam session of a registration. The session has to be on a day for the registration's organization
     * and wave (or for all), and a full one cannot be chosen; the seats taken are counted under a lock so two parents
     * cannot take the last one together.
     *
     * Committee members (`$by` with the right to manage registrants) can still put a registration in a full
     * session with `$overrideFull`, as long as they give a reason. When `$by` is given, a private note on the
     * child says where the registration moved from and to, and why a full session was overridden.
     *
     * @throws ValidationException
     */
    public function handle(Registrant $registrant, ExamSession $session, ?User $by = null, bool $overrideFull = false, ?string $overrideReason = null): void
    {
        $overrideReason = filled($overrideReason) ? trim((string) $overrideReason) : null;

        DB::transaction(function () use ($registrant, $session, $by, $overrideFull, $overrideReason): void {
            $locked = ExamSession::query()->with('day')->lockForUpdate()->findOrFail($session->id);

            if (! ExamDay::query()->forRegistrant($registrant)->whereKey($locked->exam_day_id)->exists()) {
                throw ValidationException::withMessages(['exam_session_id' => 'Sesi ini bukan untuk pendaftaran ini.']);
            }

            if ($registrant->exam_session_id === $locked->id) {
                return;
            }

            $taken = Registrant::query()->where('exam_session_id', $locked->id)->whereKeyNot($registrant->id)->count();
            $overridden = false;

            if ($taken >= $locked->quota) {
                if (! $overrideFull || $by === null || ! $by->can(Ability::RegistrantManage->value)) {
                    throw ValidationException::withMessages(['exam_session_id' => 'Sesi ini sudah penuh. Pilih sesi lain ya.']);
                }

                if ($overrideReason === null) {
                    throw ValidationException::withMessages(['override_reason' => 'Tulis alasan kenapa sesi yang sudah penuh tetap dipakai.']);
                }

                $overridden = true;
            }

            $previous = $registrant->exam_session_id === null ? null : ExamSession::query()->with('day')->find($registrant->exam_session_id);

            $registrant->update(['exam_session_id' => $locked->id]);

            if ($by !== null) {
                $body = 'Sesi ujian dipindah dari '.($previous === null ? '(belum memilih)' : $this->describe($previous)).' ke '.$this->describe($locked);

                if ($overridden) {
                    $body .= ". Sesi sudah penuh ({$taken} dari {$locked->quota} kursi) dan diizinkan oleh panitia dengan alasan: {$overrideReason}";
                }

                $this->writeAuditNote->handle($registrant->person, $by, $body.'.');
            }
        });
    }

    private function describe(ExamSession $session): string
    {
        return $session->day->date->toDateString().' '.ExamSchedulePresenter::label($session);
    }
}
