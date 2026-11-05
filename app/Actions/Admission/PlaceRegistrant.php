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
     * with the child's profile photo copied as the student's own photo, when there is one.
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
            $disk = Storage::disk('public');
            $profilePath = $registrant->person->photo_path;
            $photoPath = $profilePath === null ? null : 'students/photos/'.basename($profilePath);

            if ($photoPath !== null && ! $disk->exists($photoPath) && $disk->exists($profilePath)) {
                $disk->copy($profilePath, $photoPath);
            }

            $student = Student::query()->create([
                'person_id' => $registrant->person_id,
                'organization_id' => $registrant->organization_id,
                'year_id' => $year->id,
                'registrant_id' => $registrant->id,
                'nis' => $nis,
                'photo_path' => $photoPath !== null && $disk->exists($photoPath) ? $photoPath : null,
            ]);

            $this->changeStatus->handle($registrant, RegistrantStatus::Placed, $by);

            return $student;
        });
    }
}
