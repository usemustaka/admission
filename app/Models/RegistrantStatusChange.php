<?php

namespace App\Models;

use App\Enums\RegistrantStatus;
use Database\Factories\RegistrantStatusChangeFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One step in the history of a registration. The note is the reason the committee gave; for a bypass it is
 * an internal note that parents do not see.
 *
 * @property int $id
 * @property int $registrant_id
 * @property int|null $user_id
 * @property RegistrantStatus|null $from_status
 * @property RegistrantStatus $to_status
 * @property string|null $note
 * @property bool $is_bypass
 * @property Carbon|null $created_at
 */
#[Fillable(['registrant_id', 'user_id', 'from_status', 'to_status', 'note', 'is_bypass'])]
class RegistrantStatusChange extends Model
{
    /** @use HasFactory<RegistrantStatusChangeFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'from_status' => RegistrantStatus::class,
            'to_status' => RegistrantStatus::class,
            'is_bypass' => 'boolean',
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
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
