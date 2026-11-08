<?php

namespace App\Enums;

use App\Contracts\HasLabel;

/**
 * Why a child may be eligible for a scholarship, worked out from the supporting files and numbers the parent gave.
 */
enum ScholarshipKind: string implements HasLabel
{
    case Achievement = 'achievement';
    case Tahfidz = 'tahfidz';
    case Hardship = 'hardship';

    public function label(): string
    {
        return match ($this) {
            self::Achievement => 'Prestasi',
            self::Tahfidz => 'Tahfidz',
            self::Hardship => 'Tidak mampu',
        };
    }
}
