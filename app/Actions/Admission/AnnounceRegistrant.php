<?php

namespace App\Actions\Admission;

use App\Enums\RegistrantStatus;
use App\Models\Registrant;
use App\Models\User;
use App\Support\AnnouncementReadiness;
use Illuminate\Validation\ValidationException;

class AnnounceRegistrant
{
    public function __construct(private ChangeRegistrantStatus $changeStatus) {}

    /**
     * The committee has sent the announcement (copied or through WhatsApp) and marks it as done. Announcing a
     * registration that is not complete (no scores, not passed, a bill still open) is allowed; callers that run
     * many at once pass `requireComplete` to refuse those instead (see `AnnouncementReadiness`).
     *
     * @throws ValidationException
     */
    public function handle(Registrant $registrant, User $by, bool $requireComplete = false): void
    {
        if ($registrant->needs_identity_review) {
            throw ValidationException::withMessages(['status' => 'Selesaikan dulu pencocokan NIK dan nama anak.']);
        }

        if ($requireComplete && ($reason = AnnouncementReadiness::incompleteReason($registrant)) !== null) {
            throw ValidationException::withMessages(['status' => $reason]);
        }

        $this->changeStatus->handle($registrant, RegistrantStatus::Announced, $by);
    }
}
