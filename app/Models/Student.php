<?php

namespace App\Models;

use Database\Factories\StudentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * The role a person holds at an organization. Its photo is separate from the person's profile photo.
 *
 * @property int $id
 * @property int $person_id
 * @property int $organization_id
 * @property int|null $year_id
 * @property int|null $registrant_id
 * @property string|null $nis
 * @property string|null $photo_path
 */
#[Fillable(['person_id', 'organization_id', 'year_id', 'registrant_id', 'nis', 'photo_path'])]
class Student extends Model
{
    /** @use HasFactory<StudentFactory> */
    use HasFactory;

    /**
     * @return BelongsTo<Person, $this>
     */
    public function person(): BelongsTo
    {
        return $this->belongsTo(Person::class);
    }

    /**
     * @return BelongsTo<Organization, $this>
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * @return BelongsTo<Year, $this>
     */
    public function year(): BelongsTo
    {
        return $this->belongsTo(Year::class);
    }

    /**
     * @return BelongsTo<Registrant, $this>
     */
    public function registrant(): BelongsTo
    {
        return $this->belongsTo(Registrant::class);
    }
}
