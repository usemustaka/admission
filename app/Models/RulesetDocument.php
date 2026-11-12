<?php

namespace App\Models;

use App\Enums\DocumentType;
use App\Support\DocumentKind;
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
 * @property string $type a `DocumentType` value, or a custom key
 * @property string|null $label the name of a custom file
 * @property int $max_files
 * @property bool $is_required
 */
#[Fillable(['ruleset_id', 'type', 'label', 'max_files', 'is_required'])]
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
            'max_files' => 'integer',
            'is_required' => 'boolean',
        ];
    }

    public function isCustom(): bool
    {
        return DocumentKind::isCustomKey($this->type);
    }

    public function builtinType(): ?DocumentType
    {
        return DocumentType::tryFrom($this->type);
    }

    /**
     * @return BelongsTo<Ruleset, $this>
     */
    public function ruleset(): BelongsTo
    {
        return $this->belongsTo(Ruleset::class);
    }
}
