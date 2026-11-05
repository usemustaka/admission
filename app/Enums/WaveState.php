<?php

namespace App\Enums;

use App\Contracts\HasLabel;

/**
 * Where a wave is on the calendar. A wave without dates never ends, so it counts as ongoing.
 */
enum WaveState: string implements HasLabel
{
    case Upcoming = 'upcoming';
    case Ongoing = 'ongoing';
    case Ended = 'ended';

    public function label(): string
    {
        return match ($this) {
            self::Upcoming => 'Akan datang',
            self::Ongoing => 'Berlangsung',
            self::Ended => 'Selesai',
        };
    }
}
