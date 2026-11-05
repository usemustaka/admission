<?php

namespace App\Actions\Billing;

use App\Enums\PaymentMethod;
use App\Models\User;
use Illuminate\Http\UploadedFile;

final readonly class PaymentData
{
    /**
     * @param  list<array{bill_item_id: int, amount: int}>  $items  what the payment pays, per bill item
     * @param  bool  $confirmDuplicate  the committee saw the warning about an identical payment and records it anyway
     */
    public function __construct(
        public int $payerPersonId,
        public string $paidOn,
        public PaymentMethod $method,
        public array $items,
        public User $createdBy,
        public ?string $note = null,
        public ?UploadedFile $proof = null,
        public bool $confirmDuplicate = false,
    ) {}
}
