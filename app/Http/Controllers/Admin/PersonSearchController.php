<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Person;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class PersonSearchController extends Controller
{
    /**
     * Quick lookup by name or NIK, for pickers that link one person to another.
     */
    public function __invoke(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', Person::class);

        $people = Person::query()
            ->search($request->string('q')->toString())
            ->when($request->integer('except'), fn ($query, int $id) => $query->whereKeyNot($id))
            ->orderBy('name')
            ->limit(8)
            ->get(['id', 'name', 'nik', 'birth_date']);

        return response()->json([
            'data' => $people->map(fn (Person $person): array => [
                'id' => $person->id,
                'name' => $person->name,
                'nik' => $person->nik,
                'birth_date' => $person->birth_date?->toDateString(),
            ]),
        ]);
    }
}
