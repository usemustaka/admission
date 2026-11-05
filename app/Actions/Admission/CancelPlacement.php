<?php

namespace App\Actions\Admission;

use App\Actions\Notes\WritePersonAuditNote;
use App\Enums\RegistrantStatus;
use App\Models\Registrant;
use App\Models\Student;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class CancelPlacement
{
    public function __construct(private WritePersonAuditNote $writeAuditNote) {}

    /**
     * Undo a placement: the student is deleted and the registration goes back to Announced, so the child can be
     * placed again. This is not a status change the flow allows (nothing leaves Placed, not even a bypass), so it
     * writes the history row itself, marked as internal so the parent never reads the reason. The reason is
     * mandatory and also becomes a private note on the child.
     *
     * Everything is checked under row locks on the student and the registration. A student without a
     * registration (not placed through admission) cannot be undone from here.
     *
     * @throws ValidationException
     */
    public function handle(Student $student, User $by, string $reason): void
    {
        $reason = trim($reason);

        if ($reason === '') {
            throw ValidationException::withMessages(['reason' => 'Tulis alasan pembatalan penempatan.']);
        }

        $photoPath = DB::transaction(function () use ($student, $by, $reason): ?string {
            $fresh = Student::query()->lockForUpdate()->with(['person', 'organization', 'year'])->find($student->getKey());

            if ($fresh === null) {
                throw ValidationException::withMessages(['student' => 'Siswa ini sudah tidak ada. Muat ulang halamannya.']);
            }

            $registrant = $fresh->registrant_id === null ? null : Registrant::query()->lockForUpdate()->find($fresh->registrant_id);

            if ($registrant === null) {
                throw ValidationException::withMessages(['student' => 'Siswa ini tidak punya data pendaftaran, jadi penempatannya tidak bisa dibatalkan dari sini.']);
            }

            if ($registrant->status !== RegistrantStatus::Placed) {
                throw ValidationException::withMessages(['student' => 'Pendaftaran siswa ini tidak berstatus sudah ditempatkan, jadi tidak ada penempatan yang bisa dibatalkan.']);
            }

            $fresh->delete();

            $registrant->update(['status' => RegistrantStatus::Announced]);
            $registrant->statusChanges()->create([
                'user_id' => $by->id,
                'from_status' => RegistrantStatus::Placed,
                'to_status' => RegistrantStatus::Announced,
                'note' => "Penempatan dibatalkan: {$reason}",
                'is_bypass' => true,
            ]);

            $this->writeAuditNote->handle(
                $fresh->person,
                $by,
                'Penempatan di '.$fresh->organization->name.($fresh->year === null ? '' : ", tahun ajaran {$fresh->year->name}").($fresh->nis === null ? '' : ", NIS {$fresh->nis}").' dibatalkan. Pendaftar kembali berstatus sudah diumumkan. Alasan: '.$reason,
            );

            return $fresh->photo_path;
        });

        if ($photoPath !== null && ! Student::query()->where('photo_path', $photoPath)->exists()) {
            Storage::disk('public')->delete($photoPath);
        }
    }
}
