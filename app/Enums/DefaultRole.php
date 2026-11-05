<?php

namespace App\Enums;

enum DefaultRole: string
{
    case SuperAdmin = 'super-admin';
    case Admin = 'admin';
    case Operator = 'operator';
    case User = 'user';

    /**
     * Marks a deactivated account. It keeps the login but is refused everywhere.
     */
    case Inactive = 'nonaktif';
}
