<?php

namespace App\Support;

use App\Models\Parentship;
use App\Models\Person;
use App\Models\Registrant;

class RegistrantPresenter
{
    /** @var list<string> */
    private const CHILD_REQUIRED = ['gender', 'birth_place', 'birth_date', 'religion', 'address', 'family_card_number'];

    /**
     * The child's name as a parent may see it. While the committee confirms the NIK, that is only the name the
     * parent typed, never the one already on record.
     */
    public static function childName(Registrant $registrant): string
    {
        return $registrant->needs_identity_review ? $registrant->submitted_name : $registrant->person->name;
    }

    public static function childPhotoUrl(Registrant $registrant): ?string
    {
        return $registrant->needs_identity_review ? null : $registrant->person->photo_url;
    }

    /**
     * What a parent sees of a registration on the portal.
     *
     * @return array<string, mixed>
     */
    public static function summary(Registrant $registrant): array
    {
        $registrant->loadMissing(['person', 'organization', 'wave']);

        return [
            'id' => $registrant->id,
            'child' => [
                'id' => $registrant->person->id,
                'name' => self::childName($registrant),
                'photo_url' => self::childPhotoUrl($registrant),
            ],
            'identity_pending' => $registrant->needs_identity_review,
            'organization' => $registrant->organization->name,
            'wave' => $registrant->wave->name,
            'status' => $registrant->status->value,
            'status_label' => $registrant->status->label(),
            'steps' => $registrant->steps(),
            'current_step' => $registrant->currentStep(),
            'can_edit' => $registrant->status->isEditableByParent() && ! $registrant->needs_identity_review,
        ];
    }

    /**
     * How much of the draft is filled in: the child's key details, both parents' names, and the formal photo.
     *
     * @return array{filled: int, total: int}
     */
    public static function completeness(Registrant $registrant): array
    {
        $person = $registrant->person;
        $parentships = $person->parentships()->get();

        $filled = collect(self::CHILD_REQUIRED)->filter(fn (string $field): bool => filled($person->getAttribute($field)))->count();

        foreach (['father', 'mother'] as $relation) {
            $parentship = $parentships->first(fn (Parentship $link): bool => $link->relation->value === $relation);

            if ($parentship !== null && filled(Person::query()->whereKey($parentship->related_person_id)->value('name'))) {
                $filled++;
            }
        }

        if ($registrant->formal_photo_path !== null) {
            $filled++;
        }

        return ['filled' => $filled, 'total' => count(self::CHILD_REQUIRED) + 3];
    }
}
