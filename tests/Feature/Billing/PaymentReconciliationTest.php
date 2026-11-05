<?php

use App\Enums\Ability;
use App\Enums\DefaultRole;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Payment;
use App\Models\PaymentAllocation;
use App\Models\User;
use Illuminate\Support\Carbon;

beforeEach(function () {
    $this->withoutVite();

    $this->parent = User::factory()->create();
    [$this->kid, $this->bill] = billedChild($this->parent, [5000000]);
    $this->item = $this->bill->items->first();

    /** A payment of the parent, verified at the given WIB moment (or pending/rejected with `$status`). */
    $this->payment = function (int $amount, array $attributes = []): Payment {
        $payment = Payment::factory()->create([
            'payer_person_id' => $this->parent->person_id,
            'amount' => $amount,
            'status' => PaymentStatus::Verified,
            'verified_at' => '2026-11-05 10:00:00',
            ...$attributes,
        ]);
        PaymentAllocation::factory()->create(['payment_id' => $payment->id, 'bill_item_id' => $this->item->id, 'amount' => $amount]);

        return $payment;
    };

    $this->admin = userWithRole(DefaultRole::Admin);
    $this->actingAs($this->admin);
});

describe('the day', function () {
    test('sums verified payments per method, counting every method even without payments', function () {
        ($this->payment)(100000, ['method' => PaymentMethod::Transfer]);
        ($this->payment)(250000, ['method' => PaymentMethod::Transfer, 'verified_at' => '2026-11-05 15:45:00']);
        ($this->payment)(75000, ['method' => PaymentMethod::Cash]);
        ($this->payment)(900000, ['verified_at' => '2026-11-04 10:00:00']);
        ($this->payment)(800000, ['status' => PaymentStatus::Rejected]);
        ($this->payment)(700000, ['status' => PaymentStatus::Pending, 'verified_at' => null]);

        $this->get(route('admin.payments.reconciliation', ['tanggal' => '2026-11-05']))->assertOk()->assertInertia(fn ($page) => $page
            ->component('admin/payments/reconciliation')
            ->where('date', '2026-11-05')
            ->where('methods.0', ['value' => 'transfer', 'label' => 'Transfer bank', 'count' => 2, 'total' => 350000])
            ->where('methods.1', ['value' => 'cash', 'label' => 'Tunai', 'count' => 1, 'total' => 75000])
            ->where('verified.count', 3)
            ->where('verified.total', 425000)
            ->where('verified.transfer_total', 350000)
            ->has('verified.rows', 3)
            ->where('verified.rows.0.verified_time', '10:00'));
    });

    test('a payment verified at 23:30 WIB belongs to that day and one at 00:30 to the next', function () {
        ($this->payment)(111000, ['verified_at' => '2026-11-04 23:59:59']);
        ($this->payment)(222000, ['verified_at' => '2026-11-05 00:00:00']);
        ($this->payment)(333000, ['verified_at' => '2026-11-05 23:30:00']);
        ($this->payment)(444000, ['verified_at' => '2026-11-06 00:30:00']);

        $this->get(route('admin.payments.reconciliation', ['tanggal' => '2026-11-05']))
            ->assertInertia(fn ($page) => $page->where('verified.total', 555000)->where('verified.count', 2));

        $this->get(route('admin.payments.reconciliation', ['tanggal' => '2026-11-06']))
            ->assertInertia(fn ($page) => $page->where('verified.total', 444000)->where('verified.count', 1));

        $this->get(route('admin.payments.reconciliation', ['tanggal' => '2026-11-04']))
            ->assertInertia(fn ($page) => $page->where('verified.total', 111000));
    });

    test('opens on today in WIB when no date is given or the date is malformed', function () {
        Carbon::setTestNow('2026-11-05 23:30:00');
        ($this->payment)(123000, ['verified_at' => '2026-11-05 23:10:00']);

        $this->get(route('admin.payments.reconciliation'))->assertInertia(fn ($page) => $page
            ->where('date', '2026-11-05')->where('today', '2026-11-05')->where('verified.total', 123000));

        $this->get(route('admin.payments.reconciliation', ['tanggal' => 'kemarin']))->assertInertia(fn ($page) => $page->where('date', '2026-11-05'));
    });

    test('a payment taken back no longer counts', function () {
        $payment = ($this->payment)(100000);

        $this->put(route('admin.payments.void', $payment), ['to' => 'rejected', 'reason' => 'Keliru']);

        $this->get(route('admin.payments.reconciliation', ['tanggal' => '2026-11-05']))->assertInertia(fn ($page) => $page->where('verified.total', 0));
    });
});

