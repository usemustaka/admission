<?php

namespace App\Console\Commands;

use App\Actions\Users\CreateStaffAccount;
use App\Enums\DefaultRole;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

use function Laravel\Prompts\password;

#[Signature('app:create-admin {email} {name} {--role=super-admin : Nama peran, misalnya super-admin, admin, atau operator} {--password= : Password untuk deploy tanpa prompt}')]
#[Description('Buat akun admin baru dan beri perannya')]
class CreateAdmin extends Command
{
    public function handle(CreateStaffAccount $createStaffAccount): int
    {
        $this->callSilent('db:seed', ['--class' => RolesAndPermissionsSeeder::class, '--force' => true]);

        $role = (string) $this->option('role');
        $validator = Validator::make(
            ['email' => $this->argument('email'), 'role' => $role],
            [
                'email' => ['required', 'email', Rule::unique(User::class, 'email')],
                'role' => ['required', Rule::exists('roles', 'name')->where('guard_name', 'web'), Rule::notIn([DefaultRole::User->value, DefaultRole::Inactive->value])],
            ],
            [
                'email.unique' => 'Email ini sudah dipakai akun lain.',
                'role.exists' => "Peran {$role} tidak ada.",
                'role.not_in' => 'Peran ini tidak bisa dipakai untuk staf.',
            ],
        );

        if ($validator->fails()) {
            $this->error($validator->errors()->first());

            return self::FAILURE;
        }

        $password = $this->resolvePassword();

        if ($password === null) {
            return self::FAILURE;
        }

        try {
            $user = $createStaffAccount->handle($this->argument('name'), $this->argument('email'), $role, $password);
        } catch (ValidationException $exception) {
            $this->error($exception->validator->errors()->first());

            return self::FAILURE;
        }

        $this->info("Akun {$user->email} sudah dibuat dengan peran {$role}.");

        return self::SUCCESS;
    }

    /**
     * The password from `--password`, or typed twice at a prompt. Both follow the same rules as the app.
     */
    private function resolvePassword(): ?string
    {
        $rules = ['required', 'string', Password::default()];
        $problem = fn (string $value): ?string => Validator::make(['password' => $value], ['password' => $rules], [], ['password' => 'Password'])->errors()->first('password') ?: null;

        $given = $this->option('password');

        if (is_string($given) && $given !== '') {
            if ($message = $problem($given)) {
                $this->error($message);

                return null;
            }

            return $given;
        }

        if (! $this->input->isInteractive()) {
            $this->error('Tidak bisa menanyakan password di sini. Berikan lewat opsi --password=.');

            return null;
        }

        $typed = password('Kata sandi', required: true, validate: fn (string $value): ?string => $problem($value));

        password('Ulangi kata sandi', required: true, validate: fn (string $value): ?string => $value === $typed ? null : 'Kata sandi tidak sama.');

        return $typed;
    }
}
