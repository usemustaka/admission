<?php

namespace App\Models;

use Database\Factories\RulesetBillItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One line of a bill template, with its amount in rupiah.
 *
 * @property int $id
 * @property int $ruleset_bill_id
 * @property string $name
 * @property int $amount
 * @property int $sort_order
 */
#[Fillable(['ruleset_bill_id', 'name', 'amount', 'sort_order'])]
class RulesetBillItem extends Model
{
    /** @use HasFactory<RulesetBillItemFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<RulesetBill, $this>
     */
    public function bill(): BelongsTo
    {
        return $this->belongsTo(RulesetBill::class, 'ruleset_bill_id');
    }
}
