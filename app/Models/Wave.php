<?php

namespace App\Models;

use Database\Factories\WaveFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * An admission wave (gelombang). Registration stays open all year; the wave only groups applicants and picks rules.
 *
 * @property int $id
 * @property string $name
 * @property Carbon|null $starts_on
 * @property Carbon|null $ends_on
 * @property bool $is_open
 */
#[Fillable(['name', 'starts_on', 'ends_on', 'is_open'])]
class Wave extends Model
{
    /** @use HasFactory<WaveFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'starts_on' => 'date',
            'ends_on' => 'date',
            'is_open' => 'boolean',
        ];
    }

    /**
     * @return HasMany<Ruleset, $this>
     */
    public function rulesets(): HasMany
    {
        return $this->hasMany(Ruleset::class);
    }

    /**
     * Waves that are switched on and inside their optional date window.
     *
     * @param  Builder<Wave>  $query
     */
    #[Scope]
    protected function open(Builder $query): void
    {
        $query->where('is_open', true)
            ->where(fn (Builder $q) => $q->whereNull('starts_on')->orWhereDate('starts_on', '<=', today()))
            ->where(fn (Builder $q) => $q->whereNull('ends_on')->orWhereDate('ends_on', '>=', today()));
    }
}
