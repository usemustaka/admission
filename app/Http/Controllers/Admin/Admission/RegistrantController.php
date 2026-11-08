<?php

namespace App\Http\Controllers\Admin\Admission;

use App\Actions\Portal\SaveRegistrantData;
use App\Enums\Ability;
use App\Enums\AdmissionType;
use App\Enums\BillType;
use App\Enums\RegistrantStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Admission\SaveRegistrantDataRequest;
use App\Models\Bill;
use App\Models\Note;
use App\Models\Organization;
use App\Models\Parentship;
use App\Models\Payment;
use App\Models\Registrant;
use App\Models\Wave;
use App\Models\Year;
use App\Support\AdmissionLetter;
use App\Support\Announcement;
use App\Support\AnnouncementReadiness;
use App\Support\BillPresenter;
use App\Support\ExamResult;
use App\Support\ExamSchedulePresenter;
use App\Support\ParentAccount;
use App\Support\RegistrantBillSummary;
use App\Support\RegistrantDocuments;
use App\Support\RegistrantListFilters;
use App\Support\RegistrantPresenter;
use App\Support\RegistrantReadiness;
use App\Support\ScholarshipPresenter;
use App\Support\SettlementReceipt;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class RegistrantController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Registrant::class);

        $filters = RegistrantListFilters::fromRequest($request);
        $user = $request->user();

        $registrants = $filters->applySort($filters->apply(
            Registrant::query()
                ->select('registrants.*')
                ->with([
                    'person.parentships.relatedPerson',
                    'registeredBy',
                    'organization',
                    'wave',
                    'examSession.day',
                    'bills.items' => fn ($items) => $items->withPaymentTotals(),
                ]),
        ));

        $statusCounts = $filters->apply(Registrant::query(), includeStatus: false)
            ->selectRaw('registrants.status, count(*) as total')
            ->groupBy('registrants.status')
            ->pluck('total', 'status');

        $overall = Registrant::query()->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');

        return Inertia::render('admin/registrants/index', [
            'registrants' => $registrants->paginate($filters->perPage)->withQueryString()->through(fn (Registrant $registrant): array => [
                'id' => $registrant->id,
                'child' => [
                    'name' => $registrant->person->name,
                    'nik' => $registrant->person->nik,
                    'photo_url' => $registrant->person->photo_url,
                ],
                'organization' => $registrant->organization->name,
                'wave' => $registrant->wave->name,
                'status' => $registrant->status->value,
                'status_label' => $registrant->status->label(),
                'needs_identity_review' => $registrant->needs_identity_review,
                'exam_session' => $registrant->examSession === null ? null : ExamSchedulePresenter::label($registrant->examSession),
                'exam_date' => $registrant->examSession?->day->date->toDateString(),
                'passed' => ExamResult::for($registrant)['passed'],
                'payment' => RegistrantBillSummary::status($registrant->bills),
                'payment_pending' => RegistrantBillSummary::totals($registrant->bills)['pending'],
                'contact' => RegistrantPresenter::contact($registrant),
                'registered_at' => $registrant->created_at?->toIso8601String(),
                'submitted_at' => $registrant->submitted_at?->toIso8601String(),
                'can' => RegistrantPresenter::abilities($registrant, $user),
            ]),
            'filters' => $filters->toArray(),
            'options' => [
                'per_page' => RegistrantListFilters::PER_PAGE_OPTIONS,
                'statuses' => array_map(fn (RegistrantStatus $case): array => ['value' => $case->value, 'label' => $case->label()], RegistrantStatus::cases()),
                'organizations' => Organization::query()->orderBy('sort_order')->orderBy('name')->get()->map(fn (Organization $organization): array => ['value' => $organization->id, 'label' => $organization->name])->values(),
                'waves' => Wave::query()->orderByDesc('id')->get()->map(fn (Wave $wave): array => ['value' => $wave->id, 'label' => $wave->name])->values(),
            ],
            'counts' => [
                'needs_review' => Registrant::query()->where('needs_identity_review', true)->count(),
                'in_review' => (int) ($overall[RegistrantStatus::Review->value] ?? 0),
                'validated' => (int) ($overall[RegistrantStatus::Validated->value] ?? 0),
                'announced' => (int) ($overall[RegistrantStatus::Announced->value] ?? 0),
            ],
            'statusCounts' => [
                'all' => (int) $statusCounts->sum(),
                ...collect(RegistrantStatus::cases())->mapWithKeys(fn (RegistrantStatus $case): array => [$case->value => (int) ($statusCounts[$case->value] ?? 0)])->all(),
            ],
            'can' => [
                'bulk' => ($user->can(Ability::RegistrantManage->value) || $user->can('announce', Registrant::class)) && $user->can('bulk', Registrant::class),
                'export' => $user->can('export', Registrant::class),
                'editAnnouncement' => $user->can(Ability::AnnouncementManage->value),
                'scholarship' => $user->can('grantScholarship', Registrant::class),
            ],
        ]);
    }

    public function show(Request $request, Registrant $registrant): Response
    {
        Gate::authorize('viewAny', Registrant::class);

        $registrant->load(['person.parentships.relatedPerson', 'organization', 'wave', 'registeredBy', 'student.year']);
        $person = $registrant->person;
        $status = $registrant->status;
        $user = $request->user();
        $can = RegistrantPresenter::abilities($registrant, $user);
        $canManage = $can['manage'];
        $canViewNotes = $user->can(Ability::NoteView->value);
        $canCreateNote = $user->can('create', Note::class);

        $filters = RegistrantListFilters::fromRequest($request);
        $followListSort = $request->hasAny(['urut', 'arah']);
        $listQuery = array_filter(
            $request->only(['cari', 'status', 'lembaga', 'gelombang', 'perlu_dicek', 'bayar', 'belum_ujian', 'urut', 'arah']),
            fn (mixed $value): bool => is_scalar($value) && $value !== '',
        );

        $bills = $registrant->bills()->with(['items' => fn ($items) => $items->withPaymentTotals()])->get();
        $hasSettlement = $registrant->hasBill(BillType::Settlement);
        $settlementPaid = $bills->first(fn (Bill $bill): bool => $bill->type === BillType::Settlement)?->status()->value === 'paid';

        return Inertia::render('admin/registrants/show', [
            'registrant' => [
                'id' => $registrant->id,
                'status' => $status->value,
                'status_label' => $status->label(),
                'needs_identity_review' => $registrant->needs_identity_review,
                'submitted_name' => $registrant->submitted_name,
                'registered_by' => $registrant->registeredBy->name,
                'registered_as' => $registrant->registered_as_relation->label(),
                'organization' => $registrant->organization->name,
                'wave' => $registrant->wave->name,
                'registered_at' => $registrant->created_at?->toDateString(),
                'submitted_at' => $registrant->submitted_at?->toDateString(),
                'ruleset' => $registrant->ruleset_snapshot['name'] ?? null,
                'child' => [
                    'id' => $person->id,
                    'name' => $person->name,
                    'nik' => $person->nik,
                    'photo_url' => RegistrantPresenter::childPhotoUrl($registrant),
                ],
            ],
            'data' => RegistrantPresenter::detail($registrant),
            'family' => $registrant->needs_identity_review ? [] : $person->parentships->map(fn (Parentship $parentship): array => [
                'id' => $parentship->id,
                'person_id' => $parentship->related_person_id,
                'name' => $parentship->relatedPerson->name,
                'relation' => $parentship->relation->label(),
                'phone' => $parentship->relatedPerson->phone,
                'is_primary_contact' => $parentship->is_primary_contact,
            ])->values(),
            'contact' => RegistrantPresenter::contact($registrant),
            'messages' => RegistrantPresenter::messages($registrant),
            'examSession' => ExamSchedulePresenter::chosen($registrant),
            'examDays' => $canManage ? ExamSchedulePresenter::days($registrant) : [],
            'selectedSession' => $registrant->exam_session_id === null ? null : (string) $registrant->exam_session_id,
            'bills' => $bills->map(fn (Bill $bill): array => BillPresenter::bill($bill))->values(),
            'announceWarnings' => $can['announce'] ? AnnouncementReadiness::for($registrant)['messages'] : [],
            'result' => ExamResult::for($registrant),
            'scores' => collect($registrant->ruleset_snapshot['exam_aspects'] ?? [])->keys()->map(fn (int $index): ?int => $registrant->exam_scores[$index] ?? null)->values(),
            'readiness' => RegistrantReadiness::forCommittee($registrant),
            'timeline' => RegistrantPresenter::timeline($registrant, forParent: false),
            'announcement' => ! $registrant->needs_identity_review && in_array($status, [RegistrantStatus::Validated, RegistrantStatus::Announced, RegistrantStatus::Placed], true) ? [
                'text' => Announcement::text($registrant),
                'recipient' => Announcement::recipient($registrant),
                'whatsapp_url' => Announcement::whatsappUrl($registrant),
            ] : null,
            'student' => $registrant->student === null ? null : [
                'id' => $registrant->student->id,
                'nis' => $registrant->student->nis,
                'year' => $registrant->student->year?->name,
            ],
            'years' => Year::query()->orderByDesc('starts_on')->get()->map(fn (Year $year): array => ['value' => $year->id, 'label' => $year->name, 'is_active' => $year->is_active])->values(),
            'statuses' => collect(RegistrantStatus::cases())
                ->reject(fn (RegistrantStatus $case): bool => $case === RegistrantStatus::Placed
                    || $case === $status
                    || ($registrant->needs_identity_review && in_array($case, [RegistrantStatus::Validated, RegistrantStatus::Announced], true)))
                ->map(fn (RegistrantStatus $case): array => ['value' => $case->value, 'label' => $case->label()])
                ->values(),
            'settlement' => ['has_bill' => $hasSettlement, 'paid' => $settlementPaid],
            'can' => $can,
            'prints' => [
                'letter' => $user->can('announce', Registrant::class) && AdmissionLetter::canPrint($registrant),
                'settlement_receipt' => $user->can('viewAny', Payment::class) && SettlementReceipt::isAvailable($registrant),
            ],
            'documents' => $registrant->needs_identity_review ? null : RegistrantDocuments::forCommittee($registrant),
            'scholarship' => $user->can('grantScholarship', Registrant::class) && ! $registrant->needs_identity_review ? ScholarshipPresenter::for($registrant) : null,
            'parentAccount' => $registrant->needs_identity_review || ($account = ParentAccount::for($registrant)) === null
                ? null
                : [
                    'email' => $account->email,
                    'verified' => $account->hasVerifiedEmail(),
                    'can_reset' => $user->can(Ability::ParentPasswordReset->value),
                ],
            'payments' => $user->can(Ability::PaymentView->value) ? RegistrantPresenter::payments($registrant) : [],
            'access' => [
                'person' => $user->can(Ability::PersonView->value),
                'payments' => $user->can(Ability::PaymentView->value),
                'notes' => $canViewNotes,
                'createNote' => $canCreateNote,
            ],
            'notes' => $canViewNotes ? RegistrantPresenter::notes($person, $user) : [],
            'noteOptions' => $canCreateNote ? RegistrantPresenter::noteOptions($user) : null,
            'queue' => $this->queue($registrant, $filters, $followListSort),
            'queueContext' => [
                'query' => $listQuery === [] ? (object) [] : $listQuery,
                'order' => $this->queueOrderLabel($filters, $followListSort),
            ],
        ]);
    }

    /**
     * Where a registration sits among the others with the same status, so the committee can work through a
     * status one after the other. Registrations that wait for the NIK check are not in the queue, since nothing
     * can be done with them yet. The queue follows the filters of the list it was opened from; the order is the
     * list's own when the link carries one, and oldest registration first otherwise.
     *
     * @return array{position: int, total: int, previous_id: int|null, next_id: int|null}
     */
    private function queue(Registrant $registrant, RegistrantListFilters $filters, bool $followListSort): array
    {
        $registrant->loadMissing('person');

        $byName = $followListSort && $filters->sort === 'nama';
        $direction = $followListSort ? $filters->direction : 'asc';

        /** @var list<array{0: string, 1: string, 2: mixed}> $order */
        $order = $byName
            ? [['people.name', $direction, $registrant->person->name], ['registrants.id', 'asc', $registrant->id]]
            : [['registrants.created_at', $direction, $registrant->created_at], ['registrants.id', $direction, $registrant->id]];

        $queue = fn (): Builder => $filters
            ->apply(
                Registrant::query()
                    ->select('registrants.*')
                    ->where('registrants.status', $registrant->status)
                    ->where('registrants.needs_identity_review', false),
                includeStatus: false,
            )
            ->when($byName, fn (Builder $query) => $query->join('people', 'people.id', '=', 'registrants.person_id'));

        $comes = fn (Builder $query, bool $after): Builder => $query->where(function (Builder $query) use ($order, $after): void {
            foreach ($order as $index => [$column, $columnDirection, $value]) {
                $operator = ($columnDirection === 'asc') === $after ? '>' : '<';

                $query->orWhere(function (Builder $query) use ($order, $index, $column, $operator, $value): void {
                    foreach (array_slice($order, 0, $index) as [$earlierColumn, , $earlierValue]) {
                        $query->where($earlierColumn, $earlierValue);
                    }

                    $query->where($column, $operator, $value);
                });
            }
        });

        $sorted = function (Builder $query, bool $reverse) use ($order): Builder {
            foreach ($order as [$column, $columnDirection]) {
                $query->orderBy($column, $reverse === ($columnDirection === 'asc') ? 'desc' : 'asc');
            }

            return $query;
        };

        return [
            'position' => $comes($queue(), false)->count() + 1,
            'total' => $queue()->count(),
            'previous_id' => $sorted($comes($queue(), false), true)->value('registrants.id'),
            'next_id' => $sorted($comes($queue(), true), false)->value('registrants.id'),
        ];
    }

    private function queueOrderLabel(RegistrantListFilters $filters, bool $followListSort): string
    {
        if (! $followListSort) {
            return 'dari yang paling lama mendaftar';
        }

        return match (true) {
            $filters->sort === 'nama' && $filters->direction === 'asc' => 'menurut nama, A ke Z',
            $filters->sort === 'nama' => 'menurut nama, Z ke A',
            $filters->direction === 'asc' => 'dari yang paling lama mendaftar',
            default => 'dari yang paling baru mendaftar',
        };
    }

    public function edit(Registrant $registrant): Response
    {
        Gate::authorize('manage', $registrant);

        return Inertia::render('admin/registrants/edit', [
            'registrant' => ['id' => $registrant->id, 'child_name' => $registrant->person->name, 'person_id' => $registrant->person_id],
            ...RegistrantPresenter::dataForm($registrant),
        ]);
    }

    public function update(SaveRegistrantDataRequest $request, Registrant $registrant, SaveRegistrantData $saveRegistrantData): RedirectResponse
    {
        /** @var array<string, mixed> $child */
        $child = $request->validated('child') ?? [];
        /** @var array<string, array<string, mixed>> $parents */
        $parents = $request->validated('parents') ?? [];

        $saveRegistrantData->handle(
            $registrant,
            $child,
            $parents,
            $request->file('photo'),
            $request->boolean('remove_photo'),
            auditedBy: $request->user(),
            education: $request->validated('education'),
            admissionType: $request->enum('admission_type', AdmissionType::class),
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Data pendaftar sudah disimpan.']);

        return to_route('admin.registrants.data.edit', $registrant);
    }
}
