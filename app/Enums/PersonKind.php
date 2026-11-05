<?php

namespace App\Enums;

use App\Contracts\HasLabel;

/**
 * The part a person plays at the school, used to narrow the people list. One person can play several.
 */
enum PersonKind: string implements HasLabel
{
    /** Has applied to enrol, or already is a student. */
    case Student = 'siswa';

    /** Is the parent or guardian of someone. */
    case Family = 'keluarga';

    /** Works at an organization. */
    case Employee = 'pegawai';

    public function label(): string
    {
        return match ($this) {
            self::Student => 'Calon siswa & siswa',
            self::Family => 'Orang tua & wali',
            self::Employee => 'Pegawai',
        };
    }
}
