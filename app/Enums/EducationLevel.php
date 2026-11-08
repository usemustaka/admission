<?php

namespace App\Enums;

use App\Contracts\HasLabel;

enum EducationLevel: string implements HasLabel
{
    case Kindergarten = 'kindergarten';
    case Elementary = 'elementary';
    case JuniorHigh = 'junior-high';
    case SeniorHigh = 'senior-high';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Kindergarten => 'PAUD/TK/RA',
            self::Elementary => 'SD/MI',
            self::JuniorHigh => 'SMP/MTs',
            self::SeniorHigh => 'SMA/MA/SMK',
            self::Other => 'Lainnya',
        };
    }
}
