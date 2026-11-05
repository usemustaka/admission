<?php

namespace App\Enums;

use App\Contracts\HasLabel;

enum PaymentStatus: string implements HasLabel
{
    /** Submitted by a parent, waiting for the committee to check it. */
    case Pending = 'pending';

    /** Confirmed; only these payments count towards a bill. */
    case Verified = 'verified';

    case Rejected = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Menunggu verifikasi',
            self::Verified => 'Terverifikasi',
            self::Rejected => 'Ditolak',
        };
    }
}
