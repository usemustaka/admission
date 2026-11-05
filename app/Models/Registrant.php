<?php

namespace App\Models;

use App\Enums\BillType;
use App\Enums\RegistrantStatus;
use Database\Factories\RegistrantFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
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
 * @property int|null $ruleset_id
 * @property array{ruleset_id: int, name: string, bills: list<array{type: string, items: list<array{name: string, amount: int}>}>, exam_aspects: list<array{name: string, passing_score: int}>}|null $ruleset_snapshot
 * @property int $registered_by_person_id
 * @property RegistrantStatus $status
 * @property bool $needs_identity_review
 * @property string|null $formal_photo_path
 * @property-read string|null $formal_photo_url
 * @property Carbon|null $created_at
 */
#[Fillable(['person_id', 'organization_id', 'wave_id', 'ruleset_id', 'ruleset_snapshot', 'registered_by_person_id', 'status', 'needs_identity_review', 'formal_photo_path'])]
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
            'status' => RegistrantStatus::class,
            'needs_identity_review' => 'boolean',
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
     * Registrations of the children the user is a parent or guardian of.
     *
     * @param  Builder<Registrant>  $query
     */
    #[Scope]
    protected function forParent(Builder $query, User $user): void
    {
        $query->whereIn('person_id', Parentship::query()
            ->where('related_person_id', $user->person_id)
            ->select('person_id'));
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
     * Whether the registration bill is out of the way: there is none, or it has been paid.
     * Payments are not recorded yet, so a registration bill always counts as unpaid.
     */
    public function isRegistrationBillSettled(): bool
    {
        return ! $this->hasBill(BillType::Registration);
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
