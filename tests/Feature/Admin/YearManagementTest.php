<?php

use App\Enums\DefaultRole;
use App\Enums\RegistrantStatus;
use App\Models\Period;
use App\Models\Registrant;
use App\Models\Student;
use App\Models\User;
use App\Models\Year;

beforeEach(fn () => $this->withoutVite());

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function yearPayload(array $overrides = []): array
{
    return ['name' => '2030/2031', 'starts_on' => '2030-07-01', 'ends_on' => '2031-06-30', ...$overrides];
}

/**
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function periodPayload(array $overrides = []): array
{
    return ['name' => 'Ganjil', 'sequence' => 1, 'starts_on' => '2030-07-01', 'ends_on' => '2030-12-31', ...$overrides];
}

test('year screens are closed to parents and to staff without the year ability', function () {
    $year = Year::factory()->create();
    $period = Period::factory()->create(['year_id' => $year->id]);

    foreach ([User::factory()->create(), userWithRole(DefaultRole::Operator)] as $outsider) {
        $this->actingAs($outsider);

        $this->get(route('admin.years.index'))->assertForbidden();
        $this->post(route('admin.years.store'), yearPayload())->assertForbidden();
        $this->put(route('admin.years.update', $year), yearPayload())->assertForbidden();
        $this->post(route('admin.years.activate', $year))->assertForbidden();
        $this->delete(route('admin.years.destroy', $year))->assertForbidden();
        $this->post(route('admin.years.periods.store', $year), periodPayload())->assertForbidden();
        $this->put(route('admin.years.periods.update', [$year, $period]), periodPayload())->assertForbidden();
        $this->delete(route('admin.years.periods.destroy', [$year, $period]))->assertForbidden();
    }

    expect(Year::count())->toBe(1);
});

test('the list shows each year with its periods, students and a suggestion for the next one', function () {
    $this->actingAs(userWithRole(DefaultRole::Admin));
    $old = Year::factory()->create(['name' => '2025/2026', 'starts_on' => '2025-07-01', 'ends_on' => '2026-06-30']);
    $active = Year::factory()->active()->create(['name' => '2026/2027', 'starts_on' => '2026-07-01', 'ends_on' => '2027-06-30']);
    Period::factory()->create(['year_id' => $active->id, 'name' => 'Ganjil', 'sequence' => 1, 'starts_on' => '2026-07-01', 'ends_on' => '2026-12-31']);
    Student::factory()->create(['year_id' => $old->id]);

    $this->get(route('admin.years.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/years/index')
            ->where('years.0.name', '2026/2027')
            ->where('years.0.is_active', true)
            ->where('years.0.periods.0.name', 'Ganjil')
            ->where('years.0.delete_blocked', fn ($reason) => str_contains($reason, 'Aktifkan'))
            ->where('years.1.students_count', 1)
            ->where('years.1.delete_blocked', fn ($reason) => str_contains($reason, '1 siswa'))
            ->where('suggestion.name', '2027/2028')
            ->where('suggestion.starts_on', '2027-07-01')
            ->where('suggestion.ends_on', '2028-06-30')
            ->where('suggestion.copy_from_id', $active->id));
});

test('an admin creates a year, and the first one becomes the active year', function () {
    $this->actingAs(userWithRole(DefaultRole::Admin));

    $this->post(route('admin.years.store'), yearPayload())->assertRedirect(route('admin.years.index'));

    $year = Year::firstOrFail();
    expect($year->name)->toBe('2030/2031')->and($year->is_active)->toBeTrue();
});

test('a new year stays inactive unless asked, and activating it switches the others off', function () {
    $this->actingAs(userWithRole(DefaultRole::Admin));
    $current = Year::factory()->active()->create(['starts_on' => '2026-07-01', 'ends_on' => '2027-06-30']);

    $this->post(route('admin.years.store'), yearPayload())->assertSessionHasNoErrors();
    $next = Year::where('name', '2030/2031')->firstOrFail();
    expect($next->is_active)->toBeFalse()->and($current->refresh()->is_active)->toBeTrue();

    $this->post(route('admin.years.activate', $next))->assertRedirect();

    expect($next->refresh()->is_active)->toBeTrue()
        ->and($current->refresh()->is_active)->toBeFalse()
        ->and(Year::where('is_active', true)->count())->toBe(1);

    $this->post(route('admin.years.store'), yearPayload(['name' => '2032/2033', 'starts_on' => '2032-07-01', 'ends_on' => '2033-06-30', 'is_active' => true]));

    expect(Year::where('is_active', true)->pluck('name')->all())->toBe(['2032/2033']);
});

test('the only active year cannot be switched off from the form, but its details can change', function () {
    $this->actingAs(userWithRole(DefaultRole::Admin));
    $year = Year::factory()->active()->create(yearPayload(['is_active' => true]));

    $this->put(route('admin.years.update', $year), yearPayload(['is_active' => false]))->assertSessionHasErrors('is_active');
    expect($year->refresh()->is_active)->toBeTrue();

    $this->put(route('admin.years.update', $year), yearPayload(['name' => '2030/2031 (revisi)', 'is_active' => true]))->assertSessionHasNoErrors();
    expect($year->refresh()->name)->toBe('2030/2031 (revisi)')->and($year->is_active)->toBeTrue();
});

test('year names are unique and the end date follows the start date', function () {
    $this->actingAs(userWithRole(DefaultRole::Admin));
    Year::factory()->create(['name' => '2030/2031', 'starts_on' => '2020-07-01', 'ends_on' => '2021-06-30']);

    $this->post(route('admin.years.store'), yearPayload())->assertSessionHasErrors('name');
    $this->post(route('admin.years.store'), yearPayload(['name' => 'Lain', 'ends_on' => '2030-06-30']))->assertSessionHasErrors('ends_on');
    $this->post(route('admin.years.store'), ['name' => '', 'starts_on' => '', 'ends_on' => ''])->assertSessionHasErrors(['name', 'starts_on', 'ends_on']);
});

test('years cannot overlap, and a year cannot shrink past its periods', function () {
    $this->actingAs(userWithRole(DefaultRole::Admin));
    $existing = Year::factory()->create(['name' => '2030/2031', 'starts_on' => '2030-07-01', 'ends_on' => '2031-06-30']);

    $this->post(route('admin.years.store'), yearPayload(['name' => '2031/2032', 'starts_on' => '2031-06-30', 'ends_on' => '2032-06-29']))->assertSessionHasErrors('starts_on');
    $this->post(route('admin.years.store'), yearPayload(['name' => '2031/2032', 'starts_on' => '2031-07-01', 'ends_on' => '2032-06-30']))->assertSessionHasNoErrors();

    Period::factory()->create(['year_id' => $existing->id, 'starts_on' => '2030-07-01', 'ends_on' => '2030-12-31']);

    $this->put(route('admin.years.update', $existing), yearPayload(['ends_on' => '2030-10-31']))->assertSessionHasErrors('ends_on');
    $this->put(route('admin.years.update', $existing), yearPayload(['name' => '2030/2031 baru']))->assertSessionHasNoErrors();
    expect(Year::count())->toBe(2);
});

test('a new year can copy the period structure of an earlier year, shifted to the new dates', function () {
    $this->actingAs(userWithRole(DefaultRole::Admin));
    $source = Year::factory()->active()->create(['name' => '2026/2027', 'starts_on' => '2026-07-01', 'ends_on' => '2027-06-30']);
    Period::factory()->create(['year_id' => $source->id, 'name' => 'Ganjil', 'sequence' => 1, 'starts_on' => '2026-07-01', 'ends_on' => '2026-12-31', 'is_active' => true]);
    Period::factory()->create(['year_id' => $source->id, 'name' => 'Genap', 'sequence' => 2, 'starts_on' => '2027-01-01', 'ends_on' => '2027-06-30']);

    $this->post(route('admin.years.store'), [
        'name' => '2027/2028', 'starts_on' => '2027-07-01', 'ends_on' => '2028-06-30', 'copy_periods_from' => $source->id,
    ])->assertSessionHasNoErrors();

    $copy = Year::where('name', '2027/2028')->firstOrFail();

    expect($copy->periods()->orderBy('sequence')->get()->map(fn (Period $period) => [$period->name, $period->starts_on->toDateString(), $period->ends_on->toDateString(), $period->is_active])->all())
        ->toBe([['Ganjil', '2027-07-01', '2027-12-31', false], ['Genap', '2028-01-01', '2028-06-30', false]])
        ->and($source->periods()->count())->toBe(2);
});

test('a year can be deleted along with its periods, unless it is active or has students', function () {
    $this->actingAs(userWithRole(DefaultRole::Admin));
    $active = Year::factory()->active()->create();
    $withStudents = Year::factory()->create();
    Student::factory()->create(['year_id' => $withStudents->id]);
    $empty = Year::factory()->create();
    Period::factory()->create(['year_id' => $empty->id]);

    $this->delete(route('admin.years.destroy', $active))->assertSessionHasErrors('year');
    $this->delete(route('admin.years.destroy', $withStudents))->assertSessionHasErrors('year');
    expect(Year::count())->toBe(3);

    $this->delete(route('admin.years.destroy', $empty))->assertRedirect(route('admin.years.index'));

    expect(Year::whereKey($empty->id)->exists())->toBeFalse()->and(Period::count())->toBe(0);
});

describe('periods', function () {
    beforeEach(function () {
        $this->actingAs(userWithRole(DefaultRole::Admin));
        $this->year = Year::factory()->active()->create(['name' => '2030/2031', 'starts_on' => '2030-07-01', 'ends_on' => '2031-06-30']);
    });

    test('are created, edited and deleted inside a year', function () {
        $this->post(route('admin.years.periods.store', $this->year), periodPayload())->assertRedirect(route('admin.years.index'));

        $period = $this->year->periods()->firstOrFail();
        expect($period->name)->toBe('Ganjil')->and($period->is_active)->toBeFalse();

        $this->put(route('admin.years.periods.update', [$this->year, $period]), periodPayload(['name' => 'Semester 1']))->assertSessionHasNoErrors();
        expect($period->refresh()->name)->toBe('Semester 1');

        $this->delete(route('admin.years.periods.destroy', [$this->year, $period]))->assertRedirect(route('admin.years.index'));
        expect(Period::count())->toBe(0);
    });

    test('have a sequence that is unique per year', function () {
        $this->post(route('admin.years.periods.store', $this->year), periodPayload());
        $this->post(route('admin.years.periods.store', $this->year), periodPayload(['name' => 'Genap', 'starts_on' => '2031-01-01', 'ends_on' => '2031-06-30']))->assertSessionHasErrors('sequence');

        $other = Year::factory()->create(['starts_on' => '2040-07-01', 'ends_on' => '2041-06-30']);
        $this->post(route('admin.years.periods.store', $other), periodPayload(['starts_on' => '2040-07-01', 'ends_on' => '2040-12-31']))->assertSessionHasNoErrors();

        $second = $this->year->periods()->create(periodPayload(['name' => 'Genap', 'sequence' => 2, 'starts_on' => '2031-01-01', 'ends_on' => '2031-06-30']));
        $this->put(route('admin.years.periods.update', [$this->year, $second]), periodPayload(['name' => 'Genap', 'sequence' => 1, 'starts_on' => '2031-01-01', 'ends_on' => '2031-06-30']))->assertSessionHasErrors('sequence');
        $this->put(route('admin.years.periods.update', [$this->year, $second]), periodPayload(['name' => 'Genap', 'sequence' => 2, 'starts_on' => '2031-01-01', 'ends_on' => '2031-06-30']))->assertSessionHasNoErrors();
    });

    test('stay inside the dates of their year and never overlap', function () {
        $this->post(route('admin.years.periods.store', $this->year), periodPayload(['starts_on' => '2030-06-01']))->assertSessionHasErrors('starts_on');
        $this->post(route('admin.years.periods.store', $this->year), periodPayload(['ends_on' => '2031-07-01']))->assertSessionHasErrors('starts_on');
        $this->post(route('admin.years.periods.store', $this->year), periodPayload(['starts_on' => '2030-12-31', 'ends_on' => '2030-07-01']))->assertSessionHasErrors('ends_on');

        $this->post(route('admin.years.periods.store', $this->year), periodPayload())->assertSessionHasNoErrors();
        $this->post(route('admin.years.periods.store', $this->year), periodPayload(['name' => 'Genap', 'sequence' => 2, 'starts_on' => '2030-12-31', 'ends_on' => '2031-06-30']))->assertSessionHasErrors('starts_on');
        $this->post(route('admin.years.periods.store', $this->year), periodPayload(['name' => 'Genap', 'sequence' => 2, 'starts_on' => '2031-01-01', 'ends_on' => '2031-06-30']))->assertSessionHasNoErrors();

        expect($this->year->periods()->count())->toBe(2);
    });

    test('are active at most one at a time across all years', function () {
        $other = Year::factory()->create(['starts_on' => '2040-07-01', 'ends_on' => '2041-06-30']);
        $first = Period::factory()->create(['year_id' => $other->id, 'sequence' => 1, 'starts_on' => '2040-07-01', 'ends_on' => '2040-12-31', 'is_active' => true]);

        $this->post(route('admin.years.periods.store', $this->year), periodPayload(['is_active' => true]))->assertSessionHasNoErrors();
        $second = $this->year->periods()->firstOrFail();

        expect($second->is_active)->toBeTrue()->and($first->refresh()->is_active)->toBeFalse();

        $this->put(route('admin.years.periods.update', [$other, $first]), ['name' => $first->name, 'sequence' => 1, 'starts_on' => '2040-07-01', 'ends_on' => '2040-12-31', 'is_active' => true]);

        expect(Period::where('is_active', true)->pluck('id')->all())->toBe([$first->id]);

        $this->put(route('admin.years.periods.update', [$other, $first]), ['name' => $first->name, 'sequence' => 1, 'starts_on' => '2040-07-01', 'ends_on' => '2040-12-31', 'is_active' => false]);
        expect(Period::where('is_active', true)->count())->toBe(0);
    });
});

test('placement uses a year that was created from the screen', function () {
    $this->actingAs(userWithRole(DefaultRole::Admin));
    $registrant = Registrant::factory()->create(['status' => RegistrantStatus::Announced]);

    $this->get(route('admin.registrants.show', $registrant))->assertInertia(fn ($page) => $page->has('years', 0));
    $this->post(route('admin.registrants.place', $registrant), ['year_id' => 1, 'nis' => '2030001'])->assertSessionHasErrors('year_id');

    $this->post(route('admin.years.store'), yearPayload())->assertSessionHasNoErrors();
    $year = Year::firstOrFail();

    $this->get(route('admin.registrants.show', $registrant))->assertInertia(fn ($page) => $page
        ->has('years', 1)
        ->where('years.0.label', '2030/2031')
        ->where('years.0.is_active', true));

    $this->post(route('admin.registrants.place', $registrant), ['year_id' => $year->id, 'nis' => '2030001'])->assertSessionHasNoErrors();

    expect(Student::firstOrFail()->year_id)->toBe($year->id)
        ->and($registrant->refresh()->status)->toBe(RegistrantStatus::Placed);
});
