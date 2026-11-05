<?php

namespace App\Actions\Admission;

use App\Enums\RegistrantStatus;
use App\Models\Registrant;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class AnnounceRegistrant
{
    public function __construct(private ChangeRegistrantStatus $changeStatus) {}

    /**
     * The committee has sent the announcement (copied or through WhatsApp) and marks it as done.
     *
     * @throws ValidationException
     */
    public function handle(Registrant $registrant, User $by): void
    {
        if ($registrant->needs_identity_review) {
            throw ValidationException::withMessages(['status' => 'Selesaikan dulu pencocokan NIK dan nama anak.']);
        }

        $this->changeStatus->handle($registrant, RegistrantStatus::Announced, $by);
    }
}
