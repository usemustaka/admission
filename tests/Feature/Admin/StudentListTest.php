<?php

use App\Enums\DefaultRole;
use App\Models\Organization;
use App\Models\Person;
use App\Models\Student;
use App\Models\User;
use App\Models\Year;

beforeEach(function () {
    $this->withoutVite();
    $this->smp = Organization::factory()->create(['name' => 'SMP']);
    $this->sma = Organization::factory()->create(['name' => 'SMA']);
    $this->year = Year::factory()->active()->create(['name' => '2026/2027']);
    $this->oldYear = Year::factory()->create(['name' => '2025/2026']);

    $this->student = fn (string $name, array $attributes = []): Student => Student::factory()->create([
        'person_id' => Person::factory()->create(['name' => $name, 'nik' => $attributes['nik'] ?? null])->id,
        'organization_id' => $this->smp->id,
        'year_id' => $this->year->id,
        ...array_diff_key($attributes, ['nik' => true]),
    ]);
});

test('the student list is closed to parents and to staff without the student ability', function () {
    $this->get(route('admin.students.index'))->assertRedirect(route('login'));
    $this->actingAs(User::factory()->create())->get(route('admin.students.index'))->assertForbidden();

    $this->actingAs(userWithRole(DefaultRole::Operator))->get(route('admin.students.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/students/index')
            ->where('can.manage', false));

    $this->actingAs(userWithRole(DefaultRole::Admin))->get(route('admin.students.index'))
        ->assertInertia(fn ($page) => $page->where('can.manage', true));
});

test('the list shows name, NIS, lembaga, year and placement date, ordered by name', function () {
    $this->actingAs(userWithRole(DefaultRole::Operator));
    ($this->student)('Zahra', ['nis' => '002', 'nik' => '3201000000000002']);
    ($this->student)('Aisyah', ['nis' => '001', 'organization_id' => $this->sma->id, 'year_id' => $this->oldYear->id]);

    $this->get(route('admin.students.index'))->assertInertia(fn ($page) => $page
        ->has('students.data', 2)
        ->where('students.total', 2)
        ->where('students.data.0.name', 'Aisyah')
        ->where('students.data.0.nis', '001')
        ->where('students.data.0.organization', 'SMA')
        ->where('students.data.0.year', '2025/2026')
        ->where('students.data.1.nik', '3201000000000002')
        ->where('students.data.1.placed_at', now()->toDateString())
        ->has('options.organizations', 2)
        ->has('options.years', 2));
});

test('a student photo wins over the profile photo, and the profile photo is the fallback', function () {
    $this->actingAs(userWithRole(DefaultRole::Operator));
    $withStudentPhoto = ($this->student)('Aisyah', ['nis' => '001', 'photo_path' => 'students/photos/a.jpg']);
    $withProfilePhoto = ($this->student)('Budi', ['nis' => '002']);
    $withProfilePhoto->person->update(['photo_path' => 'people/b.jpg']);
    ($this->student)('Citra', ['nis' => '003']);

    $this->get(route('admin.students.index'))->assertInertia(fn ($page) => $page
        ->where('students.data.0.photo_url', fn ($url) => str_ends_with($url, 'students/photos/a.jpg'))
        ->where('students.data.1.photo_url', fn ($url) => str_ends_with($url, 'people/b.jpg'))
        ->where('students.data.2.photo_url', null));

    expect($withStudentPhoto->photo_path)->not->toBeNull();
});

test('search finds a name part, a NIS part, or the start of a NIK', function () {
    $this->actingAs(userWithRole(DefaultRole::Operator));
    ($this->student)('Ahmad Fauzi', ['nis' => '2026001', 'nik' => '3201111111111111']);
    ($this->student)('Budi Santoso', ['nis' => '2025777', 'nik' => '3202222222222222']);

    $names = fn (string $term): array => collect($this->get(route('admin.students.index', ['cari' => $term]))->viewData('page')['props']['students']['data'])->pluck('name')->all();

    expect($names('fauzi'))->toBe(['Ahmad Fauzi'])
        ->and($names('777'))->toBe(['Budi Santoso'])
        ->and($names('3202'))->toBe(['Budi Santoso'])
        ->and($names('2222'))->toBe([])
        ->and($names('zzz'))->toBe([]);
});

