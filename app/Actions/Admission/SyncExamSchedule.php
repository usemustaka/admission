<?php

namespace App\Actions\Admission;

use App\Models\ExamDay;
use App\Models\ExamSession;
use App\Support\SyncRows;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SyncExamSchedule
{
    /**
     * Make the exam days and sessions of one scope match the submitted schedule. A scope is an organization and a
     * wave, where `null` means "all"; days of other scopes are never touched. The submitted dates are already
     * known to be distinct.
     *
     * @param  list<array{id?: int|null, date: string, sessions: list<array{id?: int|null, label: string, start_time: string, end_time: string, quota: int}>}>  $days
     *
     * @throws ValidationException
     */
    public function handle(?int $organizationId, ?int $waveId, array $days): void
    {
        DB::transaction(function () use ($organizationId, $waveId, $days): void {
            $existingDays = ExamDay::query()->inScope($organizationId, $waveId)->with('sessions')->lockForUpdate()->get()->keyBy('id');

            $sessions = ExamSession::query()
                ->whereIn('exam_day_id', $existingDays->keys())
                ->withCount('registrants')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            $this->guardSessionsInUse($existingDays, $sessions, $days);
            $this->guardQuota($existingDays, $sessions, $days);

            $models = $this->syncDays($organizationId, $waveId, $existingDays, $days);

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
     * Delete the days that were removed, move the others to their new dates, and create the new ones. A date is
     * used once per scope: the schedule is checked again once it is written, so two admins adding the same date
     * at the same time cannot both succeed.
     *
     * @param  Collection<int, ExamDay>  $existingDays
     * @param  list<array{id?: int|null, date: string}>  $days
     * @return array<int, ExamDay> the day of each submitted row, by its index
     *
     * @throws ValidationException
     */
    private function syncDays(?int $organizationId, ?int $waveId, Collection $existingDays, array $days): array
    {
        $models = [];

        foreach ($days as $index => $row) {
            $models[$index] = isset($row['id']) ? $existingDays->get($row['id']) : null;
        }

        $keptIds = array_map(fn (ExamDay $day): int => $day->id, array_filter($models));

        ExamDay::query()->inScope($organizationId, $waveId)->whereNotIn('id', $keptIds)->delete();

        foreach ($models as $index => $day) {
            if ($day !== null && $day->date->toDateString() !== $days[$index]['date']) {
                $day->update(['date' => $days[$index]['date']]);
            }

            $models[$index] ??= ExamDay::query()->create([
                'organization_id' => $organizationId,
                'wave_id' => $waveId,
                'date' => $days[$index]['date'],
            ]);
        }

        $repeated = ExamDay::query()->inScope($organizationId, $waveId)->get()->groupBy(fn (ExamDay $day): string => $day->date->toDateString())->filter(fn ($group): bool => $group->count() > 1);

        if ($repeated->isNotEmpty()) {
            throw ValidationException::withMessages(['days' => 'Tanggal ujian '.$repeated->keys()->implode(', ').' sudah ada di jadwal ini. Muat ulang halaman dan coba lagi.']);
        }

        return $models;
    }
}
