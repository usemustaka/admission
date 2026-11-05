<?php

use App\Actions\Billing\ReopenPayment;
use App\Actions\Billing\VoidPayment;
use App\Enums\Ability;
use App\Enums\DefaultRole;
use App\Enums\NoteVisibility;
use App\Enums\PaymentStatus;
use App\Models\Bill;
use App\Models\Note;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Models\Registrant;
use App\Models\User;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $this->parent = User::factory()->create();
    [$this->kid, $this->bill] = billedChild($this->parent, [150000, 50000]);
    [$this->item1, $this->item2] = $this->bill->items->all();

    $this->verifier = User::factory()->create();
    $this->verifier->person->update(['name' => 'Bu Rina']);
    $this->payment = Payment::factory()->create([
        'payer_person_id' => $this->parent->person_id,
        'amount' => 200000,
        'status' => PaymentStatus::Verified,
        'verified_by_user_id' => $this->verifier->id,
        'verified_at' => now()->subDays(40),
    ]);
    PaymentAllocation::factory()->create(['payment_id' => $this->payment->id, 'bill_item_id' => $this->item1->id, 'amount' => 150000]);
    PaymentAllocation::factory()->create(['payment_id' => $this->payment->id, 'bill_item_id' => $this->item2->id, 'amount' => 50000]);

    $this->admin = userWithRole(DefaultRole::Admin);
    $this->admin->person->update(['name' => 'Pak Admin']);
    $this->actingAs($this->admin);
    $this->billStatus = fn (): string => Bill::find($this->bill->id)->loadTotals()->status()->value;
});

