<?php

namespace App\Http\Controllers\Admin\Admission;

use App\Enums\Ability;
use App\Http\Controllers\Controller;
use App\Models\ExamSession;
use App\Models\Organization;
use App\Models\Registrant;
use App\Support\CsvCell;
use App\Support\ExamSchedulePresenter;
use App\Support\ExamSessionStats;
use App\Support\RegistrantBillSummary;
use App\Support\RegistrantPresenter;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExamController extends Controller
{
    private const PAYMENT_LABELS = [
        'none' => 'Tanpa tagihan',
        'unpaid' => 'Belum dibayar',
        'partial' => 'Dibayar sebagian',
        'paid' => 'Lunas',
    ];

    /**
     * The exam sessions of every ruleset by day. By default the sessions from today onward; `tanggal` can be
     * a date (with `sampai` for a range) or `semua`. `perlu_dinilai` keeps the sessions that are today or
     * past and still have registrations without a full set of scores, across all dates unless `tanggal` says
     * where to start. `lembaga` keeps the sessions of rulesets for that organization or for any.
     */
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Registrant::class);

        $today = now()->toDateString();
        $tanggal = $this->date($request->string('tanggal')->toString());
        $sampai = $this->date($request->string('sampai')->toString());
        $everything = $request->string('tanggal')->toString() === 'semua';
        $needsGrading = $request->boolean('perlu_dinilai');
        $organizationId = $request->integer('lembaga') ?: null;

        $from = $tanggal ?? ($everything || $needsGrading ? null : $today);
        $to = $tanggal === null ? null : ($sampai !== null && $sampai >= $tanggal ? $sampai : $tanggal);

        if ($needsGrading && ($to === null || $to > $today)) {
            $to = $today;
        }

        $sessions = ExamSession::query()
            ->select('exam_sessions.*')
            ->join('exam_days', 'exam_days.id', '=', 'exam_sessions.exam_day_id')
            ->with('day.ruleset.organization')
            ->when($from, fn (Builder $query) => $query->whereDate('exam_days.date', '>=', $from))
            ->when($to, fn (Builder $query) => $query->whereDate('exam_days.date', '<=', $to))
            ->when($organizationId, fn (Builder $query) => $query->whereHas('day.ruleset', fn (Builder $ruleset) => $ruleset
                ->where(fn (Builder $any) => $any->where('organization_id', $organizationId)->orWhereNull('organization_id'))))
            ->orderBy('exam_days.date')
            ->orderBy('exam_sessions.start_time')
            ->orderBy('exam_sessions.id')
            ->get();

        $stats = ExamSessionStats::for($sessions->modelKeys());

        $rows = $sessions
            ->map(fn (ExamSession $session): array => [
                'id' => $session->id,
                'date' => $session->day->date->toDateString(),
                'label' => ExamSchedulePresenter::label($session),
                'ruleset' => $session->day->ruleset->name,
                'organization' => $session->day->ruleset->organization?->name,
                'quota' => $session->quota,
                ...$stats[$session->id],
                'needs_grading' => $session->day->date->toDateString() <= $today && $stats[$session->id]['missing'] > 0,
            ])
            ->when($needsGrading, fn ($rows) => $rows->filter(fn (array $row): bool => $row['needs_grading']))
            ->values();

        $user = $request->user();

        return Inertia::render('admin/exams/index', [
            'days' => $rows->groupBy('date')->map(fn ($sessions, string $date): array => ['date' => $date, 'sessions' => $sessions->values()->all()])->values()->all(),
            'summary' => [
                'sessions' => $rows->count(),
                'participants' => $rows->sum('taken'),
                'needs_grading' => $rows->where('needs_grading', true)->count(),
            ],
            'filters' => [
                'tanggal' => $everything ? 'semua' : ($tanggal ?? ''),
                'sampai' => $tanggal !== null && $sampai !== null && $sampai > $tanggal ? $sampai : '',
                'lembaga' => $organizationId === null ? '' : (string) $organizationId,
                'perlu_dinilai' => $needsGrading,
            ],
            'options' => [
                'organizations' => Organization::query()->orderBy('sort_order')->orderBy('name')->get()->map(fn (Organization $organization): array => ['value' => $organization->id, 'label' => $organization->name])->values(),
            ],
            'today' => $today,
            'can' => [
                'manage' => $user->can(Ability::RegistrantManage->value),
            ],
        ]);
    }

    /**
     * The attendance list of a session: who picked it, with the contact of the family and the payment state.
     */
    public function show(Request $request, ExamSession $session): Response
    {
        Gate::authorize('viewAny', Registrant::class);

        $user = $request->user();

        return Inertia::render('admin/exams/session', [
            'session' => ExamSessionStats::describe($session),
            'registrants' => $this->roster($session)->map(function (Registrant $registrant): array {
                $contact = RegistrantPresenter::contact($registrant);

                return [
                    'id' => $registrant->id,
                    'name' => $registrant->person->name,
                    'nik' => $registrant->person->nik,
                    'nisn' => $registrant->person->nisn,
                    'photo_url' => $registrant->person->photo_url,
                    'needs_identity_review' => $registrant->needs_identity_review,
                    'organization' => $registrant->organization->name,
                    'payment' => RegistrantBillSummary::status($registrant->bills),
                    'contact' => $contact === null ? null : ['name' => $contact['name'], 'phone' => $contact['phone'], 'whatsapp_url' => $contact['whatsapp_url']],
                ];
            })->values(),
            'can' => [
                'manage' => $user->can(Ability::RegistrantManage->value),
                'export' => $user->can('export', Registrant::class),
            ],
        ]);
    }

    /**
     * The attendance list as a CSV that Excel opens correctly (UTF-8 BOM), with an empty column to sign.
     */
    public function export(ExamSession $session): StreamedResponse
    {
        Gate::authorize('export', Registrant::class);

        $session->load('day');
        $registrants = $this->roster($session);

        return response()->streamDownload(function () use ($registrants): void {
            $out = fopen('php://output', 'w');

            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['No', 'Nama', 'NIK', 'NISN', 'Lembaga', 'Nama kontak', 'Nomor HP kontak', 'Pembayaran', 'Tanda tangan'], escape: '');

            foreach ($registrants as $number => $registrant) {
                $contact = RegistrantPresenter::contact($registrant);

                fputcsv($out, [
                    $number + 1,
                    CsvCell::safe($registrant->person->name),
                    $this->asText($registrant->person->nik),
                    $this->asText($registrant->person->nisn),
                    CsvCell::safe($registrant->organization->name),
                    CsvCell::safe($contact['name'] ?? ''),
                    $this->asText($contact['phone'] ?? null),
                    self::PAYMENT_LABELS[RegistrantBillSummary::status($registrant->bills)],
                    '',
                ], escape: '');
            }

            fclose($out);
        }, 'daftar-hadir-ujian-'.$session->day->date->toDateString().'-sesi-'.$session->id.'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * @return Collection<int, Registrant>
     */
    private function roster(ExamSession $session): Collection
    {
        return Registrant::query()
            ->select('registrants.*')
            ->join('people', 'people.id', '=', 'registrants.person_id')
            ->where('registrants.exam_session_id', $session->id)
            ->with([
                'person.parentships.relatedPerson',
                'registeredBy',
                'organization',
                'bills.items' => fn ($items) => $items->withPaymentTotals(),
            ])
            ->orderBy('people.name')
            ->orderBy('registrants.id')
            ->get();
    }

    private function date(string $value): ?string
    {
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) !== 1) {
            return null;
        }

        $date = CarbonImmutable::createFromFormat('!Y-m-d', $value);

        return $date !== false && $date->toDateString() === $value ? $value : null;
    }

    /**
     * Excel turns long digit strings (NIK) and phone numbers into numbers and drops leading zeros; written as a
     * text formula it keeps them as typed. Only values made of digits and phone punctuation are written this way,
     * so nothing can be injected.
     */
    private function asText(?string $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        if (preg_match('/^[\d\s+()-]+$/', $value) !== 1) {
            return (string) CsvCell::safe($value);
        }

        return '="'.preg_replace('/\D/', '', $value).'"';
    }
}
