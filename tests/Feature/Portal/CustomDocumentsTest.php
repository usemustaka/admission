<?php

use App\Enums\DefaultRole;
use App\Enums\RegistrantStatus;
use App\Models\Parentship;
use App\Models\Registrant;
use App\Models\RegistrantDocument;
use App\Models\Ruleset;
use App\Support\RegistrantDocuments;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake(RegistrantDocument::DISK);
    $this->withoutVite();
    $this->parent = userWithRole(DefaultRole::User);
    $this->ruleset = Ruleset::factory()->create();

    $this->actingAs(userWithRole(DefaultRole::Admin))->put(route('admin.rulesets.documents', $this->ruleset), [
        'documents' => ['family-card' => 'required', 'birth-certificate' => 'none', 'report-card' => 'none', 'diploma' => 'none', 'kip' => 'required', 'tahfidz-certificate' => 'none', 'achievement-certificate' => 'optional'],
        'custom' => [['label' => 'SHTKA', 'max_files' => 2, 'choice' => 'required']],
    ])->assertSessionHasNoErrors();

    $this->key = $this->ruleset->documents()->where('type', 'like', 'custom-%')->firstOrFail()->type;
    $this->registrant = Registrant::factory()->create(['status' => RegistrantStatus::Draft, 'ruleset_id' => $this->ruleset->id, 'ruleset_snapshot' => $this->ruleset->fresh()->toSnapshot(), 'registered_by_person_id' => $this->parent->person_id]);
    Parentship::factory()->create(['person_id' => $this->registrant->person_id, 'related_person_id' => $this->parent->person_id]);
    auth()->logout();
});

test('the parent is asked for the custom file and the supporting files the ruleset switched on', function () {
    $overview = RegistrantDocuments::overview($this->registrant);

    expect(collect($overview['requirements'])->pluck('label')->all())->toBe(['Kartu Keluarga (KK)', 'SHTKA'])
        ->and(collect($overview['requirements'])->firstWhere('label', 'SHTKA')['max_files'])->toBe(2)
        ->and(collect($overview['supporting'])->pluck('type')->all())->toBe(['achievement-certificate', 'kip'])
        ->and(RegistrantDocuments::missing($this->registrant))->toBe(['Kartu Keluarga (KK)', 'SHTKA (kurang 2 berkas)', 'Kartu Indonesia Pintar (KIP)']);
});

test('the parent uploads a custom file into its slots and it counts as present once every slot is full', function () {
    $this->actingAs($this->parent);

    foreach ([1, 2] as $slot) {
        $this->post(route('portal.children.documents.store', $this->registrant), ['type' => $this->key, 'slot' => $slot, 'file' => UploadedFile::fake()->create("shtka-{$slot}.pdf", 100, 'application/pdf')])->assertSessionHasNoErrors();
    }

    expect(RegistrantDocument::where('type', $this->key)->count())->toBe(2)
        ->and(RegistrantDocuments::missing($this->registrant->refresh()))->toBe(['Kartu Keluarga (KK)', 'Kartu Indonesia Pintar (KIP)']);

    $this->post(route('portal.children.documents.store', $this->registrant), ['type' => $this->key, 'slot' => 3, 'file' => UploadedFile::fake()->create('x.pdf', 10, 'application/pdf')])->assertSessionHasErrors('slot');
});

test('a file the ruleset did not switch on, or a made-up custom key, is refused', function () {
    $this->actingAs($this->parent);

    $this->post(route('portal.children.documents.store', $this->registrant), ['type' => 'tahfidz-certificate', 'file' => UploadedFile::fake()->create('t.pdf', 10, 'application/pdf')])->assertSessionHasErrors('type');
    $this->post(route('portal.children.documents.store', $this->registrant), ['type' => 'custom-zzzzzzzz', 'file' => UploadedFile::fake()->create('t.pdf', 10, 'application/pdf')])->assertSessionHasErrors('type');

    expect(RegistrantDocument::count())->toBe(0);
});

test('a registration keeps its custom file name after the ruleset renames or removes it', function () {
    $this->actingAs(userWithRole(DefaultRole::Admin))->put(route('admin.rulesets.documents', $this->ruleset), [
        'documents' => ['family-card' => 'required', 'birth-certificate' => 'none', 'report-card' => 'none', 'diploma' => 'none'],
        'custom' => [['key' => $this->key, 'label' => 'Nama baru', 'max_files' => 1, 'choice' => 'optional']],
    ]);

    expect(collect(RegistrantDocuments::overview($this->registrant->fresh())['requirements'])->pluck('label')->all())->toContain('SHTKA');

    $this->put(route('admin.rulesets.documents', $this->ruleset), ['documents' => ['family-card' => 'required', 'birth-certificate' => 'none', 'report-card' => 'none', 'diploma' => 'none']]);

    expect($this->ruleset->documents()->where('type', $this->key)->exists())->toBeFalse()
        ->and(collect(RegistrantDocuments::overview($this->registrant->fresh())['requirements'])->pluck('label')->all())->toContain('SHTKA');
});

test('a registration made before supporting files could be switched off still offers all of them', function () {
    $legacy = admissionRegistrant();

    expect(collect(RegistrantDocuments::overview($legacy)['supporting'])->pluck('type')->all())
        ->toBe(['achievement-certificate', 'tahfidz-certificate', 'kip', 'hardship-letter', 'other-support']);
});
