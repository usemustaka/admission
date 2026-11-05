<?php

use App\Enums\DefaultRole;
use App\Enums\RegistrantStatus;
use App\Models\Person;
use App\Models\Registrant;
use App\Models\Wave;

beforeEach(function () {
    $this->actingAs(userWithRole(DefaultRole::Operator));
});

function queuedRegistrant(int $daysAgo, array $attributes = [], ?string $name = null): Registrant
{
    return Registrant::factory()->create([
        'status' => RegistrantStatus::Review,
        'created_at' => now()->subDays($daysAgo),
        ...($name === null ? [] : ['person_id' => Person::factory()->create(['name' => $name])->id]),
        ...$attributes,
    ]);
}

test('without filters the queue runs through every registrant of the status, oldest first', function () {
    $waveA = Wave::factory()->create();
    $waveB = Wave::factory()->create();
    $first = queuedRegistrant(3, ['wave_id' => $waveA->id]);
    $second = queuedRegistrant(2, ['wave_id' => $waveB->id]);

    $this->get(route('admin.registrants.show', $first))->assertInertia(fn ($page) => $page
        ->where('queue.next_id', $second->id)
        ->where('queueContext.query', [])
        ->where('queueContext.order', 'dari yang paling lama mendaftar'));
});

test('the queue follows the filters of the list it was opened from', function () {
    $waveA = Wave::factory()->create();
    $waveB = Wave::factory()->create();
    $first = queuedRegistrant(4, ['wave_id' => $waveA->id]);
    queuedRegistrant(3, ['wave_id' => $waveB->id]);
    $third = queuedRegistrant(2, ['wave_id' => $waveA->id]);

    $this->get(route('admin.registrants.show', ['registrant' => $first, 'gelombang' => $waveA->id]))->assertInertia(fn ($page) => $page
        ->where('queue', ['position' => 1, 'total' => 2, 'previous_id' => null, 'next_id' => $third->id])
        ->where('queueContext.query', ['gelombang' => (string) $waveA->id]));

    $this->get(route('admin.registrants.show', ['registrant' => $third, 'gelombang' => $waveA->id]))->assertInertia(fn ($page) => $page
        ->where('queue.previous_id', $first->id)
        ->where('queue.next_id', null));
});

test('registrants waiting for the NIK check are not in the queue', function () {
    $first = queuedRegistrant(4);
    $flagged = queuedRegistrant(3, ['needs_identity_review' => true]);
    $third = queuedRegistrant(2);

    $this->get(route('admin.registrants.show', $first))->assertInertia(fn ($page) => $page
        ->where('queue.total', 2)
        ->where('queue.next_id', $third->id));

    $this->get(route('admin.registrants.show', $third))->assertInertia(fn ($page) => $page
        ->where('queue.previous_id', $first->id));

    $this->get(route('admin.registrants.show', $flagged))->assertOk();
});

test('the queue keeps the order of the list when the link carries one', function () {
    $citra = queuedRegistrant(3, name: 'Citra');
    $adi = queuedRegistrant(2, name: 'Adi');
    $budi = queuedRegistrant(1, name: 'Budi');

    $this->get(route('admin.registrants.show', ['registrant' => $adi, 'urut' => 'nama', 'arah' => 'asc']))->assertInertia(fn ($page) => $page
        ->where('queue', ['position' => 1, 'total' => 3, 'previous_id' => null, 'next_id' => $budi->id])
        ->where('queueContext.order', 'menurut nama, A ke Z'));

    $this->get(route('admin.registrants.show', ['registrant' => $budi, 'urut' => 'nama', 'arah' => 'asc']))->assertInertia(fn ($page) => $page
        ->where('queue.previous_id', $adi->id)
        ->where('queue.next_id', $citra->id));

    $this->get(route('admin.registrants.show', ['registrant' => $adi, 'urut' => 'terbaru', 'arah' => 'desc']))->assertInertia(fn ($page) => $page
        ->where('queue', ['position' => 2, 'total' => 3, 'previous_id' => $budi->id, 'next_id' => $citra->id])
        ->where('queueContext.order', 'dari yang paling baru mendaftar'));
});
