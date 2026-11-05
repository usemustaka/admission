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

        $term = $request->string('cari')->toString();
        $status = $request->enum('status', RegistrantStatus::class);
        $sort = $request->string('urut')->toString() === 'nama' ? 'nama' : 'terbaru';
        $direction = match ($request->string('arah')->toString()) {
            'asc' => 'asc',
            'desc' => 'desc',
            default => $sort === 'nama' ? 'asc' : 'desc',
        };

        $registrants = Registrant::query()
            ->select('registrants.*')
            ->with(['person', 'organization', 'wave', 'examSession.day'])
            ->when($term !== '', fn (Builder $query) => $query->whereHas('person', fn (Builder $people) => $people->search($term)))
            ->when($status, fn (Builder $query) => $query->where('registrants.status', $status))
            ->when($request->integer('lembaga') > 0, fn (Builder $query) => $query->where('registrants.organization_id', $request->integer('lembaga')))
            ->when($request->integer('gelombang') > 0, fn (Builder $query) => $query->where('registrants.wave_id', $request->integer('gelombang')))
            ->when($request->boolean('perlu_dicek'), fn (Builder $query) => $query->where('registrants.needs_identity_review', true));

        if ($sort === 'nama') {
            $registrants->join('people', 'people.id', '=', 'registrants.person_id')->orderBy('people.name', $direction);
        } else {
            $registrants->orderBy('registrants.created_at', $direction)->orderBy('registrants.id', $direction);
        }

        return Inertia::render('admin/registrants/index', [
            'registrants' => $registrants->paginate(15)->withQueryString()->through(fn (Registrant $registrant): array => [
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
                'registered_at' => $registrant->created_at?->toDateString(),
            ]),
            'filters' => [
                'cari' => $term,
                'status' => $request->string('status')->toString(),
                'lembaga' => $request->integer('lembaga') ?: '',
                'gelombang' => $request->integer('gelombang') ?: '',
                'perlu_dicek' => $request->boolean('perlu_dicek'),
                'urut' => $sort,
                'arah' => $direction,
            ],
            'options' => [
                'statuses' => array_map(fn (RegistrantStatus $case): array => ['value' => $case->value, 'label' => $case->label()], RegistrantStatus::cases()),
                'organizations' => Organization::query()->orderBy('sort_order')->orderBy('name')->get()->map(fn (Organization $organization): array => ['value' => $organization->id, 'label' => $organization->name])->values(),
                'waves' => Wave::query()->orderByDesc('id')->get()->map(fn (Wave $wave): array => ['value' => $wave->id, 'label' => $wave->name])->values(),
            ],
            'counts' => [
                'needs_review' => Registrant::query()->where('needs_identity_review', true)->count(),
                'in_review' => Registrant::query()->where('status', RegistrantStatus::Review)->count(),
            ],
        ]);
    }

    public function show(Request $request, Registrant $registrant): Response
    {
        Gate::authorize('viewAny', Registrant::class);

        $registrant->load(['person.parentships.relatedPerson', 'organization', 'wave', 'registeredBy', 'student.year']);
        $person = $registrant->person;
        $status = $registrant->status;
        $canManage = $request->user()->can(Ability::RegistrantManage->value);

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
                'formal_photo_url' => $registrant->formal_photo_url,
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
            'can' => [
                'manage' => $canManage,
                'accept' => $canManage && $status === RegistrantStatus::Review && ! $registrant->needs_identity_review,
                'revise' => $canManage && $status === RegistrantStatus::Review,
                'announce' => $canManage && $status === RegistrantStatus::Validated && ! $registrant->needs_identity_review,
                'place' => $canManage && $status === RegistrantStatus::Announced && ! $registrant->needs_identity_review,
                'bypass' => $canManage && $status !== RegistrantStatus::Placed,
            ],
        ]);
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
            $request->file('formal_photo'),
            $request->boolean('remove_formal_photo'),
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Data pendaftar sudah disimpan.']);

        return to_route('admin.registrants.data.edit', $registrant);
    }
}
