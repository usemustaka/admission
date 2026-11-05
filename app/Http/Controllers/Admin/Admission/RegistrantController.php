<?php

namespace App\Http\Controllers\Admin\Admission;

use App\Actions\Portal\SaveRegistrantData;
use App\Enums\Ability;
use App\Enums\BillType;
use App\Enums\RegistrantStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Admission\SaveRegistrantDataRequest;
use App\Models\Bill;
use App\Models\Organization;
use App\Models\Parentship;
use App\Models\Registrant;
use App\Models\Wave;
use App\Models\Year;
use App\Support\Announcement;
use App\Support\BillPresenter;
use App\Support\ExamResult;
use App\Support\ExamSchedulePresenter;
use App\Support\RegistrantBillSummary;
use App\Support\RegistrantListFilters;
use App\Support\RegistrantPresenter;
use App\Support\RegistrantReadiness;
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
            'registrants' => $registrants->paginate(25)->withQueryString()->through(fn (Registrant $registrant): array => [
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
                'contact' => RegistrantPresenter::contact($registrant),
                'registered_at' => $registrant->created_at?->toIso8601String(),
                'can' => RegistrantPresenter::abilities($registrant, $user),
            ]),
            'filters' => $filters->toArray(),
            'options' => [
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
            'canManage' => $user->can(Ability::RegistrantManage->value),
        ]);
    }

    public function show(Request $request, Registrant $registrant): Response
    {
        Gate::authorize('viewAny', Registrant::class);

        $registrant->load(['person.parentships.relatedPerson', 'organization', 'wave', 'registeredBy', 'student.year']);
        $person = $registrant->person;
        $status = $registrant->status;
        $can = RegistrantPresenter::abilities($registrant, $request->user());
        $canManage = $can['manage'];

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
                'child' => [
                    'id' => $person->id,
                    'name' => $person->name,
                    'nik' => $person->nik,
                    'gender' => $person->gender?->label(),
                    'birth' => collect([$person->birth_place, $person->birth_date?->translatedFormat('j F Y')])->filter()->implode(', ') ?: null,
                    'religion' => $person->religion,
                    'address' => $person->address,
                    'family_card_number' => $person->family_card_number,
                    'photo_url' => $person->photo_url,
                ],
            ],
            'family' => $person->parentships->map(fn (Parentship $parentship): array => [
                'id' => $parentship->id,
                'person_id' => $parentship->related_person_id,
                'name' => $parentship->relatedPerson->name,
                'relation' => $parentship->relation->label(),
                'phone' => $parentship->relatedPerson->phone,
                'is_primary_contact' => $parentship->is_primary_contact,
            ])->values(),
            'examSession' => ExamSchedulePresenter::chosen($registrant),
            'examDays' => $canManage ? ExamSchedulePresenter::days($registrant) : [],
            'selectedSession' => $registrant->exam_session_id === null ? null : (string) $registrant->exam_session_id,
            'bills' => $bills->map(fn (Bill $bill): array => BillPresenter::bill($bill))->values(),
            'result' => ExamResult::for($registrant),
            'scores' => collect($registrant->ruleset_snapshot['exam_aspects'] ?? [])->keys()->map(fn (int $index): ?int => $registrant->exam_scores[$index] ?? null)->values(),
            'readiness' => $status->isEditableByParent() ? RegistrantReadiness::missing($registrant) : [],
            'timeline' => RegistrantPresenter::timeline($registrant, forParent: false),
            'announcement' => in_array($status, [RegistrantStatus::Validated, RegistrantStatus::Announced, RegistrantStatus::Placed], true) ? [
                'text' => Announcement::text($registrant),
                'recipient' => Announcement::recipient($registrant),
                'whatsapp_url' => Announcement::whatsappUrl($registrant),
            ] : null,
            'student' => $registrant->student === null ? null : [
                'nis' => $registrant->student->nis,
                'year' => $registrant->student->year?->name,
            ],
            'years' => Year::query()->orderByDesc('starts_on')->get()->map(fn (Year $year): array => ['value' => $year->id, 'label' => $year->name, 'is_active' => $year->is_active])->values(),
            'statuses' => collect(RegistrantStatus::cases())
                ->reject(fn (RegistrantStatus $case): bool => $case === RegistrantStatus::Placed || $case === $status)
                ->map(fn (RegistrantStatus $case): array => ['value' => $case->value, 'label' => $case->label()])
                ->values(),
            'settlement' => ['has_bill' => $hasSettlement, 'paid' => $settlementPaid],
            'can' => $can,
            'queue' => $this->queue($registrant),
        ]);
    }

    /**
     * Where a registration sits among the others with the same status, oldest registration first, so the
     * committee can work through a status one after the other.
     *
     * @return array{position: int, total: int, previous_id: int|null, next_id: int|null}
     */
    private function queue(Registrant $registrant): array
    {
        $sameStatus = fn (): Builder => Registrant::query()->where('status', $registrant->status);
        $before = fn (Builder $query): Builder => $query->where(fn (Builder $query) => $query
            ->where('created_at', '<', $registrant->created_at)
            ->orWhere(fn (Builder $query) => $query->where('created_at', $registrant->created_at)->where('id', '<', $registrant->id)));
        $after = fn (Builder $query): Builder => $query->where(fn (Builder $query) => $query
            ->where('created_at', '>', $registrant->created_at)
            ->orWhere(fn (Builder $query) => $query->where('created_at', $registrant->created_at)->where('id', '>', $registrant->id)));

        return [
            'position' => $before($sameStatus())->count() + 1,
            'total' => $sameStatus()->count(),
            'previous_id' => $before($sameStatus())->orderByDesc('created_at')->orderByDesc('id')->value('id'),
            'next_id' => $after($sameStatus())->orderBy('created_at')->orderBy('id')->value('id'),
        ];
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
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Data pendaftar sudah disimpan.']);

        return to_route('admin.registrants.data.edit', $registrant);
    }
}
