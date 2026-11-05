<?php

namespace App\Support;

use App\Enums\PaymentStatus;
use App\Enums\RegistrantStatus;
use App\Models\Registrant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

/**
 * What the committee narrowed the registrant list down to, read from the Indonesian query string. The list, its
 * status counts and the CSV export all apply the same filters, so they never disagree about what is "filtered".
 */
class RegistrantListFilters
{
    /** Payment states a bill-based filter understands: `lunas` (every bill is paid) and `belum` (some bill is not). */
    public const PAYMENT_PAID = 'lunas';

    public const PAYMENT_UNPAID = 'belum';

    /** At least one payment proof is waiting for the committee to verify it. */
    public const PAYMENT_PENDING = 'menunggu';

    /** Page sizes the list offers; anything else falls back to the default. */
    public const PER_PAGE_OPTIONS = [25, 50, 100, 200];

    public const DEFAULT_PER_PAGE = 25;

    /** List orders: newest registration (default), name, and how long they have waited since they were sent in. */
    public const SORTS = ['terbaru', 'nama', 'dikirim'];

    public function __construct(
        public readonly string $term,
        public readonly ?RegistrantStatus $status,
        public readonly int $organizationId,
        public readonly int $waveId,
        public readonly bool $needsIdentityReview,
        public readonly string $payment,
        public readonly bool $withoutExamSession,
        public readonly string $sort,
        public readonly string $direction,
        public readonly int $perPage = self::DEFAULT_PER_PAGE,
    ) {}

    /**
     * The same filters from a plain array of query parameters (what a "select all results" bulk request sends).
     *
     * @param  array<string, mixed>  $input
     */
    public static function fromArray(array $input): self
    {
        return self::fromRequest(new Request(array_filter($input, fn (mixed $value): bool => is_scalar($value))));
    }

    public static function fromRequest(Request $request): self
    {
        $requestedSort = $request->string('urut')->toString();
        $sort = in_array($requestedSort, self::SORTS, true) ? $requestedSort : 'terbaru';
        $payment = $request->string('bayar')->toString();
        $perPage = $request->integer('per_halaman');

        return new self(
            term: trim($request->string('cari')->toString()),
            status: $request->enum('status', RegistrantStatus::class),
            organizationId: $request->integer('lembaga'),
            waveId: $request->integer('gelombang'),
            needsIdentityReview: $request->boolean('perlu_dicek'),
            payment: in_array($payment, [self::PAYMENT_PAID, self::PAYMENT_UNPAID, self::PAYMENT_PENDING], true) ? $payment : '',
            withoutExamSession: $request->boolean('belum_ujian'),
            sort: $sort,
            direction: match ($request->string('arah')->toString()) {
                'asc' => 'asc',
                'desc' => 'desc',
                default => in_array($sort, ['nama', 'dikirim'], true) ? 'asc' : 'desc',
            },
            perPage: in_array($perPage, self::PER_PAGE_OPTIONS, true) ? $perPage : self::DEFAULT_PER_PAGE,
        );
    }

    /**
     * Whether any filter narrows the list (sorting does not count).
     */
    public function isActive(): bool
    {
        return $this->term !== ''
            || $this->status !== null
            || $this->organizationId > 0
            || $this->waveId > 0
            || $this->needsIdentityReview
            || $this->payment !== ''
            || $this->withoutExamSession;
    }

    /**
     * The filters as the page sends them back in its query string.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'cari' => $this->term,
            'status' => $this->status?->value ?? '',
            'lembaga' => $this->organizationId ?: '',
            'gelombang' => $this->waveId ?: '',
            'perlu_dicek' => $this->needsIdentityReview,
            'bayar' => $this->payment,
            'belum_ujian' => $this->withoutExamSession,
            'urut' => $this->sort,
            'arah' => $this->direction,
            'per_halaman' => $this->perPage,
        ];
    }

    /**
     * @param  Builder<Registrant>  $query
     * @return Builder<Registrant>
     */
    public function apply(Builder $query, bool $includeStatus = true): Builder
    {
        return $query
            ->when($this->term !== '', fn (Builder $query) => $this->applySearch($query))
            ->when($includeStatus && $this->status !== null, fn (Builder $query) => $query->where('registrants.status', $this->status))
            ->when($this->organizationId > 0, fn (Builder $query) => $query->where('registrants.organization_id', $this->organizationId))
            ->when($this->waveId > 0, fn (Builder $query) => $query->where('registrants.wave_id', $this->waveId))
            ->when($this->needsIdentityReview, fn (Builder $query) => $query->where('registrants.needs_identity_review', true))
            ->when($this->payment !== '', fn (Builder $query) => $this->applyPayment($query))
            ->when($this->withoutExamSession, fn (Builder $query) => $this->applyWithoutExamSession($query));
    }

    /**
     * @param  Builder<Registrant>  $query
     * @return Builder<Registrant>
     */
    public function applySort(Builder $query): Builder
    {
        if ($this->sort === 'nama') {
            return $query->join('people', 'people.id', '=', 'registrants.person_id')->orderBy('people.name', $this->direction)->orderBy('registrants.id');
        }

        if ($this->sort === 'dikirim') {
            // Registrants that were never sent in (no date) always go last, whichever way the dates run.
            return $query->orderByRaw('registrants.submitted_at is null')->orderBy('registrants.submitted_at', $this->direction)->orderBy('registrants.id', $this->direction);
        }

        return $query->orderBy('registrants.created_at', $this->direction)->orderBy('registrants.id', $this->direction);
    }

