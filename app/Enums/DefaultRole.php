<?php

namespace App\Enums;

enum DefaultRole: string
{
    case SuperAdmin = 'super-admin';
    case Admin = 'admin';
    case Operator = 'operator';
    case User = 'user';
}
