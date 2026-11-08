<?php

namespace App\Support;

use App\Enums\Ability;
use App\Enums\PaymentStatus;
use App\Enums\RegistrantStatus;
use App\Models\ExamSession;
use App\Models\Payment;
use App\Models\Registrant;
use App\Models\RegistrantStatusChange;
use App\Models\User;
use App\Models\Wave;
use Illuminate\Support\Collection;

/**
 * What the committee sees first when it opens the admin panel: the work waiting for it, how registrations are
 * spread over the admission flow, and what is coming up. Every block is limited to what the user may open.
 */
class AdminDashboard
{
    /**
     * @return array<string, mixed>
     */
    public static function for(User $user): array
    {
        $canSeeRegistrants = $user->can(Ability::RegistrantView->value);
        $canSeePayments = $user->can(Ability::PaymentView->value);
        $canSeeWaves = $user->can(Ability::WaveView->value);

        return [
            'attention' => self::attention($canSeeRegistrants, $canSeePayments, $user->can('grantScholarship', Registrant::class)),
            'flow' => $canSeeRegistrants ? self::flow() : null,
            'registrations' => $canSeeRegistrants ? self::registrations() : null,
            'payments' => $canSeePayments ? self::payments() : null,
            'exams' => $canSeeRegistrants ? self::upcomingExams() : [],
            'waves' => $canSeeWaves ? self::openWaves() : [],
            'activity' => $canSeeRegistrants ? self::recentActivity() : [],
        ];
    }

    /**
     * Queues the committee has to work through, most urgent first. An empty queue is still listed so the
     * committee can see it is clear.
     *
     * @return list<array{key: string, label: string, hint: string, count: int, amount: int|null, tone: string}>
     */
    private static function attention(bool $canSeeRegistrants, bool $canSeePayments, bool $canGrantScholarship): array
    {
        $queues = [];

        if ($canSeePayments) {
            $pending = Payment::query()->where('status', PaymentStatus::Pending);

            $queues[] = [
                'key' => 'pending_payments',
                'label' => 'Bukti bayar menunggu verifikasi',
                'hint' => 'Cocokkan dengan mutasi rekening, lalu verifikasi atau tolak.',
                'count' => (clone $pending)->count(),
                'amount' => (int) (clone $pending)->sum('amount'),
                'tone' => 'warning',
            ];
        }

        if ($canSeeRegistrants) {
            $counts = Registrant::query()
                ->selectRaw('status, count(*) as total')
                ->groupBy('status')
                ->pluck('total', 'status');

            $queues[] = [
                'key' => 'in_review',
                'label' => 'Data menunggu diperiksa',
                'hint' => 'Orang tua sudah mengirim, panitia belum menanggapi.',
                'count' => (int) ($counts[RegistrantStatus::Review->value] ?? 0),
                'amount' => null,
                'tone' => 'info',
            ];
            $queues[] = [
                'key' => 'needs_identity_review',
                'label' => 'NIK perlu dicek',
                'hint' => 'Tertahan sampai kecocokan identitas diselesaikan.',
                'count' => Registrant::query()->where('needs_identity_review', true)->count(),
                'amount' => null,
                'tone' => 'danger',
            ];
            $queues[] = [
                'key' => 'to_announce',
                'label' => 'Siap diumumkan',
                'hint' => 'Data sudah valid, tinggal kirim pengumuman.',
                'count' => (int) ($counts[RegistrantStatus::Validated->value] ?? 0),
                'amount' => null,
                'tone' => 'success',
            ];
            $queues[] = [
                'key' => 'to_place',
                'label' => 'Siap ditempatkan',
                'hint' => 'Sudah diumumkan, tinggal masuk tahun ajaran.',
                'count' => (int) ($counts[RegistrantStatus::Announced->value] ?? 0),
                'amount' => null,
                'tone' => 'success',
            ];
        }

        if ($canGrantScholarship) {
            $queues[] = [
                'key' => 'scholarship_candidates',
                'label' => 'Berpotensi mendapat beasiswa',
                'hint' => 'Orang tua melampirkan prestasi, hafalan, atau bukti kurang mampu. Tinjau lalu putuskan.',
                'count' => Registrant::query()->scholarshipCandidates()->count(),
                'amount' => null,
                'tone' => 'info',
            ];
        }

        return $queues;
    }

