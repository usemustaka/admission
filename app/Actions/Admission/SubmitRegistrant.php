<?php

namespace App\Actions\Admission;

use App\Enums\RegistrantStatus;
use App\Models\Registrant;
use App\Models\User;
use App\Support\RegistrantReadiness;
use Illuminate\Validation\ValidationException;

class SubmitRegistrant
{
    public function __construct(private ChangeRegistrantStatus $changeStatus) {}

    /**
     * A parent sends the data to the committee. Everything the flow asks for has to be there; the committee
     * can bypass this when it has a reason.
     *
     * @throws ValidationException
     */
    public function handle(Registrant $registrant, User $by): void
    {
        $missing = RegistrantReadiness::missing($registrant);

        if ($missing !== []) {
            throw ValidationException::withMessages(['status' => 'Data belum bisa dikirim. Yang masih kurang: '.implode(', ', $missing).'.']);
        }

        $this->changeStatus->handle($registrant, RegistrantStatus::Review, $by);
    }
}
