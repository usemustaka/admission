<?php

use App\Enums\DefaultRole;
use App\Models\Employee;
use App\Models\Parentship;
use App\Models\Person;
use App\Models\Registrant;
use App\Models\Student;

beforeEach(function () {
    $this->actingAs(userWithRole(DefaultRole::Operator));
});

test('the list can be narrowed to the part people play and counts follow the other filters', function () {
    $applicant = Registrant::factory()->create(['person_id' => Person::factory()->create(['name' => 'Anak Calon'])->id]);
    $student = Student::factory()->create(['person_id' => Person::factory()->create(['name' => 'Anak Siswa'])->id]);
    $parent = Parentship::factory()->create(['person_id' => $applicant->person_id, 'related_person_id' => Person::factory()->create(['name' => 'Orang Tua'])->id]);
    Employee::factory()->create(['person_id' => Person::factory()->create(['name' => 'Bu Guru'])->id]);
    Person::factory()->create(['name' => 'Tanpa Peran']);

    $this->get(route('admin.people.index', ['jenis' => 'siswa']))
        ->assertInertia(fn ($page) => $page
            ->where('people.data.0.name', 'Anak Calon')
            ->where('people.data.0.roles', ['Calon siswa'])
            ->where('people.data.1.roles', ['Siswa'])
            ->has('people.data', 2)
            ->where('counts.kinds', ['all' => Person::count(), 'siswa' => 2, 'keluarga' => 1, 'pegawai' => 1]));

    $this->get(route('admin.people.index', ['jenis' => 'keluarga']))
        ->assertInertia(fn ($page) => $page->has('people.data', 1)->where('people.data.0.name', 'Orang Tua')->where('people.data.0.roles', ['Orang tua/wali']));

    $this->get(route('admin.people.index', ['jenis' => 'pegawai']))
        ->assertInertia(fn ($page) => $page->has('people.data', 1)->where('people.data.0.name', 'Bu Guru'));

    $this->get(route('admin.people.index', ['jenis' => 'siswa', 'cari' => 'Siswa']))
        ->assertInertia(fn ($page) => $page->has('people.data', 1)->where('counts.kinds.all', 1)->where('counts.kinds.keluarga', 0));
});

test('people with an incomplete NIK can be listed', function () {
    Person::factory()->create(['name' => 'Lengkap', 'nik' => '3201111111111111']);
    Person::factory()->create(['name' => 'Kosong', 'nik' => null]);
    Person::factory()->create(['name' => 'Kurang', 'nik' => '12345']);

    $this->get(route('admin.people.index', ['data' => 'nik']))
        ->assertInertia(fn ($page) => $page
            ->has('people.data', 2)
            ->where('people.data.0.name', 'Kosong')
            ->where('people.data.0.nik_complete', false)
            ->where('people.data.1.name', 'Kurang')
            ->where('counts.data.nik', 2));
});

test('people nobody can reach are listed, but a parent with a phone is enough contact for a child', function () {
    $reachableChild = Person::factory()->create(['name' => 'Anak Terjangkau', 'phone' => null]);
    Parentship::factory()->create(['person_id' => $reachableChild->id, 'related_person_id' => Person::factory()->create(['phone' => '081234567890'])->id]);
    $strandedChild = Person::factory()->create(['name' => 'Anak Terputus', 'phone' => null]);
    Parentship::factory()->create(['person_id' => $strandedChild->id, 'related_person_id' => Person::factory()->create(['name' => 'Ortu Tanpa HP', 'phone' => ''])->id]);

    $this->get(route('admin.people.index', ['data' => 'kontak']))
        ->assertInertia(fn ($page) => $page
            ->where('counts.data.kontak', 2)
            ->has('people.data', 2)
            ->where('people.data.0.has_contact', false));

    $this->get(route('admin.people.index', ['cari' => 'Terjangkau']))
        ->assertInertia(fn ($page) => $page->where('people.data.0.has_contact', true));
});

test('people can be sorted by name or by when they were added', function () {
    $later = now()->addDays(10);
    Person::factory()->create(['name' => 'Budi', 'created_at' => $later->copy()->subDays(2)]);
    Person::factory()->create(['name' => 'Ani', 'created_at' => $later->copy()->subDay()]);
    Person::factory()->create(['name' => 'Candra', 'created_at' => $later]);

    $this->get(route('admin.people.index'))
        ->assertInertia(fn ($page) => $page->where('filters.urut', 'nama')->where('filters.arah', 'asc')->where('people.data.0.name', fn ($name) => strcmp($name, 'Ani') <= 0));

    $this->get(route('admin.people.index', ['urut' => 'nama', 'arah' => 'desc']))
        ->assertInertia(fn ($page) => $page->where('people.data.0.name', fn ($name) => strcmp($name, 'Candra') >= 0));

    $this->get(route('admin.people.index', ['urut' => 'terbaru']))
        ->assertInertia(fn ($page) => $page->where('people.data.0.name', 'Candra')->where('people.data.2.name', 'Budi')->where('filters.arah', 'desc'));
});

test('unknown filter values are ignored instead of failing', function () {
    Person::factory()->create();

    $this->get(route('admin.people.index', ['jenis' => 'hantu', 'data' => 'apa', 'urut' => 'x']))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('people.data', Person::count())->where('filters.jenis', '')->where('filters.data', ''));
});
