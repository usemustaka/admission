<?php

namespace App\Models;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use Database\Factories\PaymentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Money received from one payer, split over bill items by its allocations. The amount always equals the sum
 * of its allocations.
 *
 * @property int $id
 * @property int $payer_person_id
 * @property int $amount
 * @property Carbon $paid_on
 * @property PaymentMethod $method
 * @property string|null $note
 * @property string|null $proof_path
 * @property PaymentStatus $status
 * @property int|null $created_by_user_id
 * @property int|null $verified_by_user_id
 * @property Carbon|null $verified_at
 * @property string|null $rejection_reason
 * @property Carbon|null $created_at
 */
#[Fillable(['payer_person_id', 'amount', 'paid_on', 'method', 'note', 'proof_path', 'status', 'created_by_user_id', 'verified_by_user_id', 'verified_at', 'rejection_reason'])]
class Payment extends Model
{
    /** @use HasFactory<PaymentFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'paid_on' => 'date',
            'method' => PaymentMethod::class,
            'status' => PaymentStatus::class,
            'verified_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Person, $this>
     */
    public function payer(): BelongsTo
    {
        return $this->belongsTo(Person::class, 'payer_person_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function verifiedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by_user_id');
    }

    /**
     * @return HasMany<PaymentAllocation, $this>
     */
    public function allocations(): HasMany
    {
        return $this->hasMany(PaymentAllocation::class);
    }

    public function isPending(): bool
    {
        return $this->status === PaymentStatus::Pending;
    }
}
