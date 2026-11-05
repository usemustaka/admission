<?php

namespace App\Models;

use App\Enums\BillStatus;
use App\Enums\BillType;
use App\Enums\ParentKind;
use App\Enums\ParentRelation;
use App\Enums\RegistrantStatus;
use Database\Factories\RegistrantFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

/**
 * An application to enrol one child at one organization in one wave. Holds a frozen copy of the ruleset that
 * applied when it was created.
 *
 * @property int $id
 * @property int $person_id
 * @property int $organization_id
 * @property int $wave_id
 * @property int|null $exam_session_id
 * @property int|null $ruleset_id
 * @property array{ruleset_id: int, name: string, bills: list<array{type: string, items: list<array{name: string, amount: int}>}>, exam_aspects: list<array{name: string, passing_score: int}>}|null $ruleset_snapshot
 * @property int $registered_by_person_id
 * @property string $submitted_name the child's name as the parent typed it
 * @property ParentRelation $registered_as_relation
 * @property ParentKind|null $registered_as_kind
 * @property RegistrantStatus $status
 * @property bool $needs_identity_review
 * @property string|null $formal_photo_path
 * @property array<int, int|null>|null $exam_scores score per exam aspect, in the order of the aspects in the snapshot
 * @property Carbon|null $submitted_at
 * @property-read string|null $formal_photo_url
 * @property Carbon|null $created_at
 */
#[Fillable(['person_id', 'organization_id', 'wave_id', 'exam_session_id', 'ruleset_id', 'ruleset_snapshot', 'registered_by_person_id', 'submitted_name', 'registered_as_relation', 'registered_as_kind', 'status', 'needs_identity_review', 'formal_photo_path', 'exam_scores', 'submitted_at'])]
class Registrant extends Model
{
    /** @use HasFactory<RegistrantFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'ruleset_snapshot' => 'array',
            'registered_as_relation' => ParentRelation::class,
            'registered_as_kind' => ParentKind::class,
            'status' => RegistrantStatus::class,
            'needs_identity_review' => 'boolean',
            'exam_scores' => 'array',
            'submitted_at' => 'datetime',
        ];
    }

    /**
     * The child.
     *
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
     * @return BelongsTo<Wave, $this>
     */
    public function wave(): BelongsTo
    {
        return $this->belongsTo(Wave::class);
    }

    /**
     * The parent or guardian who registered the child.
     *
     * @return BelongsTo<Person, $this>
     */
    public function registeredBy(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'registered_by_person_id');
    }

    /**
     * @return BelongsTo<ExamSession, $this>
     */
    public function examSession(): BelongsTo
    {
        return $this->belongsTo(ExamSession::class);
    }

    /**
     * @return HasMany<RegistrantStatusChange, $this>
     */
    public function statusChanges(): HasMany
    {
        return $this->hasMany(RegistrantStatusChange::class)->orderBy('id');
    }

    /**
     * @return HasOne<Student, $this>
     */
    public function student(): HasOne
    {
        return $this->hasOne(Student::class);
    }

    /**
     * @return HasMany<Bill, $this>
     */
    public function bills(): HasMany
    {
        return $this->hasMany(Bill::class);
    }

    /**
     * Registrations a parent may follow: of the children they are a parent or guardian of, and the ones they
     * registered themselves. The latter covers registrations that wait for the committee to confirm the NIK,
     * where the parent is not linked to the child yet.
     *
     * @param  Builder<Registrant>  $query
     */
    #[Scope]
    protected function forParent(Builder $query, User $user): void
    {
        $this->forParentPerson($query, $user->person_id);
    }

    /**
     * The same, for a parent given as a person (who may have no account).
     *
     * @param  Builder<Registrant>  $query
     */
    #[Scope]
    protected function forParentPerson(Builder $query, ?int $personId): void
    {
        $query->where(function (Builder $query) use ($personId): void {
            $query->whereIn('person_id', Parentship::query()
                ->where('related_person_id', $personId)
                ->select('person_id'))
                ->orWhere('registered_by_person_id', $personId);
        });
    }

    public function getFormalPhotoUrlAttribute(): ?string
    {
        return $this->formal_photo_path ? Storage::disk('public')->url($this->formal_photo_path) : null;
    }

    public function hasBill(BillType $type): bool
    {
        foreach ($this->ruleset_snapshot['bills'] ?? [] as $bill) {
            if ($bill['type'] === $type->value && $bill['items'] !== []) {
                return true;
            }
        }

        return false;
    }

    /**
     * The stages of the admission flow that apply to this registration. Bill stages are skipped when the
     * ruleset has no bill of that type.
     *
     * @return list<string>
     */
    public function steps(): array
    {
        $steps = ['registered'];

        if ($this->hasBill(BillType::Registration)) {
            $steps[] = 'registration-bill';
        }

        array_push($steps, 'data', 'validation');

        if ($this->hasBill(BillType::Settlement)) {
            $steps[] = 'settlement-bill';
        }

        array_push($steps, 'announcement', 'placement');

        return $steps;
    }

    /**
     * Whether the ruleset has an exam schedule the parent has to pick a session from.
     */
    public function hasExamSchedule(): bool
    {
        return $this->ruleset_id !== null && ExamDay::query()->where('ruleset_id', $this->ruleset_id)->whereHas('sessions')->exists();
    }

    /**
     * Whether the registration bill is out of the way: the ruleset has none, or it has been paid in full.
     */
    public function isRegistrationBillSettled(): bool
    {
        if (! $this->hasBill(BillType::Registration)) {
            return true;
        }

        $bill = $this->bills()->where('type', BillType::Registration)->first();

        return $bill !== null && $bill->loadTotals()->status() === BillStatus::Paid;
    }

    /**
     * The stage the registration is at now. Parents can already fill in the draft while the registration bill
     * is open, but the flow only moves past it once it is settled.
     */
    public function currentStep(): string
    {
        return match ($this->status) {
            RegistrantStatus::Draft, RegistrantStatus::Revision => $this->isRegistrationBillSettled() ? 'data' : 'registration-bill',
            RegistrantStatus::Review => 'validation',
            RegistrantStatus::Validated => $this->hasBill(BillType::Settlement) ? 'settlement-bill' : 'announcement',
            RegistrantStatus::Announced => 'placement',
            RegistrantStatus::Placed => 'placement',
        };
    }
}