describe('voiding a verified payment', function () {
    test('to rejected, it stops counting towards the bill and keeps the reason', function () {
        expect(($this->billStatus)())->toBe('paid');

        $this->put(route('admin.payments.void', $this->payment), ['to' => 'rejected', 'reason' => 'Dana tidak pernah masuk'])
            ->assertRedirect(route('admin.payments.show', $this->payment))
            ->assertSessionHasNoErrors();

        $this->payment->refresh();

        expect($this->payment->status)->toBe(PaymentStatus::Rejected)
            ->and($this->payment->rejection_reason)->toBe('Dibatalkan: Dana tidak pernah masuk')
            ->and($this->payment->verified_by_user_id)->toBe($this->admin->id)
            ->and($this->payment->allocations()->count())->toBe(2)
            ->and(($this->billStatus)())->toBe('unpaid');
    });

    test('to pending, it goes back to the queue with nobody marked as the verifier', function () {
        $this->put(route('admin.payments.void', $this->payment), ['to' => 'pending', 'reason' => 'Mau dibagi ulang'])->assertSessionHasNoErrors();

        $this->payment->refresh();

        expect($this->payment->status)->toBe(PaymentStatus::Pending)
            ->and($this->payment->verified_by_user_id)->toBeNull()
            ->and($this->payment->verified_at)->toBeNull()
            ->and($this->payment->rejection_reason)->toBeNull()
            ->and(($this->billStatus)())->toBe('unpaid');
    });

    test('a bill that was paid by two payments falls back to partial', function () {
        verifiedPayment($this->item1, 150000);

        $this->put(route('admin.payments.void', $this->payment), ['to' => 'rejected', 'reason' => 'Keliru'])->assertSessionHasNoErrors();

        expect(($this->billStatus)())->toBe('partial');
    });

    test('writes a system note on the payer and on the child before the verifier is overwritten', function () {
        $this->put(route('admin.payments.void', $this->payment), ['to' => 'rejected', 'reason' => 'Salah input']);

        $notes = Note::query()->whereNull('author_id')->get();
        $forPayer = $notes->firstWhere('person_id', $this->parent->person_id);
        $forChild = $notes->firstWhere('person_id', $this->kid->person_id);

        expect($notes)->toHaveCount(2)
            ->and($forPayer->body)->toContain("Pembayaran #{$this->payment->id} Rp200.000")
            ->and($forPayer->body)->toContain('diverifikasi oleh Bu Rina')
            ->and($forPayer->body)->toContain('dibatalkan oleh Pak Admin')
            ->and($forPayer->body)->toContain('Salah input')
            ->and($forChild->body)->toBe($forPayer->body)
            ->and($forChild->noteable_id)->toBe($this->kid->person_id)
            ->and($forChild->visibility)->toBe(NoteVisibility::Public)
            ->and($forChild->shared_with_family)->toBeFalse();
    });

    test('does not roll back the status of the registrant', function () {
        $status = $this->kid->status;

        $this->put(route('admin.payments.void', $this->payment), ['to' => 'rejected', 'reason' => 'Keliru']);

        expect(Registrant::find($this->kid->id)->status)->toBe($status);
    });

    test('needs a reason and a valid destination', function () {
        $this->put(route('admin.payments.void', $this->payment), ['to' => 'rejected', 'reason' => '  '])->assertSessionHasErrors('reason');
        $this->put(route('admin.payments.void', $this->payment), ['to' => 'verified', 'reason' => 'x'])->assertSessionHasErrors('to');
        $this->put(route('admin.payments.void', $this->payment), ['reason' => 'x'])->assertSessionHasErrors('to');

        expect($this->payment->refresh()->status)->toBe(PaymentStatus::Verified)->and(Note::count())->toBe(0);
    });

    test('a second void of the same payment is refused and leaves one set of notes', function () {
        $this->put(route('admin.payments.void', $this->payment), ['to' => 'rejected', 'reason' => 'Keliru']);

        $this->put(route('admin.payments.void', $this->payment), ['to' => 'pending', 'reason' => 'Lagi'])->assertSessionHasErrors('payment');

        expect($this->payment->refresh()->status)->toBe(PaymentStatus::Rejected)->and(Note::count())->toBe(2);
    });

    test('a stale copy of the payment cannot void it after someone else did', function () {
        $stale = Payment::findOrFail($this->payment->id);
        app(VoidPayment::class)->handle($this->payment, $this->admin, PaymentStatus::Pending, 'Pertama');

        expect(fn () => app(VoidPayment::class)->handle($stale, $this->admin, PaymentStatus::Rejected, 'Kedua'))->toThrow(ValidationException::class)
            ->and($this->payment->refresh()->status)->toBe(PaymentStatus::Pending);
    });

    test('a pending payment cannot be voided', function () {
        $this->payment->update(['status' => PaymentStatus::Pending]);

        $this->put(route('admin.payments.void', $this->payment), ['to' => 'rejected', 'reason' => 'Keliru'])->assertSessionHasErrors('payment');
    });

    test('a rejected payment cannot be voided', function () {
        $this->payment->update(['status' => PaymentStatus::Rejected]);

        $this->put(route('admin.payments.void', $this->payment), ['to' => 'pending', 'reason' => 'Keliru'])->assertSessionHasErrors('payment');
    });
});

