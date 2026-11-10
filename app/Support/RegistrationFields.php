<?php

namespace App\Support;

use App\Enums\ParentRelation;
use App\Models\Parentship;
use App\Models\Person;
use App\Models\Registrant;

/**
 * The details of a registration that a ruleset can make required before a parent may send the data for review. Every
 * lembaga has its own ruleset, so each decides for itself what it insists on. A field is named `group.field`: `child`
 * for the child's profile, `education` for the previous school, `father` and `mother` for the parents.
 */
class RegistrationFields
{
    /**
     * What registrations without their own list have always required: the child's basics.
     *
     * @var list<string>
     */
    public const BASELINE = [
        'child.gender', 'child.birth_place', 'child.birth_date', 'child.religion', 'child.address', 'child.family_card_number',
    ];

    /** @var array<string, string> */
    private const CHILD = [
        'gender' => 'Jenis kelamin',
        'birth_place' => 'Tempat lahir',
        'birth_date' => 'Tanggal lahir',
        'religion' => 'Agama',
        'address' => 'Alamat',
        'family_card_number' => 'Nomor KK',
        'nisn' => 'NISN',
        'child_order' => 'Anak ke-',
        'siblings_count' => 'Jumlah saudara',
        'blood_type' => 'Golongan darah',
    ];

    /** @var array<string, string> */
    private const EDUCATION = [
        'school_name' => 'Nama sekolah asal',
        'npsn' => 'NPSN sekolah asal',
        'school_address' => 'Alamat sekolah asal',
    ];

    /** @var array<string, string> */
    private const PARENT = [
        'name' => 'Nama',
        'nik' => 'NIK',
        'phone' => 'Nomor HP / WA',
        'job' => 'Pekerjaan',
        'last_education' => 'Pendidikan',
        'monthly_income' => 'Penghasilan',
        'address' => 'Alamat',
    ];

    /**
     * What a new ruleset asks for until the committee changes it: the basics plus the details most schools want.
     *
     * @return list<string>
     */
    public static function defaults(): array
    {
        return [
            ...self::BASELINE,
            'child.nisn', 'child.child_order', 'child.siblings_count',
            'education.npsn', 'education.school_address',
            ...self::keysOf('father'),
            ...self::keysOf('mother'),
        ];
    }

    /**
     * Every field a ruleset can require, in groups for the settings page.
     *
     * @return list<array{group: string, title: string, fields: list<array{key: string, label: string}>}>
     */
    public static function groups(): array
    {
        $fields = fn (string $group, array $labels): array => array_map(
            fn (string $field, string $label): array => ['key' => "{$group}.{$field}", 'label' => $label],
            array_keys($labels),
            array_values($labels),
        );

        return [
            ['group' => 'child', 'title' => 'Data anak', 'fields' => $fields('child', self::CHILD)],
            ['group' => 'education', 'title' => 'Riwayat sekolah', 'fields' => $fields('education', self::EDUCATION)],
            ['group' => 'father', 'title' => 'Data ayah', 'fields' => $fields('father', self::PARENT)],
            ['group' => 'mother', 'title' => 'Data ibu', 'fields' => $fields('mother', self::PARENT)],
        ];
    }

    /**
     * @return list<string>
     */
    public static function allKeys(): array
    {
        return array_merge(...array_map(fn (array $group): array => array_column($group['fields'], 'key'), self::groups()));
    }

    /**
     * What this registration has to have filled in, from the copy of the ruleset it was made under.
     *
     * @return list<string>
     */
    public static function requiredFor(Registrant $registrant): array
    {
        $fields = $registrant->ruleset_snapshot['required_fields'] ?? null;

        return is_array($fields) ? array_values(array_intersect(self::allKeys(), $fields)) : self::BASELINE;
    }

    /**
     * The required details that are still empty, in plain words.
     *
     * @return list<string>
     */
    public static function missing(Registrant $registrant): array
    {
        $person = $registrant->person;
        $missing = [];

        foreach (self::requiredFor($registrant) as $key) {
            if (! self::isFilled($person, $key)) {
                $missing[] = self::describe($key);
            }
        }

        return $missing;
    }

    /**
     * How many of the required details are filled, for the progress shown on the registration.
     *
     * @return array{filled: int, total: int}
     */
    public static function progress(Registrant $registrant): array
    {
        $required = self::requiredFor($registrant);

        return [
            'filled' => count(array_filter($required, fn (string $key): bool => self::isFilled($registrant->person, $key))),
            'total' => count($required),
        ];
    }

    private static function isFilled(Person $person, string $key): bool
    {
        [$group, $field] = explode('.', $key, 2);

        if ($group === 'child') {
            return filled($person->getAttribute($field));
        }

        if ($group === 'education') {
            $histories = $person->relationLoaded('educationHistories') ? $person->educationHistories : $person->educationHistories()->get();

            return $histories->contains(fn ($history): bool => filled($history->getAttribute($field)));
        }

        $relation = ParentRelation::from($group);
        $parentships = $person->relationLoaded('parentships') ? $person->parentships : $person->parentships()->get();
        $parent = $parentships->first(fn (Parentship $parentship): bool => $parentship->relation === $relation)?->relatedPerson;

        return $parent !== null && filled($parent->getAttribute($field));
    }

    private static function describe(string $key): string
    {
        [$group, $field] = explode('.', $key, 2);

        return match ($group) {
            'child' => self::CHILD[$field].' anak',
            'education' => self::EDUCATION[$field],
            default => self::PARENT[$field].' '.($group === 'father' ? 'ayah' : 'ibu'),
        };
    }

    /**
     * @return list<string>
     */
    private static function keysOf(string $group): array
    {
        return array_map(fn (string $field): string => "{$group}.{$field}", array_keys(self::PARENT));
    }
}
