<?php

namespace App\Models;

use App\Enums\BillType;
use Database\Factories\RulesetBillFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * The bill template of one type (registration or settlement) inside a ruleset.
 *
 * @property int $id
 * @property int $ruleset_id
 * @property BillType $type
 */
#[Fillable(['ruleset_id', 'type'])]
class RulesetBill extends Model
{
    /** @use HasFactory<RulesetBillFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => BillType::class,
        ];
    }

    /**
     * @return BelongsTo<Ruleset, $this>
     */
    public function ruleset(): BelongsTo
    {
        return $this->belongsTo(Ruleset::class);
    }

    /**
     * @return HasMany<RulesetBillItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(RulesetBillItem::class)->orderBy('sort_order')->orderBy('id');
    }
}
