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
    ) {}

    public static function fromRequest(Request $request): self
    {
        $sort = $request->string('urut')->toString() === 'nama' ? 'nama' : 'terbaru';
        $payment = $request->string('bayar')->toString();

        return new self(
            term: trim($request->string('cari')->toString()),
            status: $request->enum('status', RegistrantStatus::class),
            organizationId: $request->integer('lembaga'),
            waveId: $request->integer('gelombang'),
            needsIdentityReview: $request->boolean('perlu_dicek'),
            payment: in_array($payment, [self::PAYMENT_PAID, self::PAYMENT_UNPAID], true) ? $payment : '',
            withoutExamSession: $request->boolean('belum_ujian'),
            sort: $sort,
            direction: match ($request->string('arah')->toString()) {
                'asc' => 'asc',
                'desc' => 'desc',
                default => $sort === 'nama' ? 'asc' : 'desc',
            },
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
        ];
    }

    /**
     * @param  Builder<Registrant>  $query
     * @return Builder<Registrant>
     */
    public function apply(Builder $query, bool $includeStatus = true): Builder
    {
        return $query
            ->when($this->term !== '', fn (Builder $query) => $query->whereHas('person', fn (Builder $people) => $people->search($this->term)))
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

        return $query->orderBy('registrants.created_at', $this->direction)->orderBy('registrants.id', $this->direction);
    }

    /**
     * A bill counts as open the same way `Bill::status()` says "not paid": nothing verified yet, or less than the
     * total. `lunas` therefore needs at least one bill and none of them open; `belum` needs at least one open bill.
     *
     * @param  Builder<Registrant>  $query
     * @return Builder<Registrant>
     */
    private function applyPayment(Builder $query): Builder
    {
        $verified = PaymentStatus::Verified->value;

        $openBill = <<<SQL
            coalesce((select sum(payment_allocations.amount) from payment_allocations
                inner join payments on payments.id = payment_allocations.payment_id and payments.status = '{$verified}'
                inner join bill_items on bill_items.id = payment_allocations.bill_item_id
                where bill_items.bill_id = bills.id), 0) <= 0
            or coalesce((select sum(payment_allocations.amount) from payment_allocations
                inner join payments on payments.id = payment_allocations.payment_id and payments.status = '{$verified}'
                inner join bill_items on bill_items.id = payment_allocations.bill_item_id
                where bill_items.bill_id = bills.id), 0)
                < coalesce((select sum(bill_items.amount) from bill_items where bill_items.bill_id = bills.id), 0)
            SQL;

        if ($this->payment === self::PAYMENT_UNPAID) {
            return $query->whereHas('bills', fn (Builder $bills) => $bills->whereRaw("({$openBill})"));
        }

        return $query
            ->whereHas('bills')
            ->whereDoesntHave('bills', fn (Builder $bills) => $bills->whereRaw("({$openBill})"));
    }

    /**
     * Registrations whose ruleset has an exam schedule but who have not picked a session yet.
     *
     * @param  Builder<Registrant>  $query
     * @return Builder<Registrant>
     */
    private function applyWithoutExamSession(Builder $query): Builder
    {
        return $query
            ->whereNull('registrants.exam_session_id')
            ->whereNotNull('registrants.ruleset_id')
            ->whereExists(fn ($days) => $days->selectRaw('1')
                ->from('exam_days')
                ->whereColumn('exam_days.ruleset_id', 'registrants.ruleset_id')
                ->whereExists(fn ($sessions) => $sessions->selectRaw('1')->from('exam_sessions')->whereColumn('exam_sessions.exam_day_id', 'exam_days.id')));
    }
}
