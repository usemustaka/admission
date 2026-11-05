<?php

namespace App\Http\Controllers\Portal;

use App\Actions\Portal\SaveRegistrantData;
use App\Enums\Gender;
use App\Enums\ParentKind;
use App\Enums\ParentRelation;
use App\Http\Controllers\Controller;
use App\Http\Requests\Portal\SaveRegistrantDataRequest;
use App\Models\Parentship;
use App\Models\Person;
use App\Models\Registrant;
use App\Support\PersonOptions;
use App\Support\RegistrantPresenter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class ChildDataController extends Controller
{
    public function edit(Registrant $registrant): Response
    {
        Gate::authorize('view', $registrant);

        $registrant->loadMissing(['person.parentships.relatedPerson']);
        $child = $registrant->person;

        return Inertia::render('portal/children/data', [
            'registrant' => RegistrantPresenter::summary($registrant),
            'child' => [
                'name' => $child->name,
                'nik' => $child->nik,
                'gender' => $child->gender?->value,
                'birth_place' => $child->birth_place,
                'birth_date' => $child->birth_date?->toDateString(),
                'nisn' => $child->nisn,
                'family_card_number' => $child->family_card_number,
                'religion' => $child->religion,
                'blood_type' => $child->blood_type,
                'child_order' => $child->child_order,
                'siblings_count' => $child->siblings_count,
                'address' => $child->address,
                'photo_url' => $child->photo_url,
            ],
            'formalPhotoUrl' => $registrant->formal_photo_url,
            'parents' => collect(ParentRelation::cases())->mapWithKeys(function (ParentRelation $relation) use ($child): array {
                $parentship = $child->parentships->first(fn (Parentship $link): bool => $link->relation === $relation);

                return [$relation->value => $parentship === null ? null : $this->parent($parentship)];
            }),
            'options' => [
                ...PersonOptions::all(),
                'genders' => array_map(fn (Gender $case): array => ['value' => $case->value, 'label' => $case->label()], Gender::cases()),
                'kinds' => array_map(fn (ParentKind $case): array => ['value' => $case->value, 'label' => $case->label()], ParentKind::cases()),
            ],
            'canEdit' => Gate::allows('update', $registrant),
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

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Datanya sudah disimpan sebagai draf.']);

        return to_route('portal.children.data.edit', $registrant);
    }

    /**
     * @return array<string, mixed>
     */
    private function parent(Parentship $parentship): array
    {
        /** @var Person $person */
        $person = $parentship->relatedPerson;

        return [
            'name' => $person->name,
            'nik' => $person->nik,
            'phone' => $person->phone,
            'job' => $person->job,
            'last_education' => $person->last_education,
            'monthly_income' => $person->monthly_income,
            'address' => $person->address,
            'is_alive' => $person->is_alive,
            'kind' => $parentship->kind?->value,
        ];
    }
}
