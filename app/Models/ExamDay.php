<?php

namespace App\Models;

use Database\Factories\ExamDayFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A date on which the exam is held, with its sessions.
 *
 * @property int $id
 * @property int $ruleset_id
 * @property Carbon $date
 */
#[Fillable(['ruleset_id', 'date'])]
class ExamDay extends Model
{
    /** @use HasFactory<ExamDayFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'date' => 'date',
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
     * @return HasMany<ExamSession, $this>
     */
    public function sessions(): HasMany
    {
        return $this->hasMany(ExamSession::class)->orderBy('start_time')->orderBy('id');
    }
}
