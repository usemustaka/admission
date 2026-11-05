<?php

namespace App\Actions\Roles;

use App\Models\User;
use App\Support\RoleRules;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;

/**
 * Copies a role and its abilities under a free name such as "operator (salinan)".
 */
class DuplicateRole
{
    public function __construct(private SaveRole $saveRole) {}

    /**
     * @throws ValidationException
     */
    public function handle(User $actor, Role $role): Role
    {
        $abilities = RoleRules::abilitiesOf($role->loadMissing('permissions'));

        if (RoleRules::unheldBy($actor, $abilities) !== []) {
            throw ValidationException::withMessages(['role' => 'Peran ini berisi izin yang tidak kamu miliki, jadi tidak bisa kamu gandakan.']);
        }

        return $this->saveRole->handle($actor, null, $this->freeName($role->name), $abilities);
    }

    private function freeName(string $name): string
    {
        $candidate = null;

        for ($attempt = 1; $candidate === null || Role::query()->where('name', $candidate)->exists(); $attempt++) {
            $suffix = $attempt === 1 ? ' (salinan)' : " (salinan {$attempt})";
            $candidate = Str::limit($name, 50 - strlen($suffix), '').$suffix;
        }

        return $candidate;
    }
}
