<?php

use App\Enums\DefaultRole;
use App\Enums\DocumentType;
use App\Models\Ruleset;
use App\Models\RulesetDocument;

beforeEach(function () {
    $this->actingAs(userWithRole(DefaultRole::Admin));
    $this->ruleset = Ruleset::factory()->create();
});

test('a new ruleset asks for the family card, birth certificate and report cards, offers the diploma and the supporting files as optional', function () {
    $this->post(route('admin.rulesets.store'), ['name' => 'Baru', 'wave_id' => '', 'organization_id' => '', 'gender' => '', 'is_active' => true]);

    $asked = Ruleset::where('name', 'Baru')->firstOrFail()->documents->mapWithKeys(fn ($document) => [$document->type => $document->is_required])->all();

    expect($asked)->toBe(['family-card' => true, 'birth-certificate' => true, 'report-card' => true, 'diploma' => false, 'achievement-certificate' => false, 'tahfidz-certificate' => false, 'kip' => false, 'hardship-letter' => false, 'other-support' => false]);
});

test('the committee chooses per file whether it is not asked, optional or required', function () {
    RulesetDocument::factory()->create(['ruleset_id' => $this->ruleset->id, 'type' => DocumentType::Diploma]);

    $this->put(route('admin.rulesets.documents', $this->ruleset), ['documents' => [
        'family-card' => 'required', 'birth-certificate' => 'optional', 'report-card' => 'none', 'diploma' => 'none',
    ]])->assertRedirect();

    expect($this->ruleset->documents()->get()->mapWithKeys(fn ($document) => [$document->type => $document->is_required])->all())
        ->toBe(['family-card' => true, 'birth-certificate' => false]);
});

test('changing a choice again updates the row instead of adding one', function () {
    $payload = fn (string $choice) => ['documents' => ['family-card' => $choice, 'birth-certificate' => 'none', 'report-card' => 'none', 'diploma' => 'none']];

    $this->put(route('admin.rulesets.documents', $this->ruleset), $payload('optional'));
    $this->put(route('admin.rulesets.documents', $this->ruleset), $payload('required'));

    expect($this->ruleset->documents()->count())->toBe(1)->and($this->ruleset->documents()->first()->is_required)->toBeTrue();
});

test('only the core files can be configured and every choice must be valid', function () {
    $this->put(route('admin.rulesets.documents', $this->ruleset), ['documents' => [
        'family-card' => 'maybe', 'birth-certificate' => 'none', 'report-card' => 'none', 'diploma' => 'none', 'kip' => 'required',
    ]])->assertSessionHasErrors('documents.family-card');

    expect($this->ruleset->documents()->count())->toBe(0);
});

test('a registration keeps the files its ruleset asked for when it was made', function () {
    RulesetDocument::factory()->create(['ruleset_id' => $this->ruleset->id, 'type' => DocumentType::FamilyCard]);
    $snapshot = $this->ruleset->toSnapshot();

    $this->ruleset->documents()->delete();

    expect($snapshot['documents'])->toBe([['type' => 'family-card', 'required' => true]])->and($snapshot['documents_configured'])->toBeTrue();
});

test('staff who may only view rulesets cannot change the files', function () {
    $this->actingAs(registrantViewer());

    $this->put(route('admin.rulesets.documents', $this->ruleset), ['documents' => ['family-card' => 'none', 'birth-certificate' => 'none', 'report-card' => 'none', 'diploma' => 'none']])->assertForbidden();
});

test('the ruleset page lists each core file with its current choice', function () {
    $this->withoutVite();
    RulesetDocument::factory()->optional()->create(['ruleset_id' => $this->ruleset->id, 'type' => DocumentType::Diploma]);

    $this->get(route('admin.rulesets.show', $this->ruleset))->assertInertia(fn ($page) => $page
        ->has('documents', 9)
        ->where('documents.0.choice', 'none')
        ->where('documents.3.type', 'diploma')
        ->where('documents.3.choice', 'optional'));
});

test('every built-in file, supporting ones included, can be switched off or made required', function () {
    $this->put(route('admin.rulesets.documents', $this->ruleset), ['documents' => [
        'family-card' => 'none', 'birth-certificate' => 'none', 'report-card' => 'none', 'diploma' => 'none',
        'kip' => 'required', 'tahfidz-certificate' => 'optional',
    ]])->assertSessionHasNoErrors();

    expect($this->ruleset->documents()->get()->mapWithKeys(fn ($document) => [$document->type => $document->is_required])->all())
        ->toBe(['tahfidz-certificate' => false, 'kip' => true]);

    $this->put(route('admin.rulesets.documents', $this->ruleset), ['documents' => [
        'family-card' => 'none', 'birth-certificate' => 'none', 'report-card' => 'none', 'diploma' => 'none', 'kip' => 'none',
    ]]);

    expect($this->ruleset->documents()->pluck('type')->all())->toBe(['tahfidz-certificate']);
});

test('the committee adds custom files with a name and a number of files, and a rename keeps the key', function () {
    $core = ['family-card' => 'none', 'birth-certificate' => 'none', 'report-card' => 'none', 'diploma' => 'none'];

    $this->put(route('admin.rulesets.documents', $this->ruleset), ['documents' => $core, 'custom' => [
        ['label' => 'SHTKA', 'max_files' => 1, 'choice' => 'optional'],
        ['label' => 'KTP orang tua', 'max_files' => 2, 'choice' => 'required'],
    ]])->assertSessionHasNoErrors();

    $rows = $this->ruleset->documents()->orderBy('id')->get();
    expect($rows)->toHaveCount(2)
        ->and($rows[0]->label)->toBe('SHTKA')
        ->and($rows[1]->max_files)->toBe(2)
        ->and($rows[1]->is_required)->toBeTrue()
        ->and($rows[0]->isCustom())->toBeTrue();

    $key = $rows[0]->type;

    $this->put(route('admin.rulesets.documents', $this->ruleset), ['documents' => $core, 'custom' => [
        ['key' => $key, 'label' => 'SHTKA 2026', 'max_files' => 1, 'choice' => 'required'],
    ]])->assertSessionHasNoErrors();

    expect($this->ruleset->documents()->count())->toBe(1)
        ->and($this->ruleset->documents()->first()->type)->toBe($key)
        ->and($this->ruleset->documents()->first()->label)->toBe('SHTKA 2026');
});

test('custom file names cannot repeat or be empty', function () {
    $this->put(route('admin.rulesets.documents', $this->ruleset), ['documents' => ['family-card' => 'none', 'birth-certificate' => 'none', 'report-card' => 'none', 'diploma' => 'none'], 'custom' => [
        ['label' => 'SHTKA', 'max_files' => 1, 'choice' => 'optional'],
        ['label' => 'shtka', 'max_files' => 1, 'choice' => 'optional'],
        ['label' => '', 'max_files' => 1, 'choice' => 'optional'],
    ]])->assertSessionHasErrors(['custom.0.label', 'custom.1.label', 'custom.2.label']);
});