test('search treats percent and underscore as plain characters', function () {
    $this->actingAs(userWithRole(DefaultRole::Operator));
    ($this->student)('Ahmad', ['nis' => '2026_01']);
    ($this->student)('Budi', ['nis' => '2026X01']);
    ($this->student)('Citra', ['nis' => '5%']);

    $names = fn (string $term): array => collect($this->get(route('admin.students.index', ['cari' => $term]))->viewData('page')['props']['students']['data'])->pluck('name')->all();

    expect($names('2026_01'))->toBe(['Ahmad'])
        ->and($names('%'))->toBe(['Citra'])
        ->and($names('_'))->toBe(['Ahmad']);
});

test('lembaga and year filters narrow the list', function () {
    $this->actingAs(userWithRole(DefaultRole::Operator));
    ($this->student)('Ahmad', ['nis' => '001']);
    ($this->student)('Budi', ['nis' => '002', 'organization_id' => $this->sma->id]);
    ($this->student)('Citra', ['nis' => '003', 'year_id' => $this->oldYear->id]);

    $names = fn (array $query): array => collect($this->get(route('admin.students.index', $query))->viewData('page')['props']['students']['data'])->pluck('name')->all();

    expect($names(['lembaga' => $this->sma->id]))->toBe(['Budi'])
        ->and($names(['tahun' => $this->oldYear->id]))->toBe(['Citra'])
        ->and($names(['lembaga' => $this->smp->id, 'tahun' => $this->year->id]))->toBe(['Ahmad'])
        ->and($names(['lembaga' => $this->sma->id, 'tahun' => $this->oldYear->id]))->toBe([]);
});

test('the list sorts by name, NIS or placement date and ignores unknown sorts', function () {
    $this->actingAs(userWithRole(DefaultRole::Operator));
    ($this->student)('Ahmad', ['nis' => '300', 'created_at' => now()->subDays(2)]);
    ($this->student)('Budi', ['nis' => '100', 'created_at' => now()]);
    ($this->student)('Citra', ['nis' => '200', 'created_at' => now()->subDay()]);

    $names = fn (array $query): array => collect($this->get(route('admin.students.index', $query))->viewData('page')['props']['students']['data'])->pluck('name')->all();

    expect($names([]))->toBe(['Ahmad', 'Budi', 'Citra'])
        ->and($names(['urut' => 'nama', 'arah' => 'desc']))->toBe(['Citra', 'Budi', 'Ahmad'])
        ->and($names(['urut' => 'nis']))->toBe(['Budi', 'Citra', 'Ahmad'])
        ->and($names(['urut' => 'ditempatkan']))->toBe(['Budi', 'Citra', 'Ahmad'])
        ->and($names(['urut' => 'ditempatkan', 'arah' => 'asc']))->toBe(['Ahmad', 'Citra', 'Budi'])
        ->and($names(['urut' => 'nik; drop table students']))->toBe(['Ahmad', 'Budi', 'Citra']);
});

test('the list is paginated on the server', function () {
    $this->actingAs(userWithRole(DefaultRole::Operator));
    Student::factory()->count(23)->create(['organization_id' => $this->smp->id, 'year_id' => $this->year->id]);

    $this->get(route('admin.students.index'))->assertInertia(fn ($page) => $page->has('students.data', 20)->where('students.total', 23)->where('students.last_page', 2));
    $this->get(route('admin.students.index', ['page' => 2]))->assertInertia(fn ($page) => $page->has('students.data', 3));
});