describe('reopening a rejected payment', function () {
    beforeEach(function () {
        $this->payment->update([
            'status' => PaymentStatus::Rejected,
            'rejection_reason' => 'Bukti tidak terbaca',
            'verified_by_user_id' => $this->verifier->id,
        ]);
    });

    test('puts it back in the queue, clears the reason and keeps the allocations', function () {
        $this->put(route('admin.payments.reopen', $this->payment), ['reason' => 'Orang tua kirim bukti baru lewat WA'])
            ->assertRedirect(route('admin.payments.show', $this->payment))
            ->assertSessionHasNoErrors();

        $this->payment->refresh();

        expect($this->payment->status)->toBe(PaymentStatus::Pending)
            ->and($this->payment->rejection_reason)->toBeNull()
            ->and($this->payment->verified_by_user_id)->toBeNull()
            ->and($this->payment->allocations()->count())->toBe(2);
    });

    test('keeps who rejected it and why in a system note', function () {
        $this->put(route('admin.payments.reopen', $this->payment), ['reason' => 'Ada bukti baru']);

        $note = Note::query()->whereNull('author_id')->where('person_id', $this->parent->person_id)->sole();

        expect($note->body)->toContain('ditolak oleh Bu Rina')
            ->and($note->body)->toContain('Bukti tidak terbaca')
            ->and($note->body)->toContain('dibuka ulang oleh Pak Admin')
            ->and($note->body)->toContain('Ada bukti baru')
            ->and(Note::whereNull('author_id')->where('person_id', $this->kid->person_id)->count())->toBe(1);
    });

    test('needs a reason', function () {
        $this->put(route('admin.payments.reopen', $this->payment), ['reason' => ''])->assertSessionHasErrors('reason');

        expect($this->payment->refresh()->status)->toBe(PaymentStatus::Rejected);
    });

    test('a payment that is not rejected cannot be reopened', function () {
        foreach ([PaymentStatus::Pending, PaymentStatus::Verified] as $status) {
            $this->payment->update(['status' => $status]);

            $this->put(route('admin.payments.reopen', $this->payment), ['reason' => 'Coba'])->assertSessionHasErrors('payment');

            expect($this->payment->refresh()->status)->toBe($status);
        }

        expect(fn () => app(ReopenPayment::class)->handle($this->payment, $this->admin, 'Coba'))->toThrow(ValidationException::class);
    });

    test('can be verified again afterwards', function () {
        $this->put(route('admin.payments.reopen', $this->payment), ['reason' => 'Ada bukti baru']);
        $this->put(route('admin.payments.verify', $this->payment))->assertSessionHasNoErrors();

        expect($this->payment->refresh()->status)->toBe(PaymentStatus::Verified)->and(($this->billStatus)())->toBe('paid');
    });
});

describe('who may correct payments', function () {
    test('the default operator cannot void or reopen', function () {
        $this->actingAs(userWithRole(DefaultRole::Operator));

        $this->put(route('admin.payments.void', $this->payment), ['to' => 'rejected', 'reason' => 'Keliru'])->assertForbidden();
        $this->put(route('admin.payments.reopen', $this->payment), ['reason' => 'Keliru'])->assertForbidden();

        expect($this->payment->refresh()->status)->toBe(PaymentStatus::Verified);
    });

    test('a parent cannot', function () {
        $this->actingAs($this->parent)->put(route('admin.payments.void', $this->payment), ['to' => 'rejected', 'reason' => 'Keliru'])->assertForbidden();
    });

    test('the void ability alone is not enough without the right to view payments', function () {
        $staff = User::factory()->create();
        $staff->givePermissionTo([Ability::AdminAccess->value, Ability::PaymentVoid->value]);

        $this->actingAs($staff)->put(route('admin.payments.void', $this->payment), ['to' => 'rejected', 'reason' => 'Keliru'])->assertForbidden();

        $staff->givePermissionTo(Ability::PaymentView->value);

        $this->actingAs($staff->fresh())->put(route('admin.payments.void', $this->payment), ['to' => 'rejected', 'reason' => 'Keliru'])->assertSessionHasNoErrors();
    });

    test('the detail page offers the right correction only to those who may', function () {
        $this->get(route('admin.payments.show', $this->payment))->assertInertia(fn ($page) => $page
            ->where('can.void', true)->where('can.reopen', false)->where('can.receipt', true)->where('can.verify', false));

        $this->actingAs(userWithRole(DefaultRole::Operator))->get(route('admin.payments.show', $this->payment))
            ->assertInertia(fn ($page) => $page->where('can.void', false)->where('can.reopen', false));

        $this->payment->update(['status' => PaymentStatus::Rejected]);

        $this->actingAs($this->admin)->get(route('admin.payments.show', $this->payment))
            ->assertInertia(fn ($page) => $page->where('can.void', false)->where('can.reopen', true)->where('can.receipt', false));
    });

    test('the detail page lists what the system noted about this payment', function () {
        $this->put(route('admin.payments.void', $this->payment), ['to' => 'pending', 'reason' => 'Mau dibagi ulang']);

        $this->get(route('admin.payments.show', $this->payment))->assertInertia(fn ($page) => $page
            ->has('history', 1)
            ->where('history.0.body', fn (string $body) => str_contains($body, 'Mau dibagi ulang')));
    });
});
