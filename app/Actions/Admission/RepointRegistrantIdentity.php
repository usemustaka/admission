<?php

namespace App\Actions\Admission;

use App\Actions\Notes\WritePersonAuditNote;
use App\Actions\Portal\LinkRegisteringParent;
use App\Enums\RegistrantStatus;
use App\Models\Person;
use App\Models\Registrant;
use App\Models\User;
use App\Support\IdentityMismatchNote;
use App\Support\PersonName;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RepointRegistrantIdentity
{
    public function __construct(
        private LinkRegisteringParent $linkRegisteringParent,
        private WritePersonAuditNote $writePersonAuditNote,
    ) {}

    /**
     * The NIK the parent typed was wrong: point the registration at the child who owns the right NIK, or at a
     * new person with the typed name when nobody has that NIK yet. The flag stays on when the name of the person
     * who owns the new NIK still differs from the typed one; otherwise the registering parent is linked to the
     * child. Both children get a private note about it and the old warning is closed.
     *
     * The registration is read again under a row lock, so the status checked is the one stored right now.
     *
     * @throws ValidationException
     */
    public function handle(Registrant $registrant, User $by, string $nik, string $reason): void
    {
        $nik = trim($nik);
        $reason = trim($reason);

        DB::transaction(function () use ($registrant, $by, $nik, $reason): void {
            $fresh = Registrant::query()->lockForUpdate()->findOrFail($registrant->getKey());
            $registrant->setRawAttributes($fresh->getAttributes(), sync: true);
            $registrant->unsetRelation('person');

            $this->guard($registrant, $nik);

            $previous = $registrant->person;
            $target = Person::query()->where('nik', $nik)->first();
            $created = $target === null;

            $target ??= Person::create(['nik' => $nik, 'name' => $registrant->submitted_name]);

            $stillMismatched = ! $created && ! PersonName::same($target->name, $registrant->submitted_name);

            $registrant->update(['person_id' => $target->id, 'needs_identity_review' => $stillMismatched]);
            $registrant->setRelation('person', $target);

            if ($stillMismatched) {
                IdentityMismatchNote::open($target, $registrant->submitted_name, $nik);
            } else {
                $this->linkRegisteringParent->handle($registrant);
            }

            IdentityMismatchNote::close($previous, $registrant->submitted_name, $by, "pendaftaran dialihkan ke NIK {$nik}.");

            $label = $this->label($registrant);

            $this->writePersonAuditNote->handle(
                $previous,
                $by,
                "Pendaftaran {$label} atas nama \"{$registrant->submitted_name}\" dialihkan dari anak ini ke NIK {$nik} ({$target->name}). Alasan: {$reason}",
            );
            $this->writePersonAuditNote->handle(
                $target,
                $by,
                "Pendaftaran {$label} atas nama \"{$registrant->submitted_name}\" dialihkan ke anak ini dari NIK ".($previous->nik ?? '-')." ({$previous->name}). Alasan: {$reason}",
            );
        });
    }

    /**
     * @throws ValidationException
     */
    private function guard(Registrant $registrant, string $nik): void
    {
        if ($registrant->status === RegistrantStatus::Placed) {
            throw ValidationException::withMessages(['nik' => 'Pendaftar yang sudah ditempatkan tidak bisa dialihkan. Batalkan penempatannya dulu.']);
        }

        if (! $registrant->needs_identity_review) {
            throw ValidationException::withMessages(['nik' => 'Pendaftaran ini tidak sedang menunggu pencocokan NIK.']);
        }

        if ($registrant->person->nik === $nik) {
            throw ValidationException::withMessages(['nik' => 'NIK ini sama dengan NIK pendaftaran sekarang. Kalau datanya memang benar, pilih "NIK sudah cocok".']);
        }

        $target = Person::query()->where('nik', $nik)->first();

        if ($target !== null && $target->id === $registrant->registered_by_person_id) {
            throw ValidationException::withMessages(['nik' => 'NIK ini adalah NIK orang tua yang mendaftarkan. Isi dengan NIK anak.']);
        }

        if ($target?->registrants()->where('organization_id', $registrant->organization_id)->where('wave_id', $registrant->wave_id)->exists()) {
            throw ValidationException::withMessages(['nik' => 'Anak dengan NIK ini sudah terdaftar di lembaga dan gelombang yang sama.']);
        }
    }

    private function label(Registrant $registrant): string
    {
        $registrant->loadMissing(['organization', 'wave']);

        return "{$registrant->organization->name} ({$registrant->wave->name})";
    }
}
