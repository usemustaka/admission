<?php

use App\Enums\DefaultRole;
use App\Enums\PaymentStatus;
use App\Enums\RegistrantStatus;
use App\Models\ExamDay;
use App\Models\ExamSession;
use App\Models\Payment;
use App\Models\Registrant;
use App\Models\RegistrantStatusChange;
use App\Models\Wave;

function queue(array $props, string $key): array
{
    return collect($props['attention'])->firstWhere('key', $key);
}

test('the dashboard counts what is waiting for the committee', function () {
    $this->actingAs(userWithRole(DefaultRole::Admin));

    Registrant::factory()->count(2)->create(['status' => RegistrantStatus::Review]);
    Registrant::factory()->create(['status' => RegistrantStatus::Validated]);
    Registrant::factory()->create(['status' => RegistrantStatus::Announced]);
    Registrant::factory()->create(['status' => RegistrantStatus::Draft, 'needs_identity_review' => true]);
    Payment::factory()->count(2)->create(['status' => PaymentStatus::Pending, 'amount' => 150000]);
    Payment::factory()->create(['status' => PaymentStatus::Verified]);

    $this->get(route('dashboard'))->assertInertia(function ($page) {
        $props = $page->toArray()['props'];

        expect(queue($props, 'in_review')['count'])->toBe(2)
            ->and(queue($props, 'to_announce')['count'])->toBe(1)
            ->and(queue($props, 'to_place')['count'])->toBe(1)
            ->and(queue($props, 'needs_identity_review')['count'])->toBe(1)
            ->and(queue($props, 'pending_payments')['count'])->toBe(2)
            ->and(queue($props, 'pending_payments')['amount'])->toBe(300000)
            ->and($props['flow']['total'])->toBe(5)
            ->and($props['payments']['pending_count'])->toBe(2);
    });
});

test('the flow lists every step even when it is empty', function () {
    $this->actingAs(userWithRole(DefaultRole::Admin));

    Registrant::factory()->create(['status' => RegistrantStatus::Review]);

    $this->get(route('dashboard'))->assertInertia(fn ($page) => $page
        ->has('flow.steps', count(RegistrantStatus::cases()))
        ->where('flow.steps.0.status', 'draft')
        ->where('flow.steps.0.count', 0));
});

test('upcoming exam sessions show how many seats are taken', function () {
    $this->actingAs(userWithRole(DefaultRole::Admin));

    $day = ExamDay::factory()->create(['date' => today()->addDays(3)]);
    $session = ExamSession::factory()->create(['exam_day_id' => $day->id, 'quota' => 20]);
    Registrant::factory()->count(3)->create(['exam_session_id' => $session->id]);
    ExamSession::factory()->create([
        'exam_day_id' => ExamDay::factory()->create(['date' => today()->addDays(30)])->id,
    ]);

    $this->get(route('dashboard'))->assertInertia(fn ($page) => $page
        ->has('exams', 1)
        ->where('exams.0.taken', 3)
        ->where('exams.0.quota', 20));
});

test('only waves that accept registrations today are listed', function () {
    $this->actingAs(userWithRole(DefaultRole::Admin));

    Wave::factory()->create(['name' => 'Gelombang aktif', 'is_open' => true, 'starts_on' => today()->subDay(), 'ends_on' => today()->addDays(10)]);
    Wave::factory()->create(['name' => 'Gelombang ditutup', 'is_open' => false]);

    $this->get(route('dashboard'))->assertInertia(fn ($page) => $page
        ->has('waves', 1)
        ->where('waves.0.name', 'Gelombang aktif')
        ->where('waves.0.days_left', 10));
});

test('recent activity shows the latest status changes first', function () {
    $this->actingAs(userWithRole(DefaultRole::Admin));

    $older = RegistrantStatusChange::factory()->create(['to_status' => RegistrantStatus::Review, 'created_at' => now()->subDay()]);
    $newer = RegistrantStatusChange::factory()->create(['to_status' => RegistrantStatus::Validated]);

    $this->get(route('dashboard'))->assertInertia(fn ($page) => $page
        ->where('activity.0.id', $newer->id)
        ->where('activity.1.id', $older->id)
        ->where('activity.0.to_label', RegistrantStatus::Validated->label()));
});

test('an operator without payment access does not get payment figures', function () {
    $this->actingAs(userWithRole(DefaultRole::Operator));

    $this->get(route('dashboard'))->assertInertia(function ($page) {
        $props = $page->toArray()['props'];

        $canSeePayments = in_array('admission::payment.view', $props['auth']['abilities'], true);

        expect($props['payments'] !== null)->toBe($canSeePayments)
            ->and(collect($props['attention'])->contains('key', 'pending_payments'))->toBe($canSeePayments);
    });
});
