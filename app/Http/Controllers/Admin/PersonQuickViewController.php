<?php

namespace App\Http\Controllers\Admin;

use App\Enums\Ability;
use App\Http\Controllers\Controller;
use App\Models\Note;
use App\Models\Parentship;
use App\Models\Person;
use App\Support\PersonOverview;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

class PersonQuickViewController extends Controller
{
    /**
     * Everything the people list shows in its side panel, so staff can glance at one person without leaving the list.
     */
    public function __invoke(Request $request, Person $person): JsonResponse
    {
        Gate::authorize('view', $person);

        $user = $request->user();

        $person->load(['parentships.relatedPerson', 'childrenParentships.person']);

        $notes = $person->notes()
            ->visibleTo($user)
            ->with('author.person')
            ->latest()
            ->limit(3)
            ->get();

        $primaryParent = $person->parentships->firstWhere('is_primary_contact', true);

        return response()->json(['data' => [
            'person' => [
                'id' => $person->id,
                'name' => $person->name,
                'nik' => $person->nik,
                'gender' => $person->gender?->label(),
                'birth' => collect([$person->birth_place, $person->birth_date?->translatedFormat('j F Y')])->filter()->implode(', ') ?: null,
                'phone' => $person->phone,
                'email' => $person->email,
                'is_alive' => $person->is_alive,
                'photo_url' => $person->photo_url,
            ],
            'roles' => PersonOverview::rolesOf($person),
            'primary_contact' => $primaryParent === null ? null : [
                'id' => $primaryParent->relatedPerson->id,
                'name' => $primaryParent->relatedPerson->name,
                'relation' => $primaryParent->relation->label(),
                'phone' => $primaryParent->relatedPerson->phone,
            ],
            'parents' => $person->parentships->map(fn (Parentship $parentship): array => [
                'id' => $parentship->relatedPerson->id,
                'name' => $parentship->relatedPerson->name,
                'photo_url' => $parentship->relatedPerson->photo_url,
                'phone' => $parentship->relatedPerson->phone,
                'relation' => $parentship->relation->label(),
                'is_primary_contact' => $parentship->is_primary_contact,
            ])->values(),
            'dependents' => $person->childrenParentships->map(fn (Parentship $parentship): array => [
                'id' => $parentship->person->id,
                'name' => $parentship->person->name,
                'photo_url' => $parentship->person->photo_url,
                'relation' => $parentship->relation->label(),
            ])->values(),
            'notes' => $notes->map(fn (Note $note): array => [
                'id' => $note->id,
                'body' => Str::limit($note->body, 160),
                'urgency' => $note->urgency->value,
                'urgency_label' => $note->urgency->label(),
                'author' => $note->author_id === null ? 'Sistem' : $note->author->name,
                'created_at' => $note->created_at?->translatedFormat('j M Y, H:i'),
            ])->values(),
            'registrations' => $user->can(Ability::RegistrantView->value) ? PersonOverview::registrations($person, 5) : [],
            'can_view_registrations' => $user->can(Ability::RegistrantView->value),
        ]]);
    }
}
