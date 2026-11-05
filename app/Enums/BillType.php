<?php

namespace App\Enums;

use App\Contracts\HasLabel;

enum BillType: string implements HasLabel
{
    case Registration = 'registration';
    case Settlement = 'settlement';

    public function label(): string
    {
        return match ($this) {
            self::Registration => 'Pendaftaran',
            self::Settlement => 'Pelunasan',
        };
    }
}
