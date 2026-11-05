<?php

namespace App\Models;

use Database\Factories\OrganizationFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

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
}
