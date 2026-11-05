<?php

namespace App\Actions\Users;

use App\Enums\DefaultRole;
use App\Models\User;
use App\Support\ActiveSuperAdmins;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

/**
 * Turns an account off without deleting it, so the notes, payments and history it wrote keep their author. The
 * account gets the `nonaktif` role, which makes every permission check fail, and loses its sessions, remember
 * token and two-factor secret.
 */
class DeactivateUser
{
    /**
     * @throws ValidationException
     */
    public function handle(User $user): void
    {
        if ($user->hasRole(DefaultRole::Inactive->value)) {
            return;
        }

        if (ActiveSuperAdmins::isLast($user)) {
            throw ValidationException::withMessages(['user' => 'Super admin terakhir tidak bisa dinonaktifkan. Tambahkan super admin lain dulu.']);
        }

        DB::transaction(function () use ($user): void {
            $user->assignRole(DefaultRole::Inactive->value);

            $user->forceFill([
                'remember_token' => null,
                'two_factor_secret' => null,
                'two_factor_recovery_codes' => null,
                'two_factor_confirmed_at' => null,
            ])->save();
        });

        if (config('session.driver') === 'database' && Schema::hasTable(config('session.table', 'sessions'))) {
            DB::table(config('session.table', 'sessions'))->where('user_id', $user->id)->delete();
        }
    }
}
