<?php

namespace App\Enums;

use App\Contracts\HasLabel;

enum AdmissionType: string implements HasLabel
{
    /** A child entering the school for the first time. */
    case New = 'new';

    /** A child moving over from another school. */
    case Transfer = 'transfer';

    public function label(): string
    {
        return match ($this) {
            self::New => 'Siswa baru',
            self::Transfer => 'Siswa pindahan',
        };
    }
}
