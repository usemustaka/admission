<?php

namespace App\Actions\Admission;

use App\Actions\Billing\IssueBill;
use App\Enums\BillType;
use App\Enums\RegistrantStatus;
use App\Models\Registrant;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class BypassRegistrantStatus
{
    public function __construct(
        private ChangeRegistrantStatus $changeStatus,
        private IssueBill $issueBill,
    ) {}

    /**
     * The committee moves a registration to any status, skipping what the flow would ask, with a note that stays
     * in the history. Moving to a status that comes after the data was validated also issues the settlement bill,
     * so the registration is never left without it.
     *
     * @throws ValidationException
     */
    public function handle(Registrant $registrant, User $by, RegistrantStatus $to, string $note): void
    {
        DB::transaction(function () use ($registrant, $by, $to, $note): void {
            $this->changeStatus->handle($registrant, $to, $by, $note, bypass: true);

            if ($to === RegistrantStatus::Validated || $to === RegistrantStatus::Announced) {
                $this->issueBill->handle($registrant, BillType::Settlement);
            }
        });
    }
}
