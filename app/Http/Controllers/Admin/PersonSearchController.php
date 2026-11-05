<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Parentship;
use App\Models\Person;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

class PersonSearchController extends Controller
{
    /**
     * Quick lookup by name or NIK, for pickers that link one person to another. When `except` names a child, each
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
                'phone_masked' => $this->maskPhone($person->phone),
                'linked' => $linkedIds->contains($person->id),
            ]),
        ]);
    }

    /**
     * Enough of the number to tell two people apart (0812••••7890) without exposing it.
     */
    private function maskPhone(?string $phone): ?string
    {
        $digits = preg_replace('/\D+/', '', (string) $phone) ?? '';

        if ($digits === '') {
            return null;
        }

        return strlen($digits) >= 9 ? Str::mask($digits, '•', 4, -4) : Str::mask($digits, '•', 2, -2);
    }
}
