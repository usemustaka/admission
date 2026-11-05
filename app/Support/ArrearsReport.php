<?php

namespace App\Support;

use App\Enums\PaymentStatus;
use App\Models\Registrant;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Registrants with an open bill, one row each, with what they were billed, paid, have waiting for verification and
 * still owe. "Owe" is counted per bill item (an item paid beyond its amount never covers another item), exactly as
 * the registrant's own bills page does, so the report and the bills always agree. Everything is aggregated in SQL;
 * nothing is looped over in PHP except the page of rows that is shown.
 *
 * Age is the number of calendar days (WIB) since the oldest open bill was issued. A bill is open while any of its items
 * still asks for money. The filters come from the Indonesian query string and are shared by the page, its totals and
 * the CSV export.
 */
class ArrearsReport
{
    /** Age buckets, by the number of days since the oldest open bill was issued: `[from, to]` (null = no limit). */
    public const BUCKETS = [
        '0-7' => [0, 7],
        '8-30' => [8, 30],
        '31-60' => [31, 60],
        'lebih-60' => [61, null],
    ];

    public const BUCKET_LABELS = [
        '0-7' => '0–7 hari',
        '8-30' => '8–30 hari',
        '31-60' => '31–60 hari',
        'lebih-60' => 'Lebih dari 60 hari',
    ];

    public const SORT_OUTSTANDING = 'sisa';

    public const SORT_AGE = 'umur';

    public const SORT_NAME = 'nama';

    public function __construct(
        public readonly string $bucket = '',
        public readonly int $organizationId = 0,
        public readonly int $waveId = 0,
        public readonly string $search = '',
        public readonly string $sort = self::SORT_OUTSTANDING,
        public readonly string $direction = 'desc',
    ) {}

    public static function fromRequest(Request $request): self
    {
        $bucket = $request->string('umur')->toString();
        $sort = $request->string('urut')->toString();
        $sort = in_array($sort, [self::SORT_OUTSTANDING, self::SORT_AGE, self::SORT_NAME], true) ? $sort : self::SORT_OUTSTANDING;

        return new self(
            bucket: array_key_exists($bucket, self::BUCKETS) ? $bucket : '',
            organizationId: $request->integer('lembaga'),
            waveId: $request->integer('gelombang'),
            search: mb_substr(trim($request->string('cari')->toString()), 0, 100),
            sort: $sort,
            direction: match ($request->string('arah')->toString()) {
                'asc' => 'asc',
                'desc' => 'desc',
                default => $sort === self::SORT_NAME ? 'asc' : 'desc',
            },
        );
    }

    /**
     * Whether anything narrows the report (the age bucket included; ordering does not count).
     */
    public function isActive(): bool
    {
        return $this->bucket !== '' || $this->hasNarrowing();
    }

    /**
     * Whether anything besides the age bucket is set.
     */
    public function hasNarrowing(): bool
    {
        return $this->organizationId > 0 || $this->waveId > 0 || $this->search !== '';
    }

    /**
     * @return array<string, string|int>
     */
    public function toProps(): array
    {
        return [
            'umur' => $this->bucket,
            'lembaga' => $this->organizationId ?: '',
            'gelombang' => $this->waveId ?: '',
            'cari' => $this->search,
            'urut' => $this->sort,
            'arah' => $this->direction,
        ];
    }

    /**
     * Only the narrowing values, as a query string for links such as the CSV export.
     *
     * @return array<string, string|int>
     */
    public function toQuery(): array
    {
        return array_filter([
            'umur' => $this->bucket,
            'lembaga' => $this->organizationId ?: null,
            'gelombang' => $this->waveId ?: null,
            'cari' => $this->search,
        ], fn (mixed $value): bool => $value !== null && $value !== '');
    }

