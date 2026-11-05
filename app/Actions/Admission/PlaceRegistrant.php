<?php

namespace App\Actions\Admission;

use App\Enums\RegistrantStatus;
use App\Models\Registrant;
use App\Models\Student;
use App\Models\User;
use App\Models\Year;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class PlaceRegistrant
{
    public function __construct(private ChangeRegistrantStatus $changeStatus) {}

    /**
     * Place an announced registration into an academic year: the child becomes a student of the organization,
     * with the formal photo from the registration as the student's own copy.
     *
     * @throws ValidationException
     */
    public function handle(Registrant $registrant, User $by, Year $year, string $nis): Student
    {
        $nis = trim($nis);

        if ($registrant->needs_identity_review) {
            throw ValidationException::withMessages(['status' => 'Selesaikan dulu pencocokan NIK dan nama anak.']);
        }

        if ($registrant->status !== RegistrantStatus::Announced) {
            throw ValidationException::withMessages(['status' => 'Hanya pendaftaran yang sudah diumumkan yang bisa ditempatkan.']);
        }

        if ($registrant->formal_photo_path === null) {
            throw ValidationException::withMessages(['status' => 'Pas foto anak belum ada. Unggah dulu lewat tombol ubah data.']);
        }

        if ($nis === '') {
            throw ValidationException::withMessages(['nis' => 'Isi nomor induk siswa.']);
        }

        if (Student::query()->where('nis', $nis)->exists()) {
            throw ValidationException::withMessages(['nis' => 'Nomor induk ini sudah dipakai siswa lain.']);
        }

        if (Student::query()->where('person_id', $registrant->person_id)->where('organization_id', $registrant->organization_id)->exists()) {
            throw ValidationException::withMessages(['status' => 'Anak ini sudah tercatat sebagai siswa di lembaga ini.']);
        }

        return DB::transaction(function () use ($registrant, $by, $year, $nis): Student {
            $photoPath = 'students/photos/'.basename($registrant->formal_photo_path);
            $disk = Storage::disk('public');

            if (! $disk->exists($photoPath) && $disk->exists($registrant->formal_photo_path)) {
                $disk->copy($registrant->formal_photo_path, $photoPath);
            }

            $student = Student::query()->create([
                'person_id' => $registrant->person_id,
                'organization_id' => $registrant->organization_id,
                'year_id' => $year->id,
                'registrant_id' => $registrant->id,
                'nis' => $nis,
                'photo_path' => $disk->exists($photoPath) ? $photoPath : null,
            ]);

            $this->changeStatus->handle($registrant, RegistrantStatus::Placed, $by);

            return $student;
        });
    }
}
