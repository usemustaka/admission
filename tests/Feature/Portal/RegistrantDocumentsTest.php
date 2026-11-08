<?php

use App\Enums\DefaultRole;
use App\Enums\RegistrantStatus;
use App\Enums\ScholarshipKind;
use App\Models\Parentship;
use App\Models\Registrant;
use App\Models\RegistrantDocument;
use App\Support\RegistrantDocuments;
use App\Support\RegistrantReadiness;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake(RegistrantDocument::DISK);

    $this->parent = userWithRole(DefaultRole::User);
    $this->registrant = admissionRegistrant(RegistrantStatus::Draft, [
        'registered_by_person_id' => $this->parent->person_id,
        'ruleset_snapshot' => [
            'ruleset_id' => 1, 'name' => 'Aturan', 'bills' => [], 'exam_aspects' => [],
            'documents' => [
                ['type' => 'family-card', 'required' => true],
                ['type' => 'report-card', 'required' => true],
                ['type' => 'diploma', 'required' => false],
            ],
        ],
    ]);
    Parentship::factory()->create(['person_id' => $this->registrant->person_id, 'related_person_id' => $this->parent->person_id]);

    $this->upload = fn (array $data = [], ?Registrant $registrant = null) => $this->actingAs($this->parent)
        ->post(route('portal.children.documents.store', $registrant ?? $this->registrant), [
            'type' => 'family-card',
            'file' => UploadedFile::fake()->create('kk.pdf', 200, 'application/pdf'),
            ...$data,
        ]);
});

describe('uploading', function () {
    test('a parent uploads a PDF or JPG into the private disk', function () {
        ($this->upload)()->assertSessionHasNoErrors();
        ($this->upload)(['type' => 'diploma', 'file' => UploadedFile::fake()->image('ijazah.jpg')])->assertSessionHasNoErrors();

        $document = $this->registrant->documents()->where('type', 'family-card')->sole();

        expect($this->registrant->documents()->count())->toBe(2)
            ->and($document->slot)->toBe(1)
            ->and($document->original_name)->toBe('kk.pdf')
            ->and($document->uploaded_by_user_id)->toBe($this->parent->id);
        Storage::disk(RegistrantDocument::DISK)->assertExists($document->path);
    });

    test('a second upload into the same slot replaces the file and removes the old one', function () {
        ($this->upload)();
        $old = $this->registrant->documents()->sole();

        ($this->upload)(['file' => UploadedFile::fake()->create('kk-baru.pdf', 100, 'application/pdf'), 'slot' => 1]);

        $new = $this->registrant->documents()->sole();
        expect($new->original_name)->toBe('kk-baru.pdf')->and($new->path)->not->toBe($old->path);
        Storage::disk(RegistrantDocument::DISK)->assertMissing($old->path);
        Storage::disk(RegistrantDocument::DISK)->assertExists($new->path);
    });

    test('files other than PDF or JPG, or above 1 MB, are refused', function () {
        ($this->upload)(['file' => UploadedFile::fake()->create('kk.docx', 100)])->assertSessionHasErrors('file');
        ($this->upload)(['file' => UploadedFile::fake()->image('kk.png')])->assertSessionHasErrors('file');
        ($this->upload)(['file' => UploadedFile::fake()->create('kk.pdf', 1025, 'application/pdf')])->assertSessionHasErrors('file');

        expect($this->registrant->documents()->count())->toBe(0);
    });

    test('a report card takes five files, one per semester', function () {
        foreach (range(1, 5) as $semester) {
            ($this->upload)(['type' => 'report-card', 'slot' => $semester])->assertSessionHasNoErrors();
        }

        ($this->upload)(['type' => 'report-card', 'slot' => 6])->assertSessionHasErrors('slot');
        expect($this->registrant->documents()->where('type', 'report-card')->count())->toBe(5);
    });

    test('without a slot the file takes the first free one and a full type refuses more', function () {
        foreach (range(1, 3) as $ignored) {
            ($this->upload)(['type' => 'tahfidz-certificate'])->assertSessionHasNoErrors();
        }

        expect($this->registrant->documents()->where('type', 'tahfidz-certificate')->pluck('slot')->all())->toBe([1, 2, 3]);

        ($this->upload)(['type' => 'tahfidz-certificate'])->assertSessionHasErrors('file');
    });

    test('a file the ruleset does not ask for is refused, but supporting files are always accepted', function () {
        ($this->upload)(['type' => 'birth-certificate'])->assertSessionHasErrors('type');
        ($this->upload)(['type' => 'achievement-certificate'])->assertSessionHasNoErrors();
    });

    test('another parent cannot upload for this child', function () {
        $stranger = userWithRole(DefaultRole::User);

        $this->actingAs($stranger)->post(route('portal.children.documents.store', $this->registrant), [
            'type' => 'family-card', 'file' => UploadedFile::fake()->create('kk.pdf', 10, 'application/pdf'),
        ])->assertForbidden();
    });

    test('the core files lock once the data is sent, but supporting files stay open until placement', function () {
        $this->registrant->update(['status' => RegistrantStatus::Review]);

        ($this->upload)()->assertForbidden();
        ($this->upload)(['type' => 'kip'])->assertSessionHasNoErrors();

        $this->registrant->update(['status' => RegistrantStatus::Placed]);

        ($this->upload)(['type' => 'kip', 'slot' => 1])->assertForbidden();
    });

    test('nothing can be uploaded while the NIK check is open', function () {
        $this->registrant->update(['needs_identity_review' => true]);

        ($this->upload)(['type' => 'kip'])->assertForbidden();
    });
});

