<?php

namespace App\Models;

use App\Enums\DocumentType;
use Database\Factories\RulesetDocumentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A file a ruleset asks parents for, and whether the registration can be sent without it.
 *
 * @property int $id
 * @property int $ruleset_id
 * @property DocumentType $type
 * @property bool $is_required
 */
#[Fillable(['ruleset_id', 'type', 'is_required'])]
class RulesetDocument extends Model
{
    /** @use HasFactory<RulesetDocumentFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => DocumentType::class,
            'is_required' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Ruleset, $this>
     */
    public function ruleset(): BelongsTo
    {
        return $this->belongsTo(Ruleset::class);
    }
}
