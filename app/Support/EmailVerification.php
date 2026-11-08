<?php

namespace App\Support;

use App\Models\Setting;
use App\Models\User;
use Illuminate\Contracts\Auth\MustVerifyEmail;

/**
 * Whether an account must verify its email before it can sign in to the app. A school can switch it off in the
 * settings; until it does, the `REQUIRE_EMAIL_VERIFICATION` environment value decides.
 */
class EmailVerification
{
    public const SETTING_KEY = 'security.require_email_verification';

    public static function isRequired(): bool
    {
        $stored = Setting::get(self::SETTING_KEY);

        return $stored === null ? (bool) config('security.require_email_verification') : (bool) $stored;
    }

    /**
     * Whether the account still has an unverified email, so the app can remind the person to check their inbox.
     */
    public static function isPending(?User $user): bool
    {
        return $user instanceof MustVerifyEmail && ! $user->hasVerifiedEmail();
    }
}
