<?php

namespace App\Support;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Payment;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Throwable;

/**
 * What the committee narrowed the payment list to, read leniently from the query string (Indonesian keys): a
 * malformed value simply means "no filter" instead of an error page. The list, its summary, the quick view queue
 * and the CSV export all share this so they always talk about the same payments.
 */
class PaymentFilters
{
    public const SORT_DATE = 'tanggal';

    public const SORT_AMOUNT = 'nominal';

    /**
     * @param  'asc'|'desc'  $direction
     */
    public function __construct(
        public readonly ?PaymentStatus $status = null,
        public readonly string $search = '',
        public readonly ?PaymentMethod $method = null,
        public readonly ?string $from = null,
        public readonly ?string $until = null,
        public readonly string $sort = self::SORT_DATE,
        public readonly string $direction = 'desc',
    ) {}

    public static function fromRequest(Request $request): self
    {
        $sort = $request->query('urut') === self::SORT_AMOUNT ? self::SORT_AMOUNT : self::SORT_DATE;

        return new self(
            status: PaymentStatus::tryFrom((string) $request->query('status')),
            search: mb_substr(trim((string) $request->query('cari')), 0, 100),
            method: PaymentMethod::tryFrom((string) $request->query('cara')),
            from: self::date($request->query('dari')),
            until: self::date($request->query('sampai')),
            sort: $sort,
            direction: $request->query('arah') === 'asc' ? 'asc' : 'desc',
        );
    }

    private static function date(mixed $value): ?string
    {
        if (! is_string($value) || preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) !== 1) {
            return null;
        }

        try {
            $date = CarbonImmutable::createFromFormat('!Y-m-d', $value);
        } catch (Throwable) {
            return null;
        }

        return $date !== null && $date->toDateString() === $value ? $value : null;
    }

    /**
     * The payments that match, without ordering. Leave out the status to count every status in the same scope.
     * `paid_on` is compared as a range (from the day, up to before the day after) so it never needs a function on
     * the column.
     *
     * @return Builder<Payment>
     */
    public function query(bool $withStatus = true): Builder
    {
        return Payment::query()
            ->when($withStatus && $this->status !== null, fn (Builder $query) => $query->where('status', $this->status))
            ->when($this->method !== null, fn (Builder $query) => $query->where('method', $this->method))
            ->when($this->from !== null, fn (Builder $query) => $query->where('paid_on', '>=', $this->from))
            ->when($this->until !== null, fn (Builder $query) => $query->where('paid_on', '<', CarbonImmutable::parse($this->until)->addDay()->toDateString()))
            ->when($this->search !== '', function (Builder $query): void {
                $like = '%'.addcslashes($this->search, '%_\\').'%';

                $query->where(function (Builder $query) use ($like): void {
                    $query->whereHas('payer', fn (Builder $payers) => $payers->where('name', 'like', $like))
                        ->orWhereHas('allocations.billItem.bill.registrant', function (Builder $registrants) use ($like): void {
                            $registrants->where('submitted_name', 'like', $like)
                                ->orWhereHas('person', fn (Builder $people) => $people->where('name', 'like', $like));
                        });
                });
            });
    }

    /**
     * The matching payments in the order the list shows them. Ties break on the newest first so paging is stable.
     *
     * @return Builder<Payment>
     */
    public function ordered(bool $withStatus = true): Builder
    {
        $column = $this->sort === self::SORT_AMOUNT ? 'amount' : 'paid_on';

        return $this->query($withStatus)->orderBy($column, $this->direction)->orderByDesc('id');
    }

    public function isActive(): bool
    {
        return $this->status !== null || $this->hasNarrowing();
    }

    /**
     * Whether anything besides the status is set, which the "reset" button clears.
     */
    public function hasNarrowing(): bool
    {
        return $this->search !== '' || $this->method !== null || $this->from !== null || $this->until !== null;
    }

    /**
     * The values the page puts back into its controls.
     *
     * @return array<string, string>
     */
    public function toProps(): array
    {
        return [
            'status' => $this->status->value ?? '',
            'cari' => $this->search,
            'cara' => $this->method->value ?? '',
            'dari' => $this->from ?? '',
            'sampai' => $this->until ?? '',
            'urut' => $this->sort,
            'arah' => $this->direction,
        ];
    }

    /**
     * Only the narrowing values, as a query string for links (status, search, method and dates).
     *
     * @return array<string, string>
     */
    public function toQuery(): array
    {
        return array_filter([
            'status' => $this->status?->value,
            'cari' => $this->search,
            'cara' => $this->method?->value,
            'dari' => $this->from,
            'sampai' => $this->until,
        ], fn (?string $value): bool => $value !== null && $value !== '');
    }
}
