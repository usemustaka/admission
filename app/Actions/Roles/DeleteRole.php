<?php

namespace App\Actions\Roles;

use App\Models\NoteShare;
use App\Support\RoleRules;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Deletes a custom role that nobody uses, along with the note shares that pointed at it.
 */
class DeleteRole
{
    /**
     * @throws ValidationException
     */
    public function handle(Role $role): void
    {
        if (RoleRules::isLocked($role) || RoleRules::isBuiltIn($role)) {
            throw ValidationException::withMessages(['role' => 'Peran bawaan tidak bisa dihapus.']);
        }

        $users = $role->users()->count();

        if ($users > 0) {
            throw ValidationException::withMessages(['role' => "Peran ini masih dipakai {$users} akun. Pindahkan akunnya ke peran lain dulu."]);
        }

        DB::transaction(function () use ($role): void {
            NoteShare::query()->where('shareable_type', $role->getMorphClass())->where('shareable_id', $role->id)->delete();

            $role->delete();
        });

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
