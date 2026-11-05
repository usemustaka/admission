<?php

namespace App\Enums;

use App\Contracts\HasLabel;

/**
 * Worked out from verified payments, never stored.
 */
enum BillStatus: string implements HasLabel
{
    case Unpaid = 'unpaid';
    case Partial = 'partial';
    case Paid = 'paid';

    public function label(): string
    {
        return match ($this) {
            self::Unpaid => 'Belum dibayar',
            self::Partial => 'Dibayar sebagian',
            self::Paid => 'Lunas',
        };
    }
}
