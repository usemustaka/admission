<?php

namespace App\Actions\Users;

use App\Models\Person;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;

/**
 * Creates the login of a staff member and gives it a role. An existing person with the same email is reused
 * instead of duplicated. Without a password the account gets an unusable one and the person receives a link to
 * choose their own; with one (the console command) no mail is sent.
 */
class CreateStaffAccount
{
    public function __construct(private SendPasswordSetupLink $sendSetupLink) {}

    /**
     * @throws ValidationException when the email is taken or the person already has a login
     */
    public function handle(string $name, string $email, string $role, ?string $password = null): User
    {
        $email = Str::lower(trim($email));

        if (User::query()->whereRaw('lower(email) = ?', [$email])->exists()) {
            throw ValidationException::withMessages(['email' => 'Email ini sudah dipakai akun lain.']);
        }

        $person = Person::query()->whereRaw('lower(email) = ?', [$email])->first();

        if ($person?->user()->exists()) {
            throw ValidationException::withMessages(['email' => 'Orang dengan email ini sudah punya akun dengan email lain. Pakai akun itu saja.']);
        }

        $role = Role::findByName($role, 'web');

        $user = DB::transaction(function () use ($name, $email, $password, $person, $role): User {
            $person ??= Person::create(['name' => $name, 'email' => $email]);

            $user = User::create([
                'person_id' => $person->id,
                'email' => $email,
                'password' => $password ?? Str::password(40),
            ]);

            $user->forceFill(['email_verified_at' => now()])->save();
            $user->assignRole($role);

            return $user;
        });

        if ($password === null) {
            $this->sendSetupLink->handle($user);
        }

        return $user;
    }
}
