<?php

namespace App\Enums;

enum DefaultRole: string
{
    case SuperAdmin = 'super-admin';
    case Admin = 'admin';
    case Operator = 'operator';
    case User = 'user';

    /** Committee that enters exam scores and announces the results. */
    case TestCommittee = 'panitia-tes';

    /** Committee that verifies payments, prints receipts and places students. */
    case FinanceCommittee = 'panitia-keuangan';

    /**
     * Marks a deactivated account. It keeps the login but is refused everywhere.
     */
    case Inactive = 'nonaktif';
}
