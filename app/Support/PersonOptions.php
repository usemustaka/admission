<?php

namespace App\Support;

/**
 * Choices offered when filling in a person's profile.
 */
class PersonOptions
{
    /** @var list<string> */
    public const RELIGIONS = ['Islam', 'Kristen', 'Katolik', 'Hindu', 'Buddha', 'Konghucu', 'Lainnya'];

    /** @var list<string> */
    public const BLOOD_TYPES = ['A', 'B', 'AB', 'O'];

    /** @var list<string> */
    public const EDUCATIONS = ['Tidak sekolah', 'SD / sederajat', 'SMP / sederajat', 'SMA / sederajat', 'D1 - D3', 'S1 / D4', 'S2', 'S3'];

    /** @var list<string> */
    public const INCOMES = ['Kurang dari Rp1 juta', 'Rp1 juta - Rp3 juta', 'Rp3 juta - Rp5 juta', 'Rp5 juta - Rp10 juta', 'Lebih dari Rp10 juta'];

    /**
     * @return array<string, array<int, array{value: string, label: string}>>
     */
    public static function all(): array
    {
        $toOptions = fn (array $values): array => array_map(fn (string $value): array => ['value' => $value, 'label' => $value], $values);

        return [
            'religions' => $toOptions(self::RELIGIONS),
            'bloodTypes' => $toOptions(self::BLOOD_TYPES),
            'educations' => $toOptions(self::EDUCATIONS),
            'incomes' => $toOptions(self::INCOMES),
        ];
    }
}
