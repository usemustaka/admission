<?php

namespace App\Support;

class Money
{
    /**
     * Rupiah as shown to people, e.g. "Rp1.250.000".
     */
    public static function format(int $amount): string
    {
        return 'Rp'.number_format($amount, 0, ',', '.');
    }
}
