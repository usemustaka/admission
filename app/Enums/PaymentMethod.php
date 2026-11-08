<?php

namespace App\Enums;

use App\Contracts\HasLabel;

enum PaymentMethod: string implements HasLabel
{
    case Transfer = 'transfer';
    case Cash = 'cash';

    /** A scholarship the committee granted. It pays a bill without any money changing hands. */
    case Scholarship = 'scholarship';

    public function label(): string
    {
        return match ($this) {
            self::Transfer => 'Transfer bank',
            self::Cash => 'Tunai',
            self::Scholarship => 'Beasiswa',
        };
    }

    /**
     * The methods a person can pick when paying or recording a payment. A scholarship is granted through its own
     * action, never typed in as a payment.
     *
     * @return list<self>
     */
    public static function selectable(): array
    {
        return [self::Transfer, self::Cash];
    }
}
