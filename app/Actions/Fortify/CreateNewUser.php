<?php

namespace App\Actions\Fortify;

use App\Concerns\PasswordValidationRules;
use App\Concerns\ProfileValidationRules;
use App\Enums\DefaultRole;
use App\Models\Person;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Laravel\Fortify\Contracts\CreatesNewUsers;
use Spatie\Permission\Models\Role;

class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules, ProfileValidationRules;

    /**
     * Validate and create a newly registered user.
     *
     * @param  array<string, string>  $input
     */
    public function create(array $input): User
    {
        Validator::make($input, [
            ...$this->profileRules(),
            'password' => $this->passwordRules(),
        ])->validate();

        return DB::transaction(function () use ($input): User {
            $person = Person::create([
                'name' => $input['name'],
                'email' => $input['email'],
            ]);

            $user = User::create([
                'person_id' => $person->id,
                'email' => $input['email'],
                'password' => $input['password'],
            ]);

            $user->assignRole(Role::findOrCreate(DefaultRole::User->value, 'web'));

            return $user;
        });
    }
}
