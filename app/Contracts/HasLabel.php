<?php

namespace App\Contracts;

use BackedEnum;

/**
 * An enum case that has a human-readable Indonesian label.
 */
interface HasLabel extends BackedEnum
{
    public function label(): string;
}
