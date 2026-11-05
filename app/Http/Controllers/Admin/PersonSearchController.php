<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Parentship;
use App\Models\Person;
use App\Support\PhoneNumber;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class PersonSearchController extends Controller
{
    /**
     * Quick lookup by name, NIK, NISN, family card number or phone number, for pickers that link one person to another. When `except` names a child, each
     * result says whether it is already a parent of that child.
     */
    public function __invoke(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', Person::class);

        $childId = $request->integer('except');

        $people = Person::query()
            ->search($request->string('q')->toString())
            ->when($childId, fn ($query, int $id) => $query->whereKeyNot($id))
            ->orderBy('name')
            ->limit(8)
            ->get(['id', 'name', 'nik', 'birth_date', 'phone']);

        $linkedIds = $childId === 0
            ? collect()
            : Parentship::query()
                ->where('person_id', $childId)
                ->whereIn('related_person_id', $people->modelKeys())
                ->pluck('related_person_id');

        return response()->json([
            'data' => $people->map(fn (Person $person): array => [
                'id' => $person->id,
                'name' => $person->name,
                'nik' => $person->nik,
                'birth_date' => $person->birth_date?->toDateString(),
                'phone_masked' => PhoneNumber::mask($person->phone),
                'linked' => $linkedIds->contains($person->id),
            ]),
        ]);
    }
}