    /**
     * Match the child's name on record, the name the parent typed, the NIK, NISN or NIS by prefix, and the names and
     * phone numbers of the parents. Each part is a subquery on the id column, so nothing is loaded into memory;
     * `%`, `_` and `!` in what was typed only match themselves.
     *
     * @param  Builder<Registrant>  $query
     * @return Builder<Registrant>
     */
    private function applySearch(Builder $query): Builder
    {
        $escaped = str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $this->term);
        $contains = "%{$escaped}%";
        $prefix = "{$escaped}%";
        $looksNumeric = preg_match('/^\d+$/', $this->term) === 1;
        $looksLikePhone = preg_match('/^[\d\s+()-]*\d[\d\s+()-]*$/', $this->term) === 1;

        return $query->where(function (Builder $query) use ($contains, $prefix, $looksNumeric, $looksLikePhone): void {
            $query->whereRaw("registrants.submitted_name like ? escape '!'", [$contains])
                ->orWhereIn('registrants.person_id', fn ($people) => $people->select('people.id')->from('people')
                    ->where(function ($match) use ($contains, $prefix, $looksNumeric): void {
                        $match->whereRaw("people.name like ? escape '!'", [$contains]);

                        if ($looksNumeric) {
                            $match->orWhereRaw("people.nik like ? escape '!'", [$prefix])
                                ->orWhereRaw("people.nisn like ? escape '!'", [$prefix]);
                        }
                    }))
                ->orWhereIn('registrants.person_id', fn ($parentships) => $parentships->select('parentships.person_id')->from('parentships')
                    ->join('people as parents', 'parents.id', '=', 'parentships.related_person_id')
                    ->where(function ($match) use ($contains, $looksLikePhone): void {
                        $match->whereRaw("parents.name like ? escape '!'", [$contains]);

                        if ($looksLikePhone) {
                            $match->orWhereRaw("parents.phone like ? escape '!'", [$contains]);
                        }
                    }));

            if ($looksNumeric) {
                $query->orWhereIn('registrants.person_id', fn ($students) => $students->select('students.person_id')->from('students')
                    ->whereRaw("students.nis like ? escape '!'", [$prefix]));
            }
        });
    }

    /**
     * A bill counts as open when `Bill::status()` does not call it paid, so a bill that asks for nothing (Rp0) is
     * settled. `lunas` therefore needs at least one bill and none of them open; `belum` needs at least one open bill.
     *
     * @param  Builder<Registrant>  $query
     * @return Builder<Registrant>
     */
    private function applyPayment(Builder $query): Builder
    {
        if ($this->payment === self::PAYMENT_PENDING) {
            return $this->applyPendingProof($query);
        }

        $verified = PaymentStatus::Verified->value;

        // Open: some item has less verified money than it asks for. Rp0 items are never open.
        $openBill = <<<SQL
            exists (select 1 from bill_items
                where bill_items.bill_id = bills.id
                and bill_items.amount > coalesce((select sum(payment_allocations.amount) from payment_allocations
                    inner join payments on payments.id = payment_allocations.payment_id and payments.status = '{$verified}'
                    where payment_allocations.bill_item_id = bill_items.id), 0))
            SQL;

        if ($this->payment === self::PAYMENT_UNPAID) {
            return $query->whereHas('bills', fn (Builder $bills) => $bills->whereRaw("({$openBill})"));
        }

        return $query
            ->whereHas('bills')
            ->whereDoesntHave('bills', fn (Builder $bills) => $bills->whereRaw("({$openBill})"));
    }

    /**
     * Registrants with at least one payment proof the committee has not verified yet.
     *
     * @param  Builder<Registrant>  $query
     * @return Builder<Registrant>
     */
    private function applyPendingProof(Builder $query): Builder
    {
        return $query->whereExists(fn ($proofs) => $proofs->selectRaw('1')
            ->from('bills')
            ->join('bill_items', 'bill_items.bill_id', '=', 'bills.id')
            ->join('payment_allocations', 'payment_allocations.bill_item_id', '=', 'bill_items.id')
            ->join('payments', 'payments.id', '=', 'payment_allocations.payment_id')
            ->whereColumn('bills.registrant_id', 'registrants.id')
            ->where('payments.status', PaymentStatus::Pending->value));
    }

    /**
     * Registrations whose organization and wave have an exam schedule but who have not picked a session yet.
     *
     * @param  Builder<Registrant>  $query
     * @return Builder<Registrant>
     */
    private function applyWithoutExamSession(Builder $query): Builder
    {
        return $query
            ->whereNull('registrants.exam_session_id')
            ->whereExists(fn ($days) => $days->selectRaw('1')
                ->from('exam_days')
                ->where(fn ($organization) => $organization->whereNull('exam_days.organization_id')->orWhereColumn('exam_days.organization_id', 'registrants.organization_id'))
                ->where(fn ($wave) => $wave->whereNull('exam_days.wave_id')->orWhereColumn('exam_days.wave_id', 'registrants.wave_id'))
                ->whereExists(fn ($sessions) => $sessions->selectRaw('1')->from('exam_sessions')->whereColumn('exam_sessions.exam_day_id', 'exam_days.id')));
    }
}
