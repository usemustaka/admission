<?php

use App\Enums\DefaultRole;
use App\Models\Organization;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->withoutVite();
    Storage::fake('public');
});

test('an admin uploads a PDF brochure when creating a lembaga and the front page offers it', function () {
    $this->actingAs(userWithRole(DefaultRole::Admin));

    $this->post(route('admin.organizations.store'), [
        'name' => 'MA Putra', 'code' => 'ma-putra', 'category' => 'sekolah', 'is_active' => true,
        'brochure' => UploadedFile::fake()->create('brosur.pdf', 200, 'application/pdf'),
    ])->assertSessionHasNoErrors();

    $organization = Organization::where('code', 'ma-putra')->firstOrFail();

    expect($organization->brochure_path)->not->toBeNull();
    Storage::disk('public')->assertExists($organization->brochure_path);

    auth()->logout();

    $this->get(route('home'))->assertInertia(fn ($page) => $page->component('welcome')->where('brochures', [['id' => $organization->id, 'name' => 'MA Putra']]));
    $this->get(route('brochures.show', $organization))->assertOk()->assertDownload('brosur-ma-putra.pdf');
});

test('only PDF, JPG and PNG files up to 10 MB are accepted', function () {
    $this->actingAs(userWithRole(DefaultRole::Admin));

    $this->post(route('admin.organizations.store'), ['name' => 'X', 'code' => 'x', 'brochure' => UploadedFile::fake()->create('brosur.docx', 20, 'application/msword')])->assertSessionHasErrors('brochure');
    $this->post(route('admin.organizations.store'), ['name' => 'X', 'code' => 'x', 'brochure' => UploadedFile::fake()->create('besar.pdf', 11000, 'application/pdf')])->assertSessionHasErrors('brochure');
});

test('replacing a brochure deletes the old file, and saving without one keeps it', function () {
    $this->actingAs(userWithRole(DefaultRole::Admin));
    $organization = Organization::factory()->create(['brochure_path' => UploadedFile::fake()->create('lama.pdf', 50, 'application/pdf')->store('brochures', 'public')]);
    $old = $organization->brochure_path;

    $this->put(route('admin.organizations.update', $organization), ['name' => $organization->name, 'code' => $organization->code, 'is_active' => true])->assertSessionHasNoErrors();
    expect($organization->refresh()->brochure_path)->toBe($old);

    $this->put(route('admin.organizations.update', $organization), [
        'name' => $organization->name, 'code' => $organization->code, 'is_active' => true,
        'brochure' => UploadedFile::fake()->create('baru.pdf', 50, 'application/pdf'),
    ])->assertSessionHasNoErrors();

    expect($organization->refresh()->brochure_path)->not->toBe($old);
    Storage::disk('public')->assertMissing($old);
    Storage::disk('public')->assertExists($organization->brochure_path);
});

test('a brochure can be removed', function () {
    $this->actingAs(userWithRole(DefaultRole::Admin));
    $organization = Organization::factory()->create(['brochure_path' => UploadedFile::fake()->create('lama.pdf', 50, 'application/pdf')->store('brochures', 'public')]);
    $old = $organization->brochure_path;

    $this->put(route('admin.organizations.update', $organization), ['name' => $organization->name, 'code' => $organization->code, 'is_active' => true, 'remove_brochure' => true])->assertSessionHasNoErrors();

    expect($organization->refresh()->brochure_path)->toBeNull();
    Storage::disk('public')->assertMissing($old);
});

test('an inactive lembaga or one without a brochure offers nothing to download', function () {
    $inactive = Organization::factory()->create(['is_active' => false, 'brochure_path' => UploadedFile::fake()->create('a.pdf', 50, 'application/pdf')->store('brochures', 'public')]);
    $without = Organization::factory()->create();

    $this->get(route('brochures.show', $inactive))->assertNotFound();
    $this->get(route('brochures.show', $without))->assertNotFound();
    $this->get(route('home'))->assertInertia(fn ($page) => $page->where('brochures', []));
});

test('a brochure can also be a JPG or PNG image and downloads with its stored extension (.jpeg is kept as .jpg)', function () {
    $this->actingAs(userWithRole(DefaultRole::Admin));

    foreach (['jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png'] as $extension => $mime) {
        $this->post(route('admin.organizations.store'), [
            'name' => "Lembaga {$extension}", 'code' => "lembaga-{$extension}", 'is_active' => true,
            'brochure' => UploadedFile::fake()->image("brosur.{$extension}"),
        ])->assertSessionHasNoErrors();

        $organization = Organization::where('code', "lembaga-{$extension}")->firstOrFail();

        $response = $this->get(route('brochures.show', $organization))->assertOk()->assertDownload("brosur-lembaga-{$extension}.".($extension === 'jpeg' ? 'jpg' : $extension));
        expect($response->headers->get('content-type'))->toContain($mime);
    }
});
