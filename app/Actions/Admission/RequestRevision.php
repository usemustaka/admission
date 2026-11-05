<?php

namespace App\Actions\Admission;

use App\Enums\RegistrantStatus;
use App\Models\Registrant;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class RequestRevision
{
    public function __construct(private ChangeRegistrantStatus $changeStatus) {}

    /**
     * The committee sends the data back with the reason, which the parent reads on the portal. While the NIK is
     * unconfirmed the parent cannot edit anything, so there is nothing for them to fix yet.
     *
     * @throws ValidationException
     */
    public function handle(Registrant $registrant, User $by, string $reason): void
    {
        if (trim($reason) === '') {
            throw ValidationException::withMessages(['note' => 'Tulis apa yang perlu diperbaiki.']);
        }

        if ($registrant->needs_identity_review) {
            throw ValidationException::withMessages(['status' => 'Selesaikan dulu pencocokan NIK dan nama anak sebelum meminta revisi.']);
        }

        $this->changeStatus->handle($registrant, RegistrantStatus::Revision, $by, $reason);
    }
}
