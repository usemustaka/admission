<?php

namespace App\Http\Controllers\Portal;

use App\Actions\Portal\RegisterChild;
use App\Actions\Portal\RegisterChildData;
use App\Enums\AdmissionType;
use App\Enums\Gender;
use App\Enums\ParentKind;
use App\Enums\ParentRelation;
use App\Enums\RegistrantStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Portal\RegisterChildRequest;
use App\Models\Organization;
use App\Models\Parentship;
use App\Models\Registrant;
use App\Models\Wave;
use App\Support\ExamResult;
use App\Support\ExamSchedulePresenter;
use App\Support\RegistrantDocuments;
use App\Support\RegistrantPresenter;
use App\Support\RegistrantReadiness;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class ChildController extends Controller
{
    public function create(Request $request): Response
    {
        $gender = $request->user()->person?->gender;

        return Inertia::render('portal/children/create', [
            'waves' => Wave::query()->open()->orderBy('id')->get()->map(fn (Wave $wave): array => ['value' => $wave->id, 'label' => $wave->name])->values(),
            'organizations' => Organization::query()->active()->orderBy('sort_order')->orderBy('name')->get()->map(fn (Organization $organization): array => ['value' => $organization->id, 'label' => $organization->name])->values(),
            'genders' => array_map(fn (Gender $case): array => ['value' => $case->value, 'label' => $case->label()], Gender::cases()),
            'relations' => array_map(fn (ParentRelation $case): array => ['value' => $case->value, 'label' => $case->label()], ParentRelation::cases()),
            'kinds' => array_map(fn (ParentKind $case): array => ['value' => $case->value, 'label' => $case->label()], ParentKind::cases()),
            'admissionTypes' => array_map(fn (AdmissionType $case): array => ['value' => $case->value, 'label' => $case->label()], AdmissionType::cases()),
            'suggestedRelation' => match ($gender) {
                Gender::Male => ParentRelation::Father->value,
                Gender::Female => ParentRelation::Mother->value,
                default => null,
            },
        ]);
    }

    public function store(RegisterChildRequest $request, RegisterChild $registerChild): RedirectResponse
    {
        $registrant = $registerChild->handle($request->user(), new RegisterChildData(
            name: $request->string('name')->toString(),
            nik: $request->string('nik')->toString(),
            gender: $request->enum('gender', Gender::class) ?? Gender::Male,
            birthDate: $request->filled('birth_date') ? $request->string('birth_date')->toString() : null,
            organizationId: $request->integer('organization_id'),
            waveId: $request->integer('wave_id'),
            relation: $request->enum('relation', ParentRelation::class) ?? ParentRelation::Guardian,
            kind: $request->enum('kind', ParentKind::class),
            admissionType: $request->enum('admission_type', AdmissionType::class) ?? AdmissionType::New,
        ));

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Anak sudah didaftarkan. Lanjutkan dengan melengkapi datanya ya.']);

        return to_route('portal.children.show', $registrant);
    }

    public function show(Registrant $registrant): Response
    {
        Gate::authorize('view', $registrant);

        $pending = $registrant->needs_identity_review;

        if (! $pending) {
            $registrant->loadMissing(['person.parentships.relatedPerson']);
        }

        $showResult = in_array($registrant->status, [RegistrantStatus::Announced, RegistrantStatus::Placed], true);

        return Inertia::render('portal/children/show', [
            'registrant' => RegistrantPresenter::summary($registrant),
            'readiness' => $registrant->status->isEditableByParent() ? RegistrantReadiness::missing($registrant) : [],
            'examSession' => ExamSchedulePresenter::chosen($registrant),
            'hasExamSchedule' => $registrant->hasExamSchedule(),
            'result' => $showResult ? ExamResult::for($registrant) : null,
            'timeline' => RegistrantPresenter::timeline($registrant, forParent: true),
            'documents' => $pending ? null : [
                'missing' => RegistrantDocuments::missing($registrant),
                'asked' => RegistrantDocuments::requirements($registrant) !== [],
                'uploaded' => $registrant->documents()->count(),
            ],
            'completeness' => $pending ? ['filled' => 0, 'total' => 0] : RegistrantPresenter::completeness($registrant),
            'family' => $pending ? [] : $registrant->person->parentships->map(fn (Parentship $parentship): array => [
                'id' => $parentship->id,
                'name' => $parentship->relatedPerson->name,
                'relation' => $parentship->relation->label(),
            ])->values(),
        ]);
    }
}
