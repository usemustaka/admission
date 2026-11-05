<?php

namespace App\Support;

use App\Enums\Gender;
use App\Enums\ParentKind;
use App\Enums\ParentRelation;
use App\Enums\RegistrantStatus;
use App\Models\Parentship;
use App\Models\Person;
use App\Models\Registrant;
use App\Models\RegistrantStatusChange;

class RegistrantPresenter
{
    /** @var array<string, string> */
    public const CHILD_REQUIRED = [
        'gender' => 'Jenis kelamin',
        'birth_place' => 'Tempat lahir',
        'birth_date' => 'Tanggal lahir',
        'religion' => 'Agama',
        'address' => 'Alamat',
        'family_card_number' => 'Nomor KK',
    ];

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
            'revision_reason' => self::revisionReason($registrant),
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

        $filled = collect(array_keys(self::CHILD_REQUIRED))->filter(fn (string $field): bool => filled($person->getAttribute($field)))->count();

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

    /**
     * The history of a registration. Parents read the reason of a revision, but not the internal note of a bypass
     * nor the name of the committee member.
     *
     * @return list<array{id: int, from: string|null, to: string, to_label: string, note: string|null, by: string|null, is_bypass: bool, at: string}>
     */
    public static function timeline(Registrant $registrant, bool $forParent): array
    {
        return array_values($registrant->statusChanges()->with('user.person')->get()->map(fn (RegistrantStatusChange $change): array => [
            'id' => $change->id,
            'from' => $change->from_status?->value,
            'to' => $change->to_status->value,
            'to_label' => $change->to_status->label(),
            'note' => $forParent && $change->is_bypass ? null : $change->note,
            'by' => $forParent ? null : $change->user?->name,
            'is_bypass' => $forParent ? false : $change->is_bypass,
            'at' => $change->created_at?->toIso8601String() ?? '',
        ])->all());
    }

    /**
     * Why the committee sent the data back, while it is waiting for the parent to fix it.
     */
    public static function revisionReason(Registrant $registrant): ?string
    {
        if ($registrant->status !== RegistrantStatus::Revision) {
            return null;
        }

        return $registrant->statusChanges()->where('to_status', RegistrantStatus::Revision)->where('is_bypass', false)->latest('id')->value('note');
    }

    /**
     * What the data form shows: the child, the parents by relation, the photos and the choices to pick from.
     * The same form is used by the parent on the portal and by the committee when helping out.
     *
     * @return array{child: array<string, mixed>, formalPhotoUrl: string|null, parents: array<string, array<string, mixed>|null>, options: array<string, mixed>}
     */
    public static function dataForm(Registrant $registrant): array
    {
        $registrant->loadMissing(['person.parentships.relatedPerson']);
        $child = $registrant->person;

        return [
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

                return [$relation->value => $parentship === null ? null : self::parent($parentship)];
            })->all(),
            'options' => [
                ...PersonOptions::all(),
                'genders' => array_map(fn (Gender $case): array => ['value' => $case->value, 'label' => $case->label()], Gender::cases()),
                'kinds' => array_map(fn (ParentKind $case): array => ['value' => $case->value, 'label' => $case->label()], ParentKind::cases()),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private static function parent(Parentship $parentship): array
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
