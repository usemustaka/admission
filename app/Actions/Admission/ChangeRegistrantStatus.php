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
     * Move a registration to another status and write it to the history. Outside a bypass the move has to
     * follow the flow; a bypass can go anywhere (except into or out of Placed, which is done by placing the
     * student) but always needs a note.
     *
     * @throws ValidationException
     */
    public function handle(Registrant $registrant, RegistrantStatus $to, ?User $by = null, ?string $note = null, bool $bypass = false): void
    {
        $from = $registrant->status;
        $note = filled($note) ? trim((string) $note) : null;

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

        DB::transaction(function () use ($registrant, $from, $to, $by, $note, $bypass): void {
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
}
