<?php

namespace App\Models;

use App\Enums\BillStatus;
use App\Enums\BillType;
use Database\Factories\BillFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * What a registrant has to pay, of one type, made of items. Its status comes from verified payments.
 *
 * @property int $id
 * @property int $registrant_id
 * @property BillType $type
 * @property Carbon $issued_at
 */
#[Fillable(['registrant_id', 'type', 'issued_at'])]
class Bill extends Model
{
    /** @use HasFactory<BillFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => BillType::class,
            'issued_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Registrant, $this>
     */
    public function registrant(): BelongsTo
    {
        return $this->belongsTo(Registrant::class);
    }

    /**
     * @return HasMany<BillItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(BillItem::class)->orderBy('sort_order')->orderBy('id');
    }

    /**
     * Load the items together with how much of each was paid or is waiting for verification.
     */
    public function loadTotals(): static
    {
        return $this->load(['items' => fn ($query) => $query->withPaymentTotals()]);
    }

    public function totalAmount(): int
    {
        return (int) $this->items->sum('amount');
    }

    public function paidAmount(): int
    {
        return $this->items->sum(fn (BillItem $item): int => $item->paidAmount());
    }

    public function pendingAmount(): int
    {
        return $this->items->sum(fn (BillItem $item): int => $item->pendingAmount());
    }

    /**
     * Verified money beyond what the items ask for, counted per item so one item's surplus cannot hide another's debt.
     */
    public function excessAmount(): int
    {
        return $this->items->sum(fn (BillItem $item): int => $item->excessAmount());
    }

    /**
     * What is still owed, per item, so money beyond one item cannot cover what another still asks for.
     */
    public function outstandingAmount(): int
    {
        return $this->items->sum(fn (BillItem $item): int => $item->outstandingAmount(reservePending: false));
    }

    /**
     * A bill that asks for nothing (every item is Rp0, such as a scholarship) is settled from the start.
     */
    public function status(): BillStatus
    {
        if ($this->outstandingAmount() <= 0) {
            return BillStatus::Paid;
        }

        return $this->paidAmount() <= 0 ? BillStatus::Unpaid : BillStatus::Partial;
    }
}
