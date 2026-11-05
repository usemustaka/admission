<?php

namespace App\Models;

use App\Enums\BillType;
use App\Enums\Gender;
use Database\Factories\RulesetFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A bundle of admission rules (bills, exam aspects, exam schedule) that applies to applicants matching its
 * conditions. A null condition means "any". The lowest `priority` number is checked first.
 *
 * @property int $id
 * @property string $name
 * @property int|null $wave_id
 * @property int|null $organization_id
 * @property Gender|null $gender
 * @property int $priority
 * @property bool $is_active
 */
#[Fillable(['name', 'wave_id', 'organization_id', 'gender', 'priority', 'is_active'])]
class Ruleset extends Model
{
    /** @use HasFactory<RulesetFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'gender' => Gender::class,
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Wave, $this>
     */
    public function wave(): BelongsTo
    {
        return $this->belongsTo(Wave::class);
    }

    /**
     * @return BelongsTo<Organization, $this>
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * The registrations that were made under this ruleset.
     *
     * @return HasMany<Registrant, $this>
     */
    public function registrants(): HasMany
    {
        return $this->hasMany(Registrant::class);
    }

    /**
     * @return HasMany<RulesetBill, $this>
     */
    public function bills(): HasMany
    {
        return $this->hasMany(RulesetBill::class);
    }

    /**
     * @return HasMany<ExamAspect, $this>
     */
    public function examAspects(): HasMany
    {
        return $this->hasMany(ExamAspect::class)->orderBy('sort_order')->orderBy('id');
    }

    /**
     * @param  Builder<Ruleset>  $query
     */
    #[Scope]
    protected function active(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /**
     * @param  Builder<Ruleset>  $query
     */
    #[Scope]
    protected function byPriority(Builder $query): void
    {
        $query->orderBy('priority')->orderBy('id');
    }

    /**
     * A frozen copy of the rules, stored on a registration so later edits of this ruleset do not change it.
     * Exam sessions are not copied: seats are shared by everyone, so they stay live.
     *
     * @return array{ruleset_id: int, name: string, bills: list<array{type: string, items: list<array{name: string, amount: int}>}>, exam_aspects: list<array{name: string, passing_score: int}>}
     */
    public function toSnapshot(): array
    {
        $this->load(['bills.items', 'examAspects']);

        $bills = [];

        foreach (BillType::cases() as $type) {
            $bill = $this->bills->firstWhere('type', $type);

            if ($bill === null) {
                continue;
            }

            $items = [];

            foreach ($bill->items as $item) {
                $items[] = ['name' => $item->name, 'amount' => $item->amount];
            }

            $bills[] = ['type' => $type->value, 'items' => $items];
        }

        $aspects = [];

        foreach ($this->examAspects as $aspect) {
            $aspects[] = ['name' => $aspect->name, 'passing_score' => $aspect->passing_score];
        }

        return [
            'ruleset_id' => $this->id,
            'name' => $this->name,
            'bills' => $bills,
            'exam_aspects' => $aspects,
        ];
    }
}
