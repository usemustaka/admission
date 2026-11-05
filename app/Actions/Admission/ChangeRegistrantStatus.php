<?php

namespace App\Actions\Admission;

use App\Enums\RegistrantStatus;
use App\Models\Registrant;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ChangeRegistrantStatus
{
    /**
     * Statuses a registration cannot reach while the committee is still matching the child's NIK: they all
     * speak to the family on record, which may be someone else's.
     *
     * @var list<RegistrantStatus>
     */
    private const BLOCKED_WHILE_IDENTITY_UNCONFIRMED = [RegistrantStatus::Validated, RegistrantStatus::Announced, RegistrantStatus::Placed];

    /**
     * Move a registration to another status and write it to the history. Outside a bypass the move has to
     * follow the flow; a bypass can go anywhere (except into or out of Placed, which is done by placing the
     * student) but always needs a note. Nothing reaches Valid, Announced or Placed while the NIK is unconfirmed.
     *
     * The registration is read again under a row lock, so the status checked is the one that is stored right
     * now: a double click or a second operator gets a validation error instead of a second history row.
     *
     * @throws ValidationException
     */
    public function handle(Registrant $registrant, RegistrantStatus $to, ?User $by = null, ?string $note = null, bool $bypass = false): void
    {
        $note = filled($note) ? trim((string) $note) : null;

        DB::transaction(function () use ($registrant, $to, $by, $note, $bypass): void {
            $fresh = Registrant::query()->lockForUpdate()->findOrFail($registrant->getKey());
            $registrant->setRawAttributes($fresh->getAttributes(), sync: true);

            $from = $registrant->status;

            $this->guard($registrant, $from, $to, $note, $bypass);

            $registrant->update([
                'status' => $to,
                'submitted_at' => $to === RegistrantStatus::Review ? now() : $registrant->submitted_at,
            ]);

            $registrant->statusChanges()->create([
                'user_id' => $by?->id,
                'from_status' => $from,
                'to_status' => $to,
                'note' => $note,
                'is_bypass' => $bypass,
            ]);
        });
    }

    /**
     * @throws ValidationException
     */
    private function guard(Registrant $registrant, RegistrantStatus $from, RegistrantStatus $to, ?string $note, bool $bypass): void
    {
        if ($bypass) {
            if ($note === null) {
                throw ValidationException::withMessages(['note' => 'Tulis catatan kenapa status dilompati.']);
            }

            if ($from === $to) {
                throw ValidationException::withMessages(['status' => 'Pendaftaran ini sudah berstatus itu.']);
            }

            if ($from === RegistrantStatus::Placed || $to === RegistrantStatus::Placed) {
                throw ValidationException::withMessages(['status' => 'Penempatan dilakukan lewat tombol Tempatkan siswa, tidak lewat lompat status.']);
            }
        } elseif (! in_array($to, $from->nextInFlow(), true)) {
            throw ValidationException::withMessages(['status' => "Pendaftaran yang berstatus \"{$from->label()}\" tidak bisa langsung dipindah ke \"{$to->label()}\"."]);
        }

        if ($registrant->needs_identity_review && in_array($to, self::BLOCKED_WHILE_IDENTITY_UNCONFIRMED, true)) {
            throw ValidationException::withMessages(['status' => "Selesaikan dulu pencocokan NIK dan nama anak sebelum memindahkan ke \"{$to->label()}\"."]);
        }
    }
}