    /**
     * Registrants with an open bill that match the filters (the age bucket excluded, so every bucket can be counted in
     * the same scope), with `billed`, `paid`, `pending`, `outstanding`, `excess` and `oldest_open_at` selected.
     *
     * @return Builder<Registrant>
     */
    public function scope(): Builder
    {
        return Registrant::query()
            ->joinSub($this->perRegistrant(), 'arrears', 'arrears.registrant_id', '=', 'registrants.id')
            ->select('registrants.*', 'arrears.billed', 'arrears.paid', 'arrears.pending', 'arrears.outstanding', 'arrears.excess', 'arrears.oldest_open_at')
            ->when($this->organizationId > 0, fn (Builder $query) => $query->where('registrants.organization_id', $this->organizationId))
            ->when($this->waveId > 0, fn (Builder $query) => $query->where('registrants.wave_id', $this->waveId))
            ->when($this->search !== '', function (Builder $query): void {
                $like = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $this->search).'%';

                $query->where(function (Builder $query) use ($like): void {
                    $query->whereRaw("registrants.submitted_name like ? escape '!'", [$like])
                        ->orWhereIn('registrants.person_id', fn ($people) => $people->select('people.id')->from('people')->whereRaw("people.name like ? escape '!'", [$like]));
                });
            });
    }

    /**
     * The rows of the report: the scope narrowed to the age bucket and put in order. Ties break on the registrant so paging is stable.
     *
     * @return Builder<Registrant>
     */
    public function rows(): Builder
    {
        $query = $this->scope()->when($this->bucket !== '', fn (Builder $query) => $this->whereInBucket($query, $this->bucket));

        return match ($this->sort) {
            self::SORT_AGE => $query->orderBy('arrears.oldest_open_at', $this->direction === 'desc' ? 'asc' : 'desc')->orderBy('registrants.id'),
            self::SORT_NAME => $query->join('people', 'people.id', '=', 'registrants.person_id')->orderBy('people.name', $this->direction)->orderBy('registrants.id'),
            default => $query->orderBy('arrears.outstanding', $this->direction)->orderBy('registrants.id'),
        };
    }

    /**
     * The count and the sum owed per age bucket, and over the whole scope, in one query. Every bucket is present.
     *
     * @return array{all: array{count: int, outstanding: int}, buckets: array<string, array{count: int, outstanding: int}>}
     */
    public function totals(): array
    {
        $query = $this->scope()->toBase()->reorder()->select([])
            ->selectRaw('count(*) as all_count, coalesce(sum(arrears.outstanding), 0) as all_outstanding');

        foreach (array_keys(self::BUCKETS) as $index => $bucket) {
            [$condition, $bindings] = $this->bucketCondition($bucket);

            $query->selectRaw("coalesce(sum(case when {$condition} then 1 else 0 end), 0) as count_{$index}", $bindings)
                ->selectRaw("coalesce(sum(case when {$condition} then arrears.outstanding else 0 end), 0) as outstanding_{$index}", $bindings);
        }

        $row = $query->first();

        return [
            'all' => ['count' => (int) $row->all_count, 'outstanding' => (int) $row->all_outstanding],
            'buckets' => collect(array_keys(self::BUCKETS))->mapWithKeys(fn (string $bucket, int $index): array => [
                $bucket => ['count' => (int) $row->{"count_{$index}"}, 'outstanding' => (int) $row->{"outstanding_{$index}"}],
            ])->all(),
        ];
    }

    /**
     * Whole calendar days since a bill was issued, in the application's timezone (WIB). Never negative.
     */
    public static function ageInDays(string|CarbonImmutable $issuedAt): int
    {
        $issued = CarbonImmutable::parse($issuedAt)->startOfDay();

        return max(0, (int) $issued->diffInDays(CarbonImmutable::today()));
    }

    /**
     * @return '0-7'|'8-30'|'31-60'|'lebih-60'
     */
    public static function bucketOf(int $ageInDays): string
    {
        return match (true) {
            $ageInDays <= 7 => '0-7',
            $ageInDays <= 30 => '8-30',
            $ageInDays <= 60 => '31-60',
            default => 'lebih-60',
        };
    }

    /**
     * What each registrant with an open bill was billed, paid and still owes. Money is summed per bill item first, so
     * the amount owed on an item stops at zero.
     */
    private function perRegistrant(): QueryBuilder
    {
        $allocations = fn (PaymentStatus $status) => DB::table('payment_allocations')
            ->join('payments', 'payments.id', '=', 'payment_allocations.payment_id')
            ->where('payments.status', $status->value)
            ->groupBy('payment_allocations.bill_item_id')
            ->selectRaw('payment_allocations.bill_item_id, sum(payment_allocations.amount) as total');

        $owed = 'case when bill_items.amount > coalesce(verified.total, 0) then bill_items.amount - coalesce(verified.total, 0) else 0 end';

        return DB::table('bill_items')
            ->join('bills', 'bills.id', '=', 'bill_items.bill_id')
            ->leftJoinSub($allocations(PaymentStatus::Verified), 'verified', 'verified.bill_item_id', '=', 'bill_items.id')
            ->leftJoinSub($allocations(PaymentStatus::Pending), 'waiting', 'waiting.bill_item_id', '=', 'bill_items.id')
            ->groupBy('bills.registrant_id')
            ->selectRaw('bills.registrant_id')
            ->selectRaw('sum(bill_items.amount) as billed')
            ->selectRaw('sum(coalesce(verified.total, 0)) as paid')
            ->selectRaw('sum(coalesce(waiting.total, 0)) as pending')
            ->selectRaw("sum({$owed}) as outstanding")
            ->selectRaw('sum(case when coalesce(verified.total, 0) > bill_items.amount then coalesce(verified.total, 0) - bill_items.amount else 0 end) as excess')
            ->selectRaw("min(case when {$owed} > 0 then bills.issued_at end) as oldest_open_at")
            ->havingRaw("sum({$owed}) > 0");
    }

    /**
     * @param  Builder<Registrant>  $query
     * @return Builder<Registrant>
     */
    private function whereInBucket(Builder $query, string $bucket): Builder
    {
        [$condition, $bindings] = $this->bucketCondition($bucket);

        return $query->whereRaw($condition, $bindings);
    }

    /**
     * The age of a bucket as a range on the oldest issue date (never a function on the column): `from` days ago is the
     * start of a day, so a bill issued late on that day still has that age.
     *
     * @return array{0: string, 1: list<string>}
     */
    private function bucketCondition(string $bucket): array
    {
        [$from, $to] = self::BUCKETS[$bucket];
        $today = CarbonImmutable::today();
        $conditions = [];
        $bindings = [];

        if ($to !== null) {
            $conditions[] = 'arrears.oldest_open_at >= ?';
            $bindings[] = $today->subDays($to)->startOfDay()->toDateTimeString();
        }

        if ($from > 0) {
            $conditions[] = 'arrears.oldest_open_at < ?';
            $bindings[] = $today->subDays($from - 1)->startOfDay()->toDateTimeString();
        }

        return [$conditions === [] ? '1 = 1' : '('.implode(' and ', $conditions).')', $bindings];
    }
}
