<?php

namespace App\Actions\Portal;

use App\Actions\People\LinkParent;
use App\Actions\People\SavePerson;
use App\Enums\ParentKind;
use App\Enums\ParentRelation;
use App\Models\Parentship;
use App\Models\Person;
use App\Models\Registrant;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SaveRegistrantData
{
    public function __construct(
        private SavePerson $savePerson,
        private LinkParent $linkParent,
    ) {}

    /**
     * Save the draft: the child's profile and photos, and the parents' details.
     *
     * Existing people are never overwritten by strangers: a NIK can only be set while blank, and a person
     * who is already known is linked with their blanks filled in rather than replaced.
     *
     * @param  array<string, mixed>  $child
     * @param  array<string, array<string, mixed>>  $parents  keyed by relation (father, mother, guardian)
     *
     * @throws ValidationException
     */
    public function handle(
        Registrant $registrant,
        array $child,
        array $parents,
        ?UploadedFile $photo = null,
        bool $removePhoto = false,
    ): void {
        DB::transaction(function () use ($registrant, $child, $parents, $photo, $removePhoto): void {
            $person = $registrant->person;

            $this->savePerson->handle($person, $child, $photo, $removePhoto);

            foreach (ParentRelation::cases() as $relation) {
                $fields = $parents[$relation->value] ?? [];

                if (Arr::only($fields, self::PARENT_FIELDS) === [] || ! $this->hasContent($fields)) {
                    continue;
                }

                $this->saveParent($person, $relation, $fields);
            }
        });
    }

    /** @var list<string> */
    private const PARENT_FIELDS = ['name', 'nik', 'phone', 'job', 'last_education', 'monthly_income', 'address'];

    /**
     * @param  array<string, mixed>  $fields
     */
    private function hasContent(array $fields): bool
    {
        return collect(Arr::only($fields, self::PARENT_FIELDS))->contains(fn (mixed $value): bool => filled($value));
    }

    /**
     * @param  array<string, mixed>  $fields
     */
    private function saveParent(Person $child, ParentRelation $relation, array $fields): void
    {
        $kind = isset($fields['kind']) ? ParentKind::tryFrom((string) $fields['kind']) : null;
        $details = Arr::only($fields, [...self::PARENT_FIELDS, 'is_alive']);

        $parentship = $child->parentships()->where('relation', $relation)->with('relatedPerson')->first();

        if ($parentship !== null) {
            $this->updateLinkedParent($parentship, $details, $kind);

            return;
        }

        $nik = filled($details['nik'] ?? null) ? (string) $details['nik'] : null;
        $known = $nik === null ? null : Person::query()->where('nik', $nik)->first();

        if ($known?->is($child)) {
            throw ValidationException::withMessages(["parents.{$relation->value}.nik" => 'NIK ini adalah NIK anak. Isi dengan NIK orang tuanya ya.']);
        }

        if ($known !== null) {
            $known->fill(array_filter(
                Arr::except($details, ['nik']),
                fn (mixed $value, string $key): bool => filled($value) && blank($known->getAttribute($key)),
                ARRAY_FILTER_USE_BOTH,
            ))->save();
            $person = $known;
        } else {
            $person = Person::create($details);
        }

        $this->linkParent->handle($child, $person, $relation, $relation->hasKind() ? ($kind ?? ParentKind::Biological) : null);
    }

    /**
     * @param  array<string, mixed>  $details
     */
    private function updateLinkedParent(Parentship $parentship, array $details, ?ParentKind $kind): void
    {
        $person = $parentship->relatedPerson;
        $nik = filled($details['nik'] ?? null) ? (string) $details['nik'] : null;

        if ($nik !== null && $nik !== $person->nik) {
            if ($person->nik !== null) {
                $nik = null;
            } elseif (Person::query()->where('nik', $nik)->whereKeyNot($person->id)->exists()) {
                throw ValidationException::withMessages(["parents.{$parentship->relation->value}.nik" => 'NIK ini sudah tercatat atas orang lain. Hubungi sekolah kalau ini keliru.']);
            }
        }

        $person->fill([...Arr::except($details, ['nik']), 'nik' => $nik ?? $person->nik])->save();

        if ($kind !== null && $parentship->relation->hasKind()) {
            $parentship->update(['kind' => $kind]);
        }
    }
}
