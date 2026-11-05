<?php

namespace App\Models;

use Database\Factories\ExamSessionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A time slot on an exam day, such as "Sesi 1" from 10:00 to 12:00, with a seat quota.
 * Times are stored as `HH:MM:SS` strings.
 *
 * @property int $id
 * @property int $exam_day_id
 * @property string $label
 * @property string $start_time
 * @property string $end_time
 * @property int $quota
 */
#[Fillable(['exam_day_id', 'label', 'start_time', 'end_time', 'quota'])]
class ExamSession extends Model
{
    /** @use HasFactory<ExamSessionFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<ExamDay, $this>
     */
    public function day(): BelongsTo
    {
        return $this->belongsTo(ExamDay::class, 'exam_day_id');
    }
}
