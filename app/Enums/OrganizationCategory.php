<?php

namespace App\Enums;

use App\Contracts\HasLabel;

/**
 * The kind of institution an organization (lembaga) is. It only groups and describes them; what each one
 * charges and tests is set in its rulesets.
 */
enum OrganizationCategory: string implements HasLabel
{
    case School = 'sekolah';
    case Madrasah = 'madrasah';
    case Pesantren = 'pesantren';
    case Bootcamp = 'bootcamp';

    public function label(): string
    {
        return match ($this) {
            self::School => 'Sekolah',
            self::Madrasah => 'Madrasah',
            self::Pesantren => 'Pesantren',
            self::Bootcamp => 'Bootcamp',
        };
    }

    /**
     * Every category as a choice for a select.
     *
     * @return list<array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(fn (self $category): array => ['value' => $category->value, 'label' => $category->label()], self::cases());
    }
}