describe('what was received but still waits', function () {
    test('lists the pending payments paid that day with how many days they have waited', function () {
        Carbon::setTestNow('2026-11-08 10:00:00');
        $waiting = ($this->payment)(150000, ['status' => PaymentStatus::Pending, 'verified_at' => null, 'paid_on' => '2026-11-05']);
        ($this->payment)(160000, ['status' => PaymentStatus::Pending, 'verified_at' => null, 'paid_on' => '2026-11-06']);
        ($this->payment)(170000, ['status' => PaymentStatus::Rejected, 'paid_on' => '2026-11-05']);
        ($this->payment)(180000, ['paid_on' => '2026-11-05']);

        $this->get(route('admin.payments.reconciliation', ['tanggal' => '2026-11-05']))->assertInertia(fn ($page) => $page
            ->where('pending.count', 1)
            ->where('pending.total', 150000)
            ->has('pending.rows', 1)
            ->where('pending.rows.0.id', $waiting->id)
            ->where('pending.rows.0.age_days', 3));
    });

    test('a payment received today has waited zero days', function () {
        Carbon::setTestNow('2026-11-05 22:00:00');
        ($this->payment)(150000, ['status' => PaymentStatus::Pending, 'verified_at' => null, 'paid_on' => '2026-11-05']);

        $this->get(route('admin.payments.reconciliation'))->assertInertia(fn ($page) => $page->where('pending.rows.0.age_days', 0));
    });
});

describe('who may see it', function () {
    test('staff who may view payments can, parents cannot', function () {
        $this->actingAs($this->parent)->get(route('admin.payments.reconciliation'))->assertForbidden();
        $this->actingAs(userWithRole(DefaultRole::Operator))->get(route('admin.payments.reconciliation'))->assertOk();
    });

    test('the page tells whether the CSV export is allowed', function () {
        $viewer = User::factory()->create();
        $viewer->givePermissionTo([Ability::AdminAccess->value, Ability::PaymentView->value]);

        $this->actingAs($viewer)->get(route('admin.payments.reconciliation'))->assertInertia(fn ($page) => $page->where('can.export', false));
        $this->actingAs($this->admin)->get(route('admin.payments.reconciliation'))->assertInertia(fn ($page) => $page->where('can.export', true));
    });
});

describe('the CSV export', function () {
    test('lists the verified and the waiting payments of the day with a byte order mark', function () {
        ($this->payment)(100000, ['method' => PaymentMethod::Cash]);
        ($this->payment)(200000, ['verified_at' => '2026-11-04 12:00:00']);
        ($this->payment)(300000, ['status' => PaymentStatus::Pending, 'verified_at' => null, 'paid_on' => '2026-11-05']);

        $response = $this->get(route('admin.payments.reconciliation.export', ['tanggal' => '2026-11-05']));
        $response->assertOk()->assertDownload('rekonsiliasi-2026-11-05.csv');

        $csv = $response->streamedContent();
        $rows = array_map('str_getcsv', array_filter(explode("\n", substr($csv, 3))));

        expect(str_starts_with($csv, "\xEF\xBB\xBF"))->toBeTrue()
            ->and($rows)->toHaveCount(3)
            ->and($rows[0][0])->toBe('Kelompok')
            ->and($rows[1][0])->toBe('Terverifikasi')
            ->and($rows[1][7])->toBe('100000')
            ->and($rows[1][10])->toBe('2026-11-05 10:00')
            ->and($rows[2][0])->toBe('Diterima, menunggu verifikasi')
            ->and($rows[2][7])->toBe('300000')
            ->and($csv)->not->toContain('200000');
    });

    test('keeps spreadsheets from running text as a formula', function () {
        $this->parent->person->update(['name' => '=HYPERLINK("http://x")']);
        ($this->payment)(100000);

        expect($this->get(route('admin.payments.reconciliation.export', ['tanggal' => '2026-11-05']))->streamedContent())->toContain("\"'=HYPERLINK");
    });

    test('needs the export ability on top of the right to view payments', function () {
        $viewer = User::factory()->create();
        $viewer->givePermissionTo([Ability::AdminAccess->value, Ability::PaymentView->value]);

        $this->actingAs($viewer)->get(route('admin.payments.reconciliation.export'))->assertForbidden();
        $this->actingAs($this->parent)->get(route('admin.payments.reconciliation.export'))->assertForbidden();

        $viewer->givePermissionTo(Ability::PaymentExport->value);

        $this->actingAs($viewer->fresh())->get(route('admin.payments.reconciliation.export'))->assertOk();
    });
});
