<?php

namespace App\Actions\Admission;

use App\Actions\Notes\WritePersonAuditNote;
use App\Models\Student;
use App\Models\User;
use App\Models\Year;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CorrectStudent
{
    public function __construct(private WritePersonAuditNote $writeAuditNote) {}

    /**
     * Correct the student number and the academic year of a student, and leave a private note on the child
     * saying what changed. The student is read again under a lock, so the note names the values that were
     * really replaced. Returns whether anything changed.
     *
     * @throws ValidationException
     */
    public function handle(Student $student, User $by, string $nis, Year $year): bool
    {
        $nis = trim($nis);

        if ($nis === '') {
            throw ValidationException::withMessages(['nis' => 'Isi nomor induk siswa.']);
        }

        try {
            return DB::transaction(function () use ($student, $by, $nis, $year): bool {
                $fresh = Student::query()->lockForUpdate()->with(['year', 'organization', 'person'])->findOrFail($student->getKey());

                if (Student::query()->where('nis', $nis)->whereKeyNot($fresh->id)->exists()) {
                    throw ValidationException::withMessages(['nis' => 'Nomor induk ini sudah dipakai siswa lain.']);
                }

                $changes = [];

                if ($fresh->nis !== $nis) {
                    $changes[] = 'NIS diubah dari '.($fresh->nis ?? '(kosong)')." ke {$nis}.";
                }

                if ($fresh->year_id !== $year->id) {
                    $changes[] = 'Tahun ajaran diubah dari '.($fresh->year?->name ?? '(kosong)')." ke {$year->name}.";
                }

                if ($changes === []) {
                    return false;
                }

                $fresh->update(['nis' => $nis, 'year_id' => $year->id]);

                $this->writeAuditNote->handle(
                    $fresh->person,
                    $by,
                    "Koreksi data siswa di {$fresh->organization->name}. ".implode(' ', $changes),
                );

                return true;
            });
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages(['nis' => 'Nomor induk ini baru saja dipakai siswa lain. Pakai nomor yang lain.']);
        }
    }
}
