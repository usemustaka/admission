<?php

namespace App\Actions\Academic;

use App\Models\Organization;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DeleteOrganization
{
    /**
     * Delete an organization nobody refers to. One that has rulesets, registrations, students or employees stays,
     * and the committee is pointed to deactivating it instead.
     *
     * @throws ValidationException
     */
    public function handle(Organization $organization): void
    {
        DB::transaction(function () use ($organization): void {
            $fresh = Organization::query()->lockForUpdate()->findOrFail($organization->id);

            if ($fresh->isInUse()) {
                throw ValidationException::withMessages(['organization' => 'Lembaga ini sudah dipakai aturan, pendaftar, siswa, atau pegawai, jadi tidak bisa dihapus. Nonaktifkan saja supaya tidak muncul di pendaftaran baru.']);
            }

            $fresh->delete();
        });
    }
}
