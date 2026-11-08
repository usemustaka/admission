<?php

namespace App\Actions\Portal;

use App\Actions\Notes\WritePersonAuditNote;
use App\Actions\People\LinkParent;
use App\Actions\People\SavePerson;
use App\Enums\AdmissionType;
use App\Enums\EducationLevel;
use App\Enums\ParentKind;
use App\Enums\ParentRelation;
use App\Models\EducationHistory;
use App\Models\Parentship;
use App\Models\Person;
use App\Models\Registrant;
use App\Models\User;
use BackedEnum;
use DateTimeInterface;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SaveRegistrantData
{
    public function __construct(
        private SavePerson $savePerson,
        private LinkParent $linkParent,
        private WritePersonAuditNote $writePersonAuditNote,
    ) {}

    /**
     * Save the draft: the child's profile and photos, and the parents' details.
     *
     * Existing people are never overwritten by strangers: a NIK can only be set while blank, and a person
     * who is already known is linked with their blanks filled in rather than replaced.
     *
     * When a committee member does it on the family's behalf (`$auditedBy`), the fields they changed are
     * written down in a private note on the child; parents editing their own draft leave no such trace.
     *
     * @param  array<string, mixed>  $child
     * @param  array<string, array<string, mixed>>  $parents  keyed by relation (father, mother, guardian)
     * @param  list<array<string, mixed>>|null  $education  the schools to keep; null leaves them as they are
     *
     * @throws ValidationException
     */
    public function handle(
        Registrant $registrant,
        array $child,
        array $parents,
        ?UploadedFile $photo = null,
        bool $removePhoto = false,
        ?User $auditedBy = null,
        ?array $education = null,
        ?AdmissionType $admissionType = null,
    ): void {
        DB::transaction(function () use ($registrant, $child, $parents, $photo, $removePhoto, $auditedBy, $education, $admissionType): void {
            $person = $registrant->person;
            $hadPhoto = $person->photo_path !== null;
            $before = $auditedBy === null ? [] : $this->snapshot($person);

            $this->savePerson->handle($person, $child, $photo, $removePhoto);

            $educationChanged = $education !== null && $this->saveEducation($person, $education);
            $typeChanged = $admissionType !== null && $registrant->admission_type !== $admissionType;

            if ($typeChanged) {
                $registrant->update(['admission_type' => $admissionType]);
            }

            foreach (ParentRelation::cases() as $relation) {
                $fields = $parents[$relation->value] ?? [];

                if (Arr::only($fields, self::PARENT_FIELDS) === [] || ! $this->hasContent($fields)) {
                    continue;
                }

                $this->saveParent($person, $relation, $fields);
            }

            if ($auditedBy !== null) {
                $photoChange = match (true) {
                    $photo !== null => 'Foto',
                    $removePhoto && $hadPhoto => 'Foto (dihapus)',
                    default => null,
                };

                $this->writeAuditNote($person, $auditedBy, $before, $this->snapshot($person), $photoChange, array_values(array_filter([
                    $educationChanged ? 'Riwayat pendidikan' : null,
                    $typeChanged ? 'Status pendaftar' : null,
                ])));
            }
        });
    }

    /** @var array<string, string> */
    private const CHILD_LABELS = [
        'gender' => 'Jenis kelamin',
        'birth_place' => 'Tempat lahir',
        'birth_date' => 'Tanggal lahir',
        'nisn' => 'NISN',
        'family_card_number' => 'Nomor KK',
        'religion' => 'Agama',
        'blood_type' => 'Golongan darah',
        'child_order' => 'Anak ke',
        'siblings_count' => 'Jumlah saudara',
        'address' => 'Alamat',
    ];

    /** @var array<string, string> */
    private const PARENT_LABELS = [
        'name' => 'nama',
        'nik' => 'NIK',
        'phone' => 'nomor HP',
        'job' => 'pekerjaan',
        'last_education' => 'pendidikan terakhir',
        'monthly_income' => 'penghasilan',
        'address' => 'alamat',
        'is_alive' => 'status hidup',
        'kind' => 'hubungan',
    ];

    /**
     * The values the committee can change on this form, as plain strings: the child's, and each parent's.
     *
     * @return array{child: array<string, string>, parents: array<string, array<string, string>>}
     */
    private function snapshot(Person $child): array
    {
        $value = fn (mixed $raw): string => match (true) {
            $raw instanceof BackedEnum => (string) $raw->value,
            $raw instanceof DateTimeInterface => $raw->format('Y-m-d'),
            is_bool($raw) => $raw ? '1' : '0',
            default => (string) ($raw ?? ''),
        };

        $parents = [];

        foreach ($child->parentships()->with('relatedPerson')->get() as $parentship) {
            $parents[$parentship->relation->value] = collect(array_keys(self::PARENT_LABELS))
                ->mapWithKeys(fn (string $field): array => [$field => $value($field === 'kind' ? $parentship->kind : $parentship->relatedPerson->getAttribute($field))])
                ->all();
        }

        return [
            'child' => collect(array_keys(self::CHILD_LABELS))->mapWithKeys(fn (string $field): array => [$field => $value($child->getAttribute($field))])->all(),
            'parents' => $parents,
        ];
    }

    /**
     * @param  array{child?: array<string, string>, parents?: array<string, array<string, string>>}  $before
     * @param  array{child: array<string, string>, parents: array<string, array<string, string>>}  $after
     * @param  list<string>  $extra  other things that changed, already worded for the note
     */
    private function writeAuditNote(Person $child, User $by, array $before, array $after, ?string $photo, array $extra = []): void
    {
        $changes = $extra;

        foreach (self::CHILD_LABELS as $field => $label) {
            if (($before['child'][$field] ?? '') !== $after['child'][$field]) {
                $changes[] = $label;
            }
        }

        if ($photo !== null) {
            $changes[] = $photo;
        }

        foreach (ParentRelation::cases() as $relation) {
            $old = $before['parents'][$relation->value] ?? null;
            $new = $after['parents'][$relation->value] ?? null;

            if ($new === null || $old === $new) {
                continue;
            }

            $changed = $old === null
                ? ['ditambahkan']
                : array_values(array_filter(self::PARENT_LABELS, fn (string $label, string $field): bool => $old[$field] !== $new[$field], ARRAY_FILTER_USE_BOTH));

            $changes[] = "{$relation->label()} (".implode(', ', $changed).')';
        }

        if ($changes === []) {
            return;
        }

        $this->writePersonAuditNote->handle($child, $by, 'Data pendaftaran diubah panitia: '.implode(', ', $changes).'.');
    }

    /**
     * Replace the schools of the child with the rows of the form, in the order given. Returns whether anything
     * differs from what was stored.
     *
     * @param  list<array<string, mixed>>  $rows
     */
    private function saveEducation(Person $child, array $rows): bool
    {
        $rows = collect($rows)
            ->filter(fn (array $row): bool => filled($row['school_name'] ?? null))
            ->values()
            ->map(fn (array $row, int $index): array => [
                'level' => (string) ($row['level'] ?? EducationLevel::Other->value),
                'school_name' => trim((string) $row['school_name']),
                'npsn' => filled($row['npsn'] ?? null) ? (string) $row['npsn'] : null,
                'school_address' => filled($row['school_address'] ?? null) ? trim((string) $row['school_address']) : null,
                'sort_order' => $index,
            ]);

        $stored = $child->educationHistories()->get(['level', 'school_name', 'npsn', 'school_address', 'sort_order'])
            ->map(fn (EducationHistory $history): array => [...$history->only(['school_name', 'npsn', 'school_address', 'sort_order']), 'level' => $history->level->value]);

        if ($stored->toArray() === $rows->toArray()) {
            return false;
        }

        $child->educationHistories()->delete();
        $rows->each(fn (array $row) => $child->educationHistories()->create($row));

        return true;
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
