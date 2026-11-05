<?php

namespace App\Enums;

use App\Contracts\HasLabel;

enum PaymentMethod: string implements HasLabel
{
    case Transfer = 'transfer';
    case Cash = 'cash';

    public function label(): string
    {
        return match ($this) {
            self::Transfer => 'Transfer bank',
            self::Cash => 'Tunai',
        };
    }
}
