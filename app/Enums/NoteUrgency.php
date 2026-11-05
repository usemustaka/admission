<?php

namespace App\Enums;

use App\Contracts\HasLabel;

enum NoteUrgency: string implements HasLabel
{
    case Low = 'low';
    case Normal = 'normal';
    case High = 'high';
    case Critical = 'critical';

    public function label(): string
    {
        return match ($this) {
            self::Low => 'Santai',
            self::Normal => 'Biasa',
            self::High => 'Penting',
            self::Critical => 'Mendesak',
        };
    }
}
