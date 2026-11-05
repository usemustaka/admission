<?php

namespace App\Support;

use App\Models\Setting;
use App\Models\User;
use Laravel\Fortify\Features;

/**
 * Whether staff must protect their account with two-factor authentication. A school can switch it on or off in
 * the settings; until it does, the `REQUIRE_TWO_FACTOR_STAFF` environment value decides.
 */
class StaffTwoFactor
{
    public const SETTING_KEY = 'security.require_two_factor_staff';

    public static function isRequired(): bool
    {
        $stored = Setting::get(self::SETTING_KEY);

        return $stored === null ? (bool) config('security.require_two_factor_staff') : (bool) $stored;
    }

    /**
     * Whether this staff member still has to set up two-factor authentication before using the admin area.
     */
    public static function mustSetUp(User $user): bool
    {
        return $user->two_factor_confirmed_at === null
            && Features::canManageTwoFactorAuthentication()
            && self::isRequired();
    }
}
