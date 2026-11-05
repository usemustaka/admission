<?php

namespace App\Http\Controllers\Admin;

use App\Actions\People\LinkParent;
use App\Actions\People\SetPrimaryContact;
use App\Actions\People\UnlinkParent;
use App\Enums\Ability;
use App\Enums\ParentKind;
use App\Enums\ParentRelation;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreParentshipRequest;
use App\Models\Parentship;
use App\Models\Person;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;

class ParentshipController extends Controller
{
    public function store(StoreParentshipRequest $request, Person $person, LinkParent $linkParent): RedirectResponse
    {
        $linkParent->handle(
            $person,
            Person::findOrFail($request->integer('related_person_id')),
            ParentRelation::from($request->string('relation')->toString()),
            ParentKind::tryFrom($request->string('kind')->toString()),
            $request->boolean('is_primary_contact'),
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Orang tua atau wali sudah ditambahkan.']);

        return to_route('admin.people.show', $person);
    }

    /**
     * Make this parent the primary contact.
     */
    public function update(Person $person, Parentship $parentship, SetPrimaryContact $setPrimaryContact): RedirectResponse
    {
        Gate::authorize(Ability::FamilyManage->value);

        $setPrimaryContact->handle($parentship);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Kontak utama sudah diganti.']);

        return to_route('admin.people.show', $person);
    }

    public function destroy(Person $person, Parentship $parentship, UnlinkParent $unlinkParent): RedirectResponse
    {
        Gate::authorize(Ability::FamilyManage->value);

        $unlinkParent->handle($parentship);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Hubungan keluarga sudah dihapus.']);

        return to_route('admin.people.show', $person);
    }
}
