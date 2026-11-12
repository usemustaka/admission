<?php

namespace App\Models;

use App\Enums\ExamScale;
use Database\Factories\ExamAspectFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A scored part of the admission exam, such as "Tes Tulis", with its KKM (minimum passing score).
 *
 * @property int $id
 * @property int $ruleset_id
 * @property string $name
 * @property ExamScale $scale
 * @property int $passing_score
 * @property int $sort_order
 */
#[Fillable(['ruleset_id', 'name', 'scale', 'passing_score', 'sort_order'])]
class ExamAspect extends Model
{
    /** @use HasFactory<ExamAspectFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['scale' => ExamScale::class];
    }

    /**
     * @return BelongsTo<Ruleset, $this>
     */
    public function ruleset(): BelongsTo
    {
        return $this->belongsTo(Ruleset::class);
    }
}
