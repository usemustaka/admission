<?php

namespace App\Enums;

use App\Contracts\HasLabel;

enum ParentKind: string implements HasLabel
{
    case Biological = 'biological';
    case Step = 'step';

    public function label(): string
    {
        return match ($this) {
            self::Biological => 'Kandung',
            self::Step => 'Tiri',
        };
    }
}
