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
     * The committee sends the data back with the reason, which the parent reads on the portal.
     *
     * @throws ValidationException
     */
    public function handle(Registrant $registrant, User $by, string $reason): void
    {
        if (trim($reason) === '') {
            throw ValidationException::withMessages(['note' => 'Tulis apa yang perlu diperbaiki.']);
        }

        $this->changeStatus->handle($registrant, RegistrantStatus::Revision, $by, $reason);
    }
}
