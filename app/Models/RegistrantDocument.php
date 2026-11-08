<?php

namespace App\Models;

use App\Enums\DocumentType;
use Database\Factories\RegistrantDocumentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A file a parent uploaded for a registration. It sits on the private disk and is only served through the
 * download route, after checking who is asking.
 *
 * @property int $id
 * @property int $registrant_id
 * @property DocumentType $type
 * @property int $slot which file of the type this is, from 1
 * @property string $path
 * @property string $original_name
 * @property string $mime_type
 * @property int $size in bytes
 * @property int|null $uploaded_by_user_id
 */
#[Fillable(['registrant_id', 'type', 'slot', 'path', 'original_name', 'mime_type', 'size', 'uploaded_by_user_id'])]
class RegistrantDocument extends Model
{
    /** @use HasFactory<RegistrantDocumentFactory> */
    use HasFactory;

    public const DISK = 'local';

    /** The largest file a parent can upload, in kilobytes. */
    public const MAX_KILOBYTES = 1024;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => DocumentType::class,
        ];
    }

    /**
     * @return BelongsTo<Registrant, $this>
     */
    public function registrant(): BelongsTo
    {
        return $this->belongsTo(Registrant::class);
    }
}