    /**
     * How many registrations sit at each step of the flow.
     *
     * @return array{total: int, steps: list<array{status: string, label: string, count: int}>}
     */
    private static function flow(): array
    {
        $counts = Registrant::query()
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $steps = collect(RegistrantStatus::cases())
            ->map(fn (RegistrantStatus $status): array => [
                'status' => $status->value,
                'label' => $status->label(),
                'count' => (int) ($counts[$status->value] ?? 0),
            ])
            ->values()
            ->all();

        return ['total' => (int) $counts->sum(), 'steps' => $steps];
    }

    /**
     * New registrations over the last seven days compared with the seven before.
     *
     * @return array{last_week: int, previous_week: int}
     */
    private static function registrations(): array
    {
        return [
            'last_week' => Registrant::query()->where('created_at', '>=', today()->subDays(6))->count(),
            'previous_week' => Registrant::query()
                ->where('created_at', '>=', today()->subDays(13))
                ->where('created_at', '<', today()->subDays(6))
                ->count(),
        ];
    }

    /**
     * @return array{pending_count: int, pending_amount: int, verified_week_amount: int, rejected_week_count: int}
     */
    private static function payments(): array
    {
        $weekStart = today()->subDays(6);

        return [
            'pending_count' => Payment::query()->where('status', PaymentStatus::Pending)->count(),
            'pending_amount' => (int) Payment::query()->where('status', PaymentStatus::Pending)->sum('amount'),
            'verified_week_amount' => (int) Payment::query()
                ->where('status', PaymentStatus::Verified)
                ->where('verified_at', '>=', $weekStart)
                ->sum('amount'),
            'rejected_week_count' => Payment::query()
                ->where('status', PaymentStatus::Rejected)
                ->where('updated_at', '>=', $weekStart)
                ->count(),
        ];
    }

    /**
     * Exam sessions in the next two weeks with how full they are.
     *
     * @return list<array{id: int, date: string, label: string, time: string, taken: int, quota: int}>
     */
    private static function upcomingExams(): array
    {
        return ExamSession::query()
            ->with('day')
            ->withCount('registrants as taken')
            ->whereHas('day', fn ($days) => $days->whereDate('date', '>=', today())->whereDate('date', '<=', today()->addDays(14)))
            ->get()
            ->sortBy(fn (ExamSession $session): string => $session->day->date->toDateString().' '.$session->start_time)
            ->take(6)
            ->map(fn (ExamSession $session): array => [
                'id' => $session->id,
                'date' => $session->day->date->toDateString(),
                'label' => $session->label,
                'time' => substr($session->start_time, 0, 5).'–'.substr($session->end_time, 0, 5),
                'taken' => (int) $session->taken,
                'quota' => $session->quota,
            ])
            ->values()
            ->all();
    }

    /**
     * Waves that accept registrations today.
     *
     * @return list<array{id: int, name: string, ends_on: string|null, days_left: int|null, registrants: int}>
     */
    private static function openWaves(): array
    {
        return Wave::query()
            ->open()
            ->orderBy('starts_on')
            ->get()
            ->map(fn (Wave $wave): array => [
                'id' => $wave->id,
                'name' => $wave->name,
                'ends_on' => $wave->ends_on?->toDateString(),
                'days_left' => $wave->ends_on === null ? null : (int) today()->diffInDays($wave->ends_on, false),
                'registrants' => Registrant::query()->where('wave_id', $wave->id)->count(),
            ])
            ->all();
    }

    /**
     * The latest steps taken in the flow, newest first.
     *
     * @return Collection<int, array{id: int, registrant_id: int, child: string, to_status: string, to_label: string, note: string|null, is_bypass: bool, by: string|null, at: string}>
     */
    private static function recentActivity(): Collection
    {
        return RegistrantStatusChange::query()
            ->with(['registrant.person', 'user.person'])
            ->latest()
            ->limit(8)
            ->get()
            ->map(fn (RegistrantStatusChange $change): array => [
                'id' => $change->id,
                'registrant_id' => $change->registrant_id,
                'child' => $change->registrant->person->name,
                'to_status' => $change->to_status->value,
                'to_label' => $change->to_status->label(),
                'note' => $change->note,
                'is_bypass' => $change->is_bypass,
                'by' => $change->user?->name,
                'at' => $change->created_at?->toIso8601String() ?? now()->toIso8601String(),
            ])
            ->values();
    }
}
