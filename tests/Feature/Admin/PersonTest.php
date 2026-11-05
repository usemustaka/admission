<?php

use App\Enums\DefaultRole;
use App\Models\Person;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

test('guests are redirected and users without staff access are forbidden', function () {
    $this->get(route('admin.people.index'))->assertRedirect(route('login'));

    $this->actingAs(User::factory()->create())->get(route('admin.people.index'))->assertForbidden();
});

test('people can be searched by name or by the start of the NIK', function () {
    $this->actingAs(userWithRole(DefaultRole::Operator));
    Person::factory()->create(['name' => 'Ahmad Fauzi', 'nik' => '3201111111111111']);
    Person::factory()->create(['name' => 'Budi Santoso', 'nik' => '3302222222222222']);

    $this->get(route('admin.people.index', ['cari' => 'fauzi']))
        ->assertInertia(fn ($page) => $page->has('people.data', 1)->where('people.data.0.name', 'Ahmad Fauzi'));

    $this->get(route('admin.people.index', ['cari' => '3302']))
        ->assertInertia(fn ($page) => $page->has('people.data', 1)->where('people.data.0.name', 'Budi Santoso'));
});

test('staff can add a person with a photo', function () {
    Storage::fake('public');
    $this->actingAs(userWithRole(DefaultRole::Operator));

    $response = $this->post(route('admin.people.store'), [
        'name' => 'Siti Aisyah',
        'nik' => '3201987654321098',
        'gender' => 'female',
        'photo' => UploadedFile::fake()->image('foto.jpg'),
    ]);

    $person = Person::where('nik', '3201987654321098')->firstOrFail();

    $response->assertRedirect(route('admin.people.show', $person));
    Storage::disk('public')->assertExists($person->photo_path);
});

test('adding a NIK that already exists leads to the same person instead of a duplicate', function () {
    $this->actingAs(userWithRole(DefaultRole::Operator));
    $existing = Person::factory()->create(['nik' => '3201987654321098']);

    $this->post(route('admin.people.store'), ['name' => 'Nama Lain', 'nik' => '3201987654321098'])
        ->assertRedirect(route('admin.people.show', $existing));

    expect(Person::where('nik', '3201987654321098')->count())->toBe(1)
        ->and($existing->refresh()->name)->not->toBe('Nama Lain');
});

test('a NIK must have 16 digits', function () {
    $this->actingAs(userWithRole(DefaultRole::Operator));

    $this->post(route('admin.people.store'), ['name' => 'Budi', 'nik' => '12345'])->assertSessionHasErrors('nik');
});

test('a person can be edited but cannot take over another persons NIK', function () {
    $this->actingAs(userWithRole(DefaultRole::Operator));
    $person = Person::factory()->create();
    $other = Person::factory()->create(['nik' => '3201987654321098']);

    $this->put(route('admin.people.update', $person), ['name' => 'Nama Baru', 'nik' => $other->nik])
        ->assertSessionHasErrors('nik');

    $this->put(route('admin.people.update', $person), ['name' => 'Nama Baru', 'nik' => $person->nik])
        ->assertRedirect(route('admin.people.show', $person));

    expect($person->refresh()->name)->toBe('Nama Baru');
});

test('a photo can be replaced and removed, and old files are cleaned up', function () {
    Storage::fake('public');
    $this->actingAs(userWithRole(DefaultRole::Operator));
    $person = Person::factory()->create();

    $this->put(route('admin.people.update', $person), ['name' => $person->name, 'photo' => UploadedFile::fake()->image('a.png')]);
    $first = $person->refresh()->photo_path;
    Storage::disk('public')->assertExists($first);

    $this->put(route('admin.people.update', $person), ['name' => $person->name, 'photo' => UploadedFile::fake()->image('b.png')]);
    Storage::disk('public')->assertMissing($first);

    $this->put(route('admin.people.update', $person), ['name' => $person->name, 'remove_photo' => true]);
    expect($person->refresh()->photo_path)->toBeNull();
});

test('the quick lookup returns matches and skips the excluded person', function () {
    $this->actingAs(userWithRole(DefaultRole::Operator));
    $self = Person::factory()->create(['name' => 'Rahmat Hidayat']);
    Person::factory()->create(['name' => 'Rahmat Hakim']);

    $this->getJson(route('admin.people.search', ['q' => 'Rahmat', 'except' => $self->id]))
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.name', 'Rahmat Hakim');
});
