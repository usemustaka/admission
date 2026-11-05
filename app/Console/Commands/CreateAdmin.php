<?php

namespace App\Console\Commands;

use App\Enums\DefaultRole;
use App\Models\Person;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

use function Laravel\Prompts\password;

#[Signature('app:create-admin {email} {name} {--role=super-admin : super-admin, admin, atau operator}')]
#[Description('Buat akun admin baru dan beri perannya')]
class CreateAdmin extends Command
{
    public function handle(): int
    {
        $role = DefaultRole::tryFrom((string) $this->option('role'));

        if ($role === null || $role === DefaultRole::User) {
            $this->error('Peran harus super-admin, admin, atau operator.');

            return self::FAILURE;
        }

        $validator = Validator::make(
            ['email' => $this->argument('email')],
            ['email' => ['required', 'email', 'unique:users,email']],
        );

        if ($validator->fails()) {
            $this->error($validator->errors()->first('email'));

            return self::FAILURE;
        }

        $this->callSilent('db:seed', ['--class' => RolesAndPermissionsSeeder::class, '--force' => true]);

        $user = DB::transaction(function () use ($role): User {
            $person = Person::create([
                'name' => $this->argument('name'),
                'email' => $this->argument('email'),
            ]);

            $user = User::create([
                'person_id' => $person->id,
                'email' => $this->argument('email'),
                'password' => password('Kata sandi', required: true),
            ]);

            $user->forceFill(['email_verified_at' => now()])->save();
            $user->assignRole($role->value);

            return $user;
        });

        $this->info("Akun {$user->email} sudah dibuat dengan peran {$role->value}.");

        return self::SUCCESS;
    }
}
