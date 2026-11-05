<?php

namespace App\Enums;

use App\Contracts\HasLabel;

enum ParentRelation: string implements HasLabel
{
    case Father = 'father';
    case Mother = 'mother';
    case Guardian = 'guardian';

    public function label(): string
    {
        return match ($this) {
            self::Father => 'Ayah',
            self::Mother => 'Ibu',
            self::Guardian => 'Wali',
        };
    }

    /**
     * Only fathers and mothers can be biological or step parents.
     */
    public function hasKind(): bool
    {
        return $this !== self::Guardian;
    }
}
