<?php

namespace App\Actions\Admission;

use App\Actions\Billing\IssueBill;
use App\Enums\BillType;
use App\Enums\RegistrantStatus;
use App\Models\Registrant;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AcceptRegistrant
{
    public function __construct(
        private ChangeRegistrantStatus $changeStatus,
        private IssueBill $issueBill,
    ) {}

    /**
     * The committee finds the data valid. The settlement bill is issued from the ruleset copy on the registration.
     *
     * @throws ValidationException
     */
    public function handle(Registrant $registrant, User $by): void
    {
        $this->guardIdentity($registrant);

        DB::transaction(function () use ($registrant, $by): void {
            $this->changeStatus->handle($registrant, RegistrantStatus::Validated, $by);
            $this->issueBill->handle($registrant, BillType::Settlement);
        });
    }

    /**
     * @throws ValidationException
     */
    private function guardIdentity(Registrant $registrant): void
    {
        if ($registrant->needs_identity_review) {
            throw ValidationException::withMessages(['status' => 'Selesaikan dulu pencocokan NIK dan nama anak sebelum menerima data.']);
        }
    }
}
