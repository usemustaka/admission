<?php

namespace App\Support;

use App\Enums\BillStatus;
use App\Models\Bill;
use App\Models\Registrant;

/**
 * Whether a registration is complete enough to be announced: every exam aspect has a score, the result is a pass,
 * and no bill is left open. Announcing is still allowed when it is not; this tells the committee what is missing.
 */
class AnnouncementReadiness
{
    public const MISSING_SCORES = 'Nilai ujian belum lengkap';

    public const NOT_PASSED = 'Belum tuntas';

    public const UNSETTLED = 'Tagihan belum lunas';

    /**
     * `missing_scores` only applies when the ruleset copy has exam aspects, `not_passed` once every score is in and
     * one is under its KKM, and `unsettled` when some bill (registration or settlement) still asks for money.
     *
     * @return array{missing_scores: bool, not_passed: bool, unsettled: bool, messages: list<string>}
     */
    public static function for(Registrant $registrant): array
    {
        $result = ExamResult::for($registrant);

        $missingScores = $result['aspects'] !== [] && ! $result['complete'];
        $notPassed = $result['passed'] === false;
        $unsettled = self::hasOpenBill($registrant);

        return [
            'missing_scores' => $missingScores,
            'not_passed' => $notPassed,
            'unsettled' => $unsettled,
            'messages' => array_values(array_filter([
                $missingScores ? self::MISSING_SCORES : null,
                $notPassed ? self::NOT_PASSED : null,
                $unsettled ? self::UNSETTLED : null,
            ])),
        ];
    }

    /**
     * Why a registration is not ready, as one line for a skipped list; null when nothing is missing.
     *
     * @param  array{messages: list<string>}  $readiness
     */
    public static function reason(array $readiness): ?string
    {
        return $readiness['messages'] === [] ? null : 'Belum lengkap: '.mb_strtolower(implode(', ', $readiness['messages']));
    }

    public static function incompleteReason(Registrant $registrant): ?string
    {
        return self::reason(self::for($registrant));
    }

    private static function hasOpenBill(Registrant $registrant): bool
    {
        $registrant->loadMissing(['bills.items' => fn ($items) => $items->withPaymentTotals()]);

        return $registrant->bills->contains(fn (Bill $bill): bool => $bill->status() !== BillStatus::Paid);
    }
}
