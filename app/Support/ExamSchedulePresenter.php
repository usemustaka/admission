<?php

namespace App\Support;

use App\Models\ExamDay;
use App\Models\ExamSession;
use App\Models\Registrant;

class ExamSchedulePresenter
{
    /**
     * The exam days a registration can pick from (for its organization and wave, or for all), with the seats
     * taken in each session. The shape matches what the session picker expects.
     *
     * @return list<array{date: string, sessions: list<array{id: string, label: string, quota: int, taken: int}>}>
     */
    public static function days(Registrant $registrant): array
    {
        $days = ExamDay::query()->forRegistrant($registrant)->with('sessions')->orderBy('date')->get();

        $taken = Registrant::query()
            ->whereIn('exam_session_id', $days->flatMap(fn (ExamDay $day) => $day->sessions->modelKeys()))
            ->selectRaw('exam_session_id, count(*) as total')
            ->groupBy('exam_session_id')
            ->pluck('total', 'exam_session_id');

        return array_values($days->filter(fn (ExamDay $day): bool => $day->sessions->isNotEmpty())->map(fn (ExamDay $day): array => [
            'date' => $day->date->toDateString(),
            'sessions' => array_values($day->sessions->map(fn (ExamSession $session): array => [
                'id' => (string) $session->id,
                'label' => self::label($session),
                'quota' => $session->quota,
                'taken' => (int) ($taken[$session->id] ?? 0),
            ])->all()),
        ])->all());
    }

    /**
     * What the chosen session reads as: its label and time.
     */
    public static function label(ExamSession $session): string
    {
        return "{$session->label} · ".substr($session->start_time, 0, 5).' s.d. '.substr($session->end_time, 0, 5);
    }

    /**
     * The session a registration picked, as text for the committee and parents.
     *
     * @return array{date: string, label: string}|null
     */
    public static function chosen(Registrant $registrant): ?array
    {
        $session = $registrant->examSession()->with('day')->first();

        return $session === null ? null : ['date' => $session->day->date->toDateString(), 'label' => self::label($session)];
    }
}
