<?php

namespace App\Actions\Academic;

use App\Models\Year;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DeleteYear
{
    /**
     * Delete a year together with its periods. A year that still has students, or the one that is active, stays.
     *
     * @throws ValidationException
     */
    public function handle(Year $year): void
    {
        DB::transaction(function () use ($year): void {
            $fresh = Year::query()->lockForUpdate()->findOrFail($year->id);

            if ($fresh->is_active) {
                throw ValidationException::withMessages(['year' => 'Tahun ajaran yang aktif tidak bisa dihapus. Aktifkan tahun ajaran lain dulu.']);
            }

            $students = $fresh->students()->count();

            if ($students > 0) {
                throw ValidationException::withMessages(['year' => "Tahun ajaran ini masih dipakai {$students} siswa, jadi tidak bisa dihapus. Pindahkan tahun siswanya dulu."]);
            }

            $fresh->delete();
        });
    }
}