describe('removing', function () {
    test('a parent removes a file and it leaves the disk', function () {
        ($this->upload)();
        $document = $this->registrant->documents()->sole();

        $this->actingAs($this->parent)->delete(route('portal.children.documents.destroy', [$this->registrant, $document]))->assertSessionHasNoErrors();

        expect($this->registrant->documents()->count())->toBe(0);
        Storage::disk(RegistrantDocument::DISK)->assertMissing($document->path);
    });

    test('a file of another registration cannot be removed through this one', function () {
        $other = RegistrantDocument::factory()->create();

        $this->actingAs($this->parent)->delete(route('portal.children.documents.destroy', [$this->registrant, $other]))->assertNotFound();
        expect(RegistrantDocument::query()->count())->toBe(1);
    });

    test('a required file cannot be removed after the data is sent', function () {
        ($this->upload)();
        $document = $this->registrant->documents()->sole();
        $this->registrant->update(['status' => RegistrantStatus::Review]);

        $this->actingAs($this->parent)->delete(route('portal.children.documents.destroy', [$this->registrant, $document]))->assertForbidden();
    });
});

describe('required files and sending', function () {
    test('missing required files hold back sending and a report card needs every semester', function () {
        expect(RegistrantReadiness::missing($this->registrant))
            ->toContain('Berkas Kartu Keluarga (KK)', 'Berkas Rapor semester 1-5 (kurang 5 berkas)')
            ->not->toContain('Berkas Ijazah / SKL');

        ($this->upload)();
        foreach (range(1, 4) as $semester) {
            ($this->upload)(['type' => 'report-card', 'slot' => $semester]);
        }

        expect(RegistrantReadiness::missing($this->registrant))->toContain('Berkas Rapor semester 1-5 (kurang 1 berkas)')
            ->not->toContain('Berkas Kartu Keluarga (KK)');

        ($this->upload)(['type' => 'report-card', 'slot' => 5]);

        expect(RegistrantDocuments::missing($this->registrant))->toBe([]);
    });

    test('a registration whose ruleset asks for nothing is not held back', function () {
        $this->registrant->update(['ruleset_snapshot' => null]);

        expect(RegistrantDocuments::missing($this->registrant))->toBe([]);
    });
});

describe('opening a file', function () {
    beforeEach(function () {
        ($this->upload)();
        $this->document = $this->registrant->documents()->sole();
    });

    test('the parent, and staff who may see registrants, can open it', function () {
        $this->actingAs($this->parent)->get(route('documents.show', $this->document))->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff');
        $this->actingAs(registrantViewer())->get(route('documents.show', $this->document))->assertOk();
    });

    test('another parent and guests cannot', function () {
        $this->actingAs(userWithRole(DefaultRole::User))->get(route('documents.show', $this->document))->assertForbidden();

        auth()->logout();
        $this->get(route('documents.show', $this->document))->assertRedirect(route('login'));
    });
});

describe('scholarship evidence', function () {
    test('files and the KIP number point at the kind of scholarship the committee may weigh', function () {
        expect(RegistrantDocuments::scholarshipKinds($this->registrant))->toBe([]);

        ($this->upload)(['type' => 'achievement-certificate']);
        ($this->upload)(['type' => 'tahfidz-certificate']);
        ($this->upload)(['type' => 'other-support']);

        expect(RegistrantDocuments::scholarshipKinds($this->registrant))->toBe([ScholarshipKind::Achievement, ScholarshipKind::Tahfidz]);

        $this->registrant->update(['kip_number' => '123456']);

        expect(RegistrantDocuments::scholarshipKinds($this->registrant->refresh()))->toContain(ScholarshipKind::Hardship);
    });

    test('a note alone counts as supporting evidence but points at no scholarship', function () {
        $this->registrant->update(['support_note' => 'Ayah sudah meninggal']);

        expect(RegistrantDocuments::hasSupportingEvidence($this->registrant))->toBeTrue()
            ->and(RegistrantDocuments::scholarshipKinds($this->registrant))->toBe([]);
    });

    test('the parent saves the KIP number and a note until the child is placed', function () {
        $this->actingAs($this->parent)->put(route('portal.children.documents.support', $this->registrant), ['kip_number' => ' 0123-45 ', 'support_note' => 'Yatim'])->assertSessionHasNoErrors();

        expect($this->registrant->refresh()->kip_number)->toBe('0123-45')->and($this->registrant->support_note)->toBe('Yatim');

        $this->put(route('portal.children.documents.support', $this->registrant), ['kip_number' => '', 'support_note' => ''])->assertSessionHasNoErrors();
        expect($this->registrant->refresh()->kip_number)->toBeNull();

        $this->registrant->update(['status' => RegistrantStatus::Placed]);
        $this->put(route('portal.children.documents.support', $this->registrant), ['kip_number' => '1'])->assertForbidden();
    });

    test('a KIP number with strange characters is refused', function () {
        $this->actingAs($this->parent)->put(route('portal.children.documents.support', $this->registrant), ['kip_number' => '<script>'])->assertSessionHasErrors('kip_number');
    });
});

describe('the upload page', function () {
    test('it lists the asked files with their slots and the always-offered supporting files', function () {
        $this->withoutVite();
        ($this->upload)();

        $this->actingAs($this->parent)->get(route('portal.children.documents.edit', $this->registrant))->assertInertia(fn ($page) => $page
            ->component('portal/children/documents')
            ->has('requirements', 3)
            ->where('requirements.0.type', 'family-card')
            ->where('requirements.0.required', true)
            ->where('requirements.0.slots.0.document.name', 'kk.pdf')
            ->has('requirements.1.slots', 5)
            ->has('supporting', 5)
            ->where('canEditRequired', true));
    });

    test('another parent gets a 403', function () {
        $this->actingAs(userWithRole(DefaultRole::User))->get(route('portal.children.documents.edit', $this->registrant))->assertForbidden();
    });
});
