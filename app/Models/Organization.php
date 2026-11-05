<?php

namespace App\Models;

use Database\Factories\OrganizationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A school, pesantren, or other institution (lembaga) under the foundation.
 * Only a category/filter: every admin can see every organization.
 *
 * @property int $id
 * @property string $name
 * @property string $code
 * @property string|null $category
 * @property array<string, mixed>|null $metadata
 * @property bool $is_active
 * @property int $sort_order
 */
#[Fillable(['name', 'code', 'category', 'metadata', 'is_active', 'sort_order'])]
class Organization extends Model
{
    /** @use HasFactory<OrganizationFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @param  Builder<Organization>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /**
     * @return HasMany<ExamDay, $this>
     */
    public function examDays(): HasMany
    {
        return $this->hasMany(ExamDay::class);
    }

    /**
     * @return HasMany<Ruleset, $this>
     */
    public function rulesets(): HasMany
    {
        return $this->hasMany(Ruleset::class);
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
     * Whether anything refers to this organization: rulesets, registrations, students or employees. Such an
     * organization keeps its code and cannot be deleted, only deactivated.
     */
    public function isInUse(): bool
    {
        return $this->rulesets()->exists()
            || $this->registrants()->exists()
            || $this->students()->exists()
            || $this->employees()->exists()
            || $this->examDays()->exists();
    }
}
