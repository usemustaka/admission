<?php

namespace App\Models;

use App\Contracts\HasNoteSubject;
use App\Enums\Gender;
use App\Enums\PersonKind;
use Database\Factories\PersonFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

/**
 * A human being known to the school, whether or not they can log in. Not tied to any organization.
 *
 * @property int $id
 * @property string|null $nik
 * @property string|null $nisn
 * @property string|null $family_card_number
 * @property string $name
 * @property Gender|null $gender
 * @property string|null $birth_place
 * @property Carbon|null $birth_date
 * @property string|null $phone
 * @property string|null $email
 * @property string|null $photo_path
 * @property bool $is_alive
 * @property string|null $religion
 * @property string|null $blood_type
 * @property int|null $child_order
 * @property int|null $siblings_count
 * @property string|null $address
 * @property string|null $last_education
 * @property string|null $job
 * @property string|null $monthly_income
 * @property-read string|null $photo_url
 */
#[Fillable(['nik', 'nisn', 'family_card_number', 'name', 'gender', 'birth_place', 'birth_date', 'phone', 'email', 'photo_path', 'is_alive', 'religion', 'blood_type', 'child_order', 'siblings_count', 'address', 'last_education', 'job', 'monthly_income'])]
class Person extends Model implements HasNoteSubject
{
    /** @use HasFactory<PersonFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'gender' => Gender::class,
            'birth_date' => 'date',
            'is_alive' => 'boolean',
        ];
    }

    /**
     * @return Attribute<string|null, never>
     */
    protected function photoUrl(): Attribute
    {
        return Attribute::get(
            fn (): ?string => $this->photo_path ? Storage::disk('public')->url($this->photo_path) : null,
        );
    }

    /**
     * @return HasOne<User, $this>
     */
    public function user(): HasOne
    {
        return $this->hasOne(User::class);
    }

    /**
     * The parents and guardians of this person.
     *
     * @return HasMany<Parentship, $this>
     */
    public function parentships(): HasMany
    {
        return $this->hasMany(Parentship::class);
    }

    /**
     * The people this person is a parent or guardian of.
     *
     * @return HasMany<Parentship, $this>
     */
    public function childrenParentships(): HasMany
    {
        return $this->hasMany(Parentship::class, 'related_person_id');
    }

    /**
     * @return HasMany<Registrant, $this>
     */
    public function registrants(): HasMany
    {
        return $this->hasMany(Registrant::class);
    }

    /**
     * @return HasMany<Student, $this>
     */
    public function students(): HasMany
    {
        return $this->hasMany(Student::class);
    }

    /**
     * @return HasMany<Employee, $this>
     */
    public function employees(): HasMany
    {
        return $this->hasMany(Employee::class);
    }

    /**
     * @return MorphMany<Note, $this>
     */
    public function notes(): MorphMany
    {
        return $this->morphMany(Note::class, 'noteable');
    }

    /**
     * Match people by name or NIK.
     *
     * @param  Builder<Person>  $query
     */
    public function scopeSearch(Builder $query, ?string $term): void
    {
        $term = trim((string) $term);

        if ($term === '') {
            return;
        }

        $query->where(function (Builder $query) use ($term): void {
            $query->where('name', 'like', "%{$term}%")
                ->orWhere('nik', 'like', "{$term}%");
        });
    }

    /**
     * People who play the given part: applicants and students, parents and guardians, or staff.
     *
     * @param  Builder<Person>  $query
     */
    #[Scope]
    protected function ofKind(Builder $query, PersonKind $kind): void
    {
        match ($kind) {
            PersonKind::Student => $query->where(fn (Builder $q) => $q->has('registrants')->orHas('students')),
            PersonKind::Family => $query->has('childrenParentships'),
            PersonKind::Employee => $query->has('employees'),
        };
    }

    /**
     * People whose NIK is missing or not the full 16 digits.
     *
     * @param  Builder<Person>  $query
     */
    #[Scope]
    protected function withoutCompleteNik(Builder $query): void
    {
        $query->where(fn (Builder $q) => $q->whereNull('nik')->orWhereRaw('LENGTH(nik) <> 16'));
    }

    /**
     * People nobody can reach: no phone number of their own and no parent or guardian with one.
     *
     * @param  Builder<Person>  $query
     */
    #[Scope]
    protected function withoutContact(Builder $query): void
    {
        $query->where(fn (Builder $q) => $q->whereNull('phone')->orWhere('phone', ''))
            ->whereDoesntHave('parentships.relatedPerson', fn (Builder $parent) => $parent->whereNotNull('phone')->where('phone', '<>', ''));
    }

    public function notePersonId(): ?int
    {
        return $this->id;
    }
}
