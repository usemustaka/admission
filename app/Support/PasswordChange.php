<?php

namespace App\Support;

use App\Models\Setting;
use App\Models\User;

/**
 * Accounts that must choose a new password before they can use the panel. The list lives in the settings table
 * as a JSON array of user ids, so it needs no column of its own.
 */
class PasswordChange
{
    public const SETTING_KEY = 'auth.force_password_change_user_ids';

    public static function isRequiredFor(User $user): bool
    {
        return in_array($user->id, self::userIds(), true);
    }

    public static function require(User $user): void
    {
        Setting::put(self::SETTING_KEY, array_values(array_unique([...self::userIds(), $user->id])));
    }

    public static function clear(User $user): void
    {
        Setting::put(self::SETTING_KEY, array_values(array_filter(self::userIds(), fn (int $id): bool => $id !== $user->id)));
    }

    /**
     * @return list<int>
     */
    private static function userIds(): array
    {
        return array_map('intval', (array) Setting::get(self::SETTING_KEY, []));
    }
}
