<?php

namespace App\Models;

use Database\Factories\ExamDayFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A date on which the exam is held, with its sessions. The schedule belongs to who sits the exam: an
 * organization and a wave, where an empty one means "all", so the same real session is stored once.
 *
 * @property int $id
 * @property int|null $organization_id
 * @property int|null $wave_id
 * @property Carbon $date
 */
#[Fillable(['organization_id', 'wave_id', 'date'])]
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
     * @return BelongsTo<Organization, $this>
     */
    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    /**
     * @return BelongsTo<Wave, $this>
     */
    public function wave(): BelongsTo
    {
        return $this->belongsTo(Wave::class);
    }

    /**
     * @return HasMany<ExamSession, $this>
     */
    public function sessions(): HasMany
    {
        return $this->hasMany(ExamSession::class)->orderBy('start_time')->orderBy('id');
    }

    /**
     * The days a registration can pick a session from: those for its organization and wave, or for all.
     *
     * @param  Builder<ExamDay>  $query
     */
    #[Scope]
    protected function forRegistrant(Builder $query, Registrant $registrant): void
    {
        $query
            ->where(fn (Builder $organization) => $organization->whereNull('organization_id')->orWhere('organization_id', $registrant->organization_id))
            ->where(fn (Builder $wave) => $wave->whereNull('wave_id')->orWhere('wave_id', $registrant->wave_id));
    }

    /**
     * The days of exactly this scope, where `null` means "all" and matches only days that are for all.
     *
     * @param  Builder<ExamDay>  $query
     */
    #[Scope]
    protected function inScope(Builder $query, ?int $organizationId, ?int $waveId): void
    {
        $organizationId === null ? $query->whereNull('organization_id') : $query->where('organization_id', $organizationId);
        $waveId === null ? $query->whereNull('wave_id') : $query->where('wave_id', $waveId);
    }

    /**
     * Who the day is for, as text: "SMP · Gelombang 1", or "Semua lembaga · Semua gelombang".
     */
    public function scopeLabel(): string
    {
        return ($this->organization?->name ?? 'Semua lembaga').' · '.($this->wave?->name ?? 'Semua gelombang');
    }
}
