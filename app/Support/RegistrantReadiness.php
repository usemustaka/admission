<?php

namespace App\Support;

use App\Enums\RegistrantStatus;
use App\Models\Registrant;

class RegistrantReadiness
{
    /**
     * What is still missing before a parent can send the data for review, in plain words.
     *
     * @return list<string>
     */
    public static function missing(Registrant $registrant): array
    {
        $missing = [];

        if ($registrant->needs_identity_review) {
            return ['Panitia masih mencocokkan data anak'];
        }

        if (! $registrant->isRegistrationBillSettled()) {
            $missing[] = 'Tagihan pendaftaran belum lunas';
        }

        $person = $registrant->person;

        foreach (RegistrantPresenter::CHILD_REQUIRED as $field => $label) {
            if (blank($person->getAttribute($field))) {
                $missing[] = "{$label} anak";
            }
        }

        if (! $person->parentships()->exists()) {
            $missing[] = 'Data orang tua atau wali';
        }

        if ($registrant->hasExamSchedule() && $registrant->exam_session_id === null) {
            $missing[] = 'Jadwal ujian';
        }

        return $missing;
    }

    /**
     * What the committee still has to look at: the gaps are shown in every status before the result is announced,
     * so the member who accepts the data sees what is missing. Afterwards the data is settled and nothing is listed.
     *
     * @return list<string>
     */
    public static function forCommittee(Registrant $registrant): array
    {
        $open = [RegistrantStatus::Draft, RegistrantStatus::Revision, RegistrantStatus::Review, RegistrantStatus::Validated];

        return in_array($registrant->status, $open, true) ? self::missing($registrant) : [];
    }
}
