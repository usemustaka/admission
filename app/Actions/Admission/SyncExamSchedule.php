<?php

namespace App\Actions\Admission;

use App\Models\ExamSession;
use App\Models\Registrant;
use App\Models\Ruleset;
use App\Support\SyncRows;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SyncExamSchedule
{
    /**
     * @param  list<array{id?: int|null, date: string, sessions: list<array{id?: int|null, label: string, start_time: string, end_time: string, quota: int}>}>  $days
     */
    public function handle(Ruleset $ruleset, array $days): void
    {
        $this->guardSessionsInUse($ruleset, $days);

        DB::transaction(function () use ($ruleset, $days): void {
            $models = SyncRows::handle($ruleset->examDays(), $days, fn (array $row): array => ['date' => $row['date']]);

            foreach ($models as $index => $day) {
                SyncRows::handle($day->sessions(), $days[$index]['sessions'] ?? [], fn (array $row): array => [
                    'label' => $row['label'],
                    'start_time' => $row['start_time'].':00',
                    'end_time' => $row['end_time'].':00',
                    'quota' => $row['quota'],
                ]);
            }
        });
    }

    /**
     * Sessions that applicants already picked cannot be removed, neither directly nor by removing their day.
     *
     * @param  list<array{id?: int|null, date: string, sessions: list<array{id?: int|null}>}>  $days
     *
     * @throws ValidationException
     */
    private function guardSessionsInUse(Ruleset $ruleset, array $days): void
    {
        $kept = collect($days)->flatMap(fn (array $day): array => $day['sessions'])->pluck('id')->filter()->all();

        $inUse = ExamSession::query()
            ->whereHas('day', fn ($days) => $days->where('ruleset_id', $ruleset->id))
            ->whereNotIn('id', $kept)
            ->whereIn('id', Registrant::query()->whereNotNull('exam_session_id')->select('exam_session_id'))
            ->with('day')
            ->get();

        if ($inUse->isNotEmpty()) {
            $names = $inUse->map(fn (ExamSession $session): string => "{$session->label} ({$session->day->date->translatedFormat('j M Y')})")->implode(', ');

            throw ValidationException::withMessages(['days' => "Sesi {$names} sudah dipilih pendaftar, jadi belum bisa dihapus."]);
        }
    }
}
