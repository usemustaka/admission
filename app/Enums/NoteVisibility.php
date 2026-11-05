<?php

namespace App\Enums;

use App\Contracts\HasLabel;

enum NoteVisibility: string implements HasLabel
{
    /** Only the author and the people or roles it is shared with. */
    case Private = 'private';

    /** Every staff member allowed to view notes. */
    case Public = 'public';

    public function label(): string
    {
        return match ($this) {
            self::Private => 'Privat',
            self::Public => 'Semua panitia',
        };
    }
}
