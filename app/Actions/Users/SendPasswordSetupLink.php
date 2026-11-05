<?php

namespace App\Actions\Users;

use App\Models\User;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;

/**
 * Mails the reset link that lets a staff member choose their own password.
 */
class SendPasswordSetupLink
{
    /**
     * @throws ValidationException
     */
    public function handle(User $user): void
    {
        $status = Password::sendResetLink(['email' => $user->email]);

        if ($status === Password::RESET_THROTTLED) {
            throw ValidationException::withMessages(['user' => 'Tautan baru saja dikirim. Tunggu satu menit lalu coba lagi.']);
        }

        if ($status !== Password::RESET_LINK_SENT) {
            throw ValidationException::withMessages(['user' => 'Tautan atur password belum bisa dikirim. Coba lagi sebentar lagi.']);
        }
    }
}
