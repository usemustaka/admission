<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * The sign-ins of one account, read from and cleared in the `sessions` table. Only the database session driver
 * keeps that list; with any other driver nothing is listed and nothing can be ended from here.
 */
class UserSessions
{
    public static function isSupported(): bool
    {
        return config('session.driver') === 'database' && Schema::hasTable(self::table());
    }

    /**
     * The user's sessions, the one making this request first.
     *
     * @return list<array{handle: string, browser: string, platform: string, ip_address: string|null, last_active_at: string, is_current: bool}>
     */
    public static function listFor(User $user, string $currentId): array
    {
        return DB::table(self::table())
            ->where('user_id', $user->id)
            ->orderByDesc('last_activity')
            ->get(['id', 'ip_address', 'user_agent', 'last_activity'])
            ->map(fn (object $row): array => [
                'handle' => self::handle($row->id),
                ...self::describeAgent((string) $row->user_agent),
                'ip_address' => $row->ip_address,
                'last_active_at' => Carbon::createFromTimestamp($row->last_activity)->toIso8601String(),
                'is_current' => $row->id === $currentId,
            ])
            ->sortByDesc('is_current')
            ->values()
            ->all();
    }

    /**
     * Ends every session of the user except the one making this request, and drops remember-me logins.
     */
    public static function forgetOthers(User $user, string $currentId): void
    {
        self::rotateRememberToken($user);

        if (self::isSupported()) {
            DB::table(self::table())->where('user_id', $user->id)->where('id', '!=', $currentId)->delete();
        }
    }

    public static function forgetAll(User $user): void
    {
        self::rotateRememberToken($user);

        if (self::isSupported()) {
            DB::table(self::table())->where('user_id', $user->id)->delete();
        }
    }

    /**
     * Ends one other session. The handle is an opaque stand-in for the session id, so ids never reach the browser.
     * The session making this request is never ended here.
     */
    public static function forget(User $user, string $handle, string $currentId): bool
    {
        if (! self::isSupported()) {
            return false;
        }

        $ids = DB::table(self::table())->where('user_id', $user->id)->where('id', '!=', $currentId)->pluck('id');
        $id = $ids->first(fn (string $candidate): bool => hash_equals(self::handle($candidate), $handle));

        if ($id === null) {
            return false;
        }

        self::rotateRememberToken($user);
        DB::table(self::table())->where('id', $id)->delete();

        return true;
    }

    private static function rotateRememberToken(User $user): void
    {
        $user->forceFill(['remember_token' => Str::random(60)])->save();
    }

    private static function handle(string $sessionId): string
    {
        return hash_hmac('sha256', $sessionId, (string) config('app.key'));
    }

    private static function table(): string
    {
        return (string) config('session.table', 'sessions');
    }

    /**
     * Browser and system from the user agent, by plain string matching. Order matters: Edge and Opera also say
     * Chrome, Chrome also says Safari, and Android also says Linux.
     *
     * @return array{browser: string, platform: string}
     */
    private static function describeAgent(string $agent): array
    {
        $browser = match (true) {
            str_contains($agent, 'Edg') => 'Edge',
            str_contains($agent, 'OPR') || str_contains($agent, 'Opera') => 'Opera',
            str_contains($agent, 'Firefox') || str_contains($agent, 'FxiOS') => 'Firefox',
            str_contains($agent, 'Chrome') || str_contains($agent, 'CriOS') => 'Chrome',
            str_contains($agent, 'Safari') => 'Safari',
            default => 'Browser tak dikenal',
        };

        $platform = match (true) {
            str_contains($agent, 'Windows') => 'Windows',
            str_contains($agent, 'iPhone') => 'iPhone',
            str_contains($agent, 'iPad') => 'iPad',
            str_contains($agent, 'Android') => 'Android',
            str_contains($agent, 'Mac OS X') || str_contains($agent, 'Macintosh') => 'macOS',
            str_contains($agent, 'CrOS') => 'ChromeOS',
            str_contains($agent, 'Linux') => 'Linux',
            default => 'perangkat tak dikenal',
        };

        return ['browser' => $browser, 'platform' => $platform];
    }
}
