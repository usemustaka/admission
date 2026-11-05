<?php

namespace App\Support;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Payment;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/**
 * The payments of one calendar day as the cashier closes the books. Money counts on the day it was verified, in the
 * application's timezone (WIB), since that is when it was accepted. Payments received that day but still waiting for
 * verification are kept apart: they are not money yet.
 *
 * Both queries are ranges on the column (from the start of the day up to the start of the next), so an index stays usable.
 */
class PaymentReconciliation
{
    /** The most rows the page lists per block; the totals always cover everything. */
    public const LIST_LIMIT = 500;

    public function __construct(public readonly CarbonImmutable $day) {}

    public static function fromRequest(Request $request): self
    {
        $date = PaymentFilters::date($request->query('tanggal'));

        return new self($date === null ? CarbonImmutable::today() : CarbonImmutable::createFromFormat('!Y-m-d', $date));
    }

    /**
     * Payments verified (and still verified) on the day.
     *
     * @return Builder<Payment>
     */
    public function verified(): Builder
    {
        return Payment::query()
            ->where('status', PaymentStatus::Verified)
            ->where('verified_at', '>=', $this->day->startOfDay())
            ->where('verified_at', '<', $this->day->addDay()->startOfDay());
    }

    /**
     * Payments received on the day that still wait for verification.
     *
     * @return Builder<Payment>
     */
    public function pending(): Builder
    {
        return Payment::query()
            ->where('status', PaymentStatus::Pending)
            ->where('paid_on', '>=', $this->day->toDateString())
            ->where('paid_on', '<', $this->day->addDay()->toDateString());
    }

    /**
     * Count and total of the verified payments per method, with every method present even when it has none.
     *
     * @return list<array{value: string, label: string, count: int, total: int}>
     */
    public function methodTotals(): array
    {
        $rows = $this->verified()
            ->toBase()
            ->selectRaw('method, count(*) as total_count, coalesce(sum(amount), 0) as total_amount')
            ->groupBy('method')
            ->get()
            ->keyBy('method');

        return array_map(fn (PaymentMethod $method): array => [
            'value' => $method->value,
            'label' => $method->label(),
            'count' => (int) ($rows->get($method->value)->total_count ?? 0),
            'total' => (int) ($rows->get($method->value)->total_amount ?? 0),
        ], PaymentMethod::cases());
    }

    /**
     * Whole days a payment has waited since it was paid, counted against today.
     */
    public static function ageInDays(Payment $payment): int
    {
        return max(0, (int) $payment->paid_on->startOfDay()->diffInDays(CarbonImmutable::today()));
    }
}
