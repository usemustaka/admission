<?php

namespace App\Models;

use App\Enums\EducationLevel;
use Database\Factories\EducationHistoryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A school a child attended before: its level, name, national school number (NPSN) and address.
 *
 * @property int $id
 * @property int $person_id
 * @property EducationLevel $level
 * @property string $school_name
 * @property string|null $npsn
 * @property string|null $school_address
 * @property int $sort_order
 */
#[Fillable(['person_id', 'level', 'school_name', 'npsn', 'school_address', 'sort_order'])]
class EducationHistory extends Model
{
    /** @use HasFactory<EducationHistoryFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'level' => EducationLevel::class,
        ];
    }

    /**
     * @return BelongsTo<Person, $this>
     */
    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }
}
