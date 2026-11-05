<?php

use App\Enums\DefaultRole;
use App\Enums\RegistrantStatus;
use App\Enums\WaveState;
use App\Models\Registrant;
use App\Models\Wave;

test('a wave is upcoming, ongoing or ended depending on its dates', function () {
    $today = today();

    expect(Wave::factory()->make(['starts_on' => $today->copy()->addDay()])->state())->toBe(WaveState::Upcoming)
        ->and(Wave::factory()->make(['ends_on' => $today->copy()->subDay()])->state())->toBe(WaveState::Ended)
        ->and(Wave::factory()->make(['starts_on' => $today->copy()->subDay(), 'ends_on' => $today->copy()->addDay()])->state())->toBe(WaveState::Ongoing)
        ->and(Wave::factory()->make(['starts_on' => $today, 'ends_on' => $today])->state())->toBe(WaveState::Ongoing)
        ->and(Wave::factory()->make()->state())->toBe(WaveState::Ongoing);
});

test('the wave list shows state, time left and registrant counts per status', function () {
    $this->actingAs(userWithRole(DefaultRole::Operator));

    $ongoing = Wave::factory()->create(['name' => 'Berjalan', 'starts_on' => today()->subDays(9), 'ends_on' => today()->addDays(10)]);
    $upcoming = Wave::factory()->create(['name' => 'Nanti', 'starts_on' => today()->addDays(5), 'ends_on' => today()->addDays(30)]);
    $ended = Wave::factory()->create(['name' => 'Lalu', 'starts_on' => today()->subDays(30), 'ends_on' => today()->subDays(3), 'is_open' => false]);

    Registrant::factory()->count(2)->create(['wave_id' => $ongoing->id, 'status' => RegistrantStatus::Review]);
    Registrant::factory()->create(['wave_id' => $ongoing->id, 'status' => RegistrantStatus::Validated]);
    Registrant::factory()->create(['wave_id' => $ended->id]);

    $this->get(route('admin.waves.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('waves', 3)
            ->where('waves.0.name', 'Berjalan')
            ->where('waves.0.state', 'ongoing')
            ->where('waves.0.is_accepting', true)
            ->where('waves.0.days_left', 10)
            ->where('waves.0.progress', 50)
            ->where('waves.0.registrants.total', 3)
            ->where('waves.0.registrants.by_status', [
                ['status' => 'review', 'label' => 'Sedang diperiksa', 'count' => 2],
                ['status' => 'validated', 'label' => 'Data valid', 'count' => 1],
            ])
            ->where('waves.1.name', 'Nanti')
            ->where('waves.1.state', 'upcoming')
            ->where('waves.1.days_until_start', 5)
            ->where('waves.1.progress', 0)
            ->where('waves.1.is_accepting', false)
            ->where('waves.2.name', 'Lalu')
            ->where('waves.2.state', 'ended')
            ->where('waves.2.days_since_end', 3)
            ->where('waves.2.progress', 100)
            ->where('waves.2.registrants.total', 1));

    expect($upcoming->registrants()->count())->toBe(0);
});

test('a wave without dates has no progress and never ends, and a closed wave is not accepting', function () {
    $this->actingAs(userWithRole(DefaultRole::Operator));
    Wave::factory()->create(['name' => 'Tanpa tanggal', 'is_open' => false]);

    $this->get(route('admin.waves.index'))
        ->assertInertia(fn ($page) => $page
            ->where('waves.0.state', 'ongoing')
            ->where('waves.0.progress', null)
            ->where('waves.0.days_left', null)
            ->where('waves.0.is_accepting', false));
});
