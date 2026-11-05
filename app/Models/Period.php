<?php

namespace App\Models;

use Database\Factories\PeriodFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A semester inside an academic year.
 *
 * @property int $id
 * @property int $year_id
 * @property string $name
 * @property int $sequence
 * @property Carbon $starts_on
 * @property Carbon $ends_on
 * @property bool $is_active
 */
#[Fillable(['year_id', 'name', 'sequence', 'starts_on', 'ends_on', 'is_active'])]
class Period extends Model
{
    /** @use HasFactory<PeriodFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'starts_on' => 'date',
            'ends_on' => 'date',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Year, $this>
     */
    public function year(): BelongsTo
    {
        return $this->belongsTo(Year::class);
    }
}
