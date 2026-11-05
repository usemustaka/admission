<?php

namespace App\Models;

use Database\Factories\PaymentAllocationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The part of a payment that pays one bill item.
 *
 * @property int $id
 * @property int $payment_id
 * @property int $bill_item_id
 * @property int $amount
 */
#[Fillable(['payment_id', 'bill_item_id', 'amount'])]
class PaymentAllocation extends Model
{
    /** @use HasFactory<PaymentAllocationFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<Payment, $this>
     */
    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    /**
     * @return BelongsTo<BillItem, $this>
     */
    public function billItem(): BelongsTo
    {
        return $this->belongsTo(BillItem::class);
    }
}
