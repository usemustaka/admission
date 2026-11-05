<?php

namespace App\Http\Controllers\Portal;

use App\Actions\Portal\RegisterChild;
use App\Actions\Portal\RegisterChildData;
use App\Enums\Gender;
use App\Enums\ParentKind;
use App\Enums\ParentRelation;
use App\Http\Controllers\Controller;
use App\Http\Requests\Portal\RegisterChildRequest;
use App\Models\Organization;
use App\Models\Parentship;
use App\Models\Registrant;
use App\Models\Wave;
use App\Support\RegistrantPresenter;
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
        ));

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Anak sudah didaftarkan. Lanjutkan dengan melengkapi datanya ya.']);

        return to_route('portal.children.show', $registrant);
    }

    public function show(Registrant $registrant): Response
    {
        Gate::authorize('view', $registrant);

        $registrant->loadMissing(['person.parentships.relatedPerson']);

        return Inertia::render('portal/children/show', [
            'registrant' => RegistrantPresenter::summary($registrant),
            'completeness' => RegistrantPresenter::completeness($registrant),
            'family' => $registrant->person->parentships->map(fn (Parentship $parentship): array => [
                'id' => $parentship->id,
                'name' => $parentship->relatedPerson->name,
                'relation' => $parentship->relation->label(),
            ])->values(),
        ]);
    }
}
