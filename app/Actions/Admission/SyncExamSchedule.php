<?php

namespace App\Actions\Admission;

use App\Models\ExamDay;
use App\Models\ExamSession;
use App\Models\Ruleset;
use App\Support\SyncRows;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SyncExamSchedule
{
    /**
     * Make the exam days and sessions of a ruleset match the submitted schedule. The submitted dates are already
     * known to be distinct, but days may take over each other's dates, so the dates are changed in steps.
     *
     * @param  list<array{id?: int|null, date: string, sessions: list<array{id?: int|null, label: string, start_time: string, end_time: string, quota: int}>}>  $days
     *
     * @throws ValidationException
     */
    public function handle(Ruleset $ruleset, array $days): void
    {
        DB::transaction(function () use ($ruleset, $days): void {
            $existingDays = $ruleset->examDays()->with('sessions')->get()->keyBy('id');

            $sessions = ExamSession::query()
                ->whereIn('exam_day_id', $existingDays->keys())
                ->withCount('registrants')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            $this->guardSessionsInUse($existingDays, $sessions, $days);
            $this->guardQuota($existingDays, $sessions, $days);

            $models = $this->syncDays($ruleset, $existingDays, $days);

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
     * Sessions that applicants already picked cannot be removed, neither directly, by removing their day, nor by
     * placing them under another day (that would make a new session and drop the old one).
     *
     * @param  Collection<int, ExamDay>  $existingDays
     * @param  Collection<int, ExamSession>  $sessions
     * @param  list<array{id?: int|null, date: string, sessions: list<array{id?: int|null}>}>  $days
     *
     * @throws ValidationException
     */
    private function guardSessionsInUse(Collection $existingDays, Collection $sessions, array $days): void
    {
        $kept = $this->keptSessionIds($existingDays, $days);

        $inUse = $sessions->filter(fn (ExamSession $session): bool => $session->registrants_count > 0 && ! isset($kept[$session->id]));

        if ($inUse->isNotEmpty()) {
            $names = $inUse->map(fn (ExamSession $session): string => "{$session->label} ({$existingDays->get($session->exam_day_id)->date->translatedFormat('j M Y')})")->implode(', ');

            throw ValidationException::withMessages(['days' => "Sesi {$names} sudah dipilih pendaftar, jadi tidak bisa dihapus atau dipindahkan ke tanggal lain."]);
        }
    }

    /**
     * A session cannot have fewer seats than the registrants who already picked it.
     *
     * @param  Collection<int, ExamDay>  $existingDays
     * @param  Collection<int, ExamSession>  $sessions
     * @param  list<array{id?: int|null, date: string, sessions: list<array{id?: int|null, quota: int}>}>  $days
     *
     * @throws ValidationException
     */
    private function guardQuota(Collection $existingDays, Collection $sessions, array $days): void
    {
        $errors = [];

        foreach ($days as $dayIndex => $day) {
            foreach ($day['sessions'] as $sessionIndex => $row) {
                $session = isset($row['id']) ? $sessions->get($row['id']) : null;

                if ($session === null || $session->exam_day_id !== (int) ($day['id'] ?? 0) || ! $existingDays->has($session->exam_day_id)) {
                    continue;
                }

                if ((int) $row['quota'] < $session->registrants_count) {
                    $errors["days.{$dayIndex}.sessions.{$sessionIndex}.quota"] = "Kuota tidak boleh di bawah {$session->registrants_count}, jumlah pendaftar yang sudah memilih sesi ini.";
                }
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }

    /**
     * The ids of the existing sessions that survive the sync: only those submitted under their own day.
     *
     * @param  Collection<int, ExamDay>  $existingDays
     * @param  list<array{id?: int|null, sessions: list<array{id?: int|null}>}>  $days
     * @return array<int, true>
     */
    private function keptSessionIds(Collection $existingDays, array $days): array
    {
        $kept = [];

        foreach ($days as $day) {
            $existingDay = isset($day['id']) ? $existingDays->get($day['id']) : null;

            if ($existingDay === null) {
                continue;
            }

            $own = $existingDay->sessions->modelKeys();

            foreach ($day['sessions'] as $row) {
                if (isset($row['id']) && in_array((int) $row['id'], $own, true)) {
                    $kept[(int) $row['id']] = true;
                }
            }
        }

        return $kept;
    }

    /**
     * Delete the days that were removed, move the others to their new dates through temporary dates (so days can
     * swap or shift into each other's dates without hitting the unique date per ruleset), and create the new ones.
     *
     * @param  Collection<int, ExamDay>  $existingDays
     * @param  list<array{id?: int|null, date: string}>  $days
     * @return array<int, ExamDay> the day of each submitted row, by its index
     */
    private function syncDays(Ruleset $ruleset, Collection $existingDays, array $days): array
    {
        $models = [];

        foreach ($days as $index => $row) {
            $models[$index] = isset($row['id']) ? $existingDays->get($row['id']) : null;
        }

        $keptIds = array_map(fn (ExamDay $day): int => $day->id, array_filter($models));

        ExamDay::query()->where('ruleset_id', $ruleset->id)->whereNotIn('id', $keptIds)->delete();

        $moving = [];

        foreach ($models as $index => $day) {
            if ($day !== null && $day->date->toDateString() !== $days[$index]['date']) {
                $moving[$index] = $day;
            }
        }

        $used = array_flip(array_merge(
            $existingDays->map(fn (ExamDay $day): string => $day->date->toDateString())->all(),
            array_column($days, 'date'),
        ));
        $placeholder = Carbon::create(1000, 1, 1);

        foreach ($moving as $day) {
            while (isset($used[$placeholder->toDateString()])) {
                $placeholder->addDay();
            }

            $day->update(['date' => $placeholder->toDateString()]);
            $placeholder->addDay();
        }

        foreach ($moving as $index => $day) {
            $day->update(['date' => $days[$index]['date']]);
        }

        foreach ($models as $index => $day) {
            $models[$index] ??= $ruleset->examDays()->create(['date' => $days[$index]['date']]);
        }

        return $models;
    }
}
