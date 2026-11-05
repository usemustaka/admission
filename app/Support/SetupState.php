<?php

namespace App\Support;

use App\Enums\DefaultRole;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * Whether the first-run setup is done. It is done once the setup page has been completed, or when a super admin
 * already exists (an install that was set up from the console). Only a positive answer is remembered, so a fresh
 * install keeps asking until it is done, and a database that is not reachable counts as "not done".
 */
class SetupState
{
    public const SETTING_KEY = 'setup.completed_at';

    private const CACHE_KEY = 'setup.complete';

    public static function isComplete(): bool
    {
        try {
            if (Cache::get(self::CACHE_KEY) === true) {
                return true;
            }
        } catch (Throwable) {
            // The cache may live in the database, which is what is missing on a fresh install.
        }

        try {
            $complete = Setting::query()->where('key', self::SETTING_KEY)->exists()
                || User::query()->role(DefaultRole::SuperAdmin->value)->exists();
        } catch (Throwable) {
            return false;
        }

        if ($complete) {
            self::remember();
        }

        return $complete;
    }

    public static function markComplete(): void
    {
        Setting::put(self::SETTING_KEY, now()->toIso8601String());

        self::remember();
    }

    private static function remember(): void
    {
        try {
            Cache::forever(self::CACHE_KEY, true);
        } catch (Throwable) {
            // Not remembering only costs one more query on the next request.
        }
    }
}
