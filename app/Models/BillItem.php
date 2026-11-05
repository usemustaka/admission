<?php

namespace App\Models;

use App\Enums\PaymentStatus;
use Database\Factories\BillItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One line of a bill, with its amount in rupiah. Payments are allocated to items, so every rupiah can be traced.
 *
 * @property int $id
 * @property int $bill_id
 * @property string $name
 * @property int $amount
 * @property int $sort_order
 */
#[Fillable(['bill_id', 'name', 'amount', 'sort_order'])]
class BillItem extends Model
{
    /** @use HasFactory<BillItemFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<Bill, $this>
     */
    public function bill(): BelongsTo
    {
        return $this->belongsTo(Bill::class);
    }

    /**
     * @return HasMany<PaymentAllocation, $this>
     */
    public function allocations(): HasMany
    {
        return $this->hasMany(PaymentAllocation::class);
    }

    /**
     * Add `verified_amount` and `pending_amount`: what is paid, and what waits for verification.
     *
     * @param  Builder<BillItem>  $query
     */
    #[Scope]
    protected function withPaymentTotals(Builder $query): void
    {
        foreach (['verified_amount' => PaymentStatus::Verified, 'pending_amount' => PaymentStatus::Pending] as $alias => $status) {
            $query->withSum(
                ["allocations as {$alias}" => fn (Builder $allocations) => $allocations->whereHas('payment', fn (Builder $payment) => $payment->where('status', $status))],
                'amount',
            );
        }
    }

    public function paidAmount(): int
    {
        return $this->sumOf('verified_amount', PaymentStatus::Verified);
    }

    public function pendingAmount(): int
    {
        return $this->sumOf('pending_amount', PaymentStatus::Pending);
    }

    /**
     * What is still to be paid. Amounts waiting for verification are kept out by default so the same rupiah is
     * not submitted twice.
     */
    public function outstandingAmount(bool $reservePending = true): int
    {
        return max($this->amount - $this->paidAmount() - ($reservePending ? $this->pendingAmount() : 0), 0);
    }

    /**
     * Verified money beyond what this item asks for. It is never hidden by `outstandingAmount`, which stops at zero.
     */
    public function excessAmount(): int
    {
        return max($this->paidAmount() - $this->amount, 0);
    }

    /**
     * Lock these items in id order, so two payments that touch the same items wait for each other (and cannot
     * deadlock) instead of both reading the same room. Call it inside a transaction, before reading the totals.
     *
     * @param  list<int>  $ids
     */
    public static function lockForAllocation(array $ids): void
    {
        static::query()->whereIn('id', $ids)->orderBy('id')->lockForUpdate()->pluck('id');
    }

    private function sumOf(string $attribute, PaymentStatus $status): int
    {
        if (array_key_exists($attribute, $this->getAttributes())) {
            return (int) $this->getAttribute($attribute);
        }

        return (int) $this->allocations()
            ->whereHas('payment', fn (Builder $payment) => $payment->where('status', $status))
            ->sum('amount');
    }
}
