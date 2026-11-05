<?php

namespace App\Support;

use App\Models\Person;
use App\Models\Registrant;

/**
 * Read-only facts about a person that several admin screens show: the parts they play and their registrations.
 */
class PersonOverview
{
    /**
     * @return list<string>
     */
    public static function roles(bool $isStudent, bool $isApplicant, bool $isFamily, bool $isEmployee): array
    {
        return array_values(array_filter([
            $isStudent ? 'Siswa' : ($isApplicant ? 'Calon siswa' : null),
            $isFamily ? 'Orang tua/wali' : null,
            $isEmployee ? 'Pegawai' : null,
        ]));
    }

    /**
     * @return list<string>
     */
    public static function rolesOf(Person $person): array
    {
        return self::roles(
            $person->students()->exists(),
            $person->registrants()->exists(),
            $person->childrenParentships()->exists(),
            $person->employees()->exists(),
        );
    }

    /**
     * The person's own registrations plus those of the children they are a parent or guardian of, newest first.
     *
     * @return list<array{id: int, child_name: string, is_self: bool, organization: string, wave: string, status: string, status_label: string}>
     */
    public static function registrations(Person $person, int $limit = 10): array
    {
        $childIds = $person->childrenParentships()->pluck('person_id');

        return Registrant::query()
            ->with(['person', 'organization', 'wave'])
            ->whereIn('person_id', $childIds->push($person->id))
            ->latest()
            ->limit($limit)
            ->get()
            ->map(fn (Registrant $registrant): array => [
                'id' => $registrant->id,
                'child_name' => $registrant->person->name,
                'is_self' => $registrant->person_id === $person->id,
                'organization' => $registrant->organization->name,
                'wave' => $registrant->wave->name,
                'status' => $registrant->status->value,
                'status_label' => $registrant->status->label(),
            ])
            ->all();
    }
}
