<?php

use App\Enums\DefaultRole;
use App\Enums\DocumentType;
use App\Enums\RegistrantStatus;
use App\Http\Controllers\Admin\Admission\RegistrantDocumentArchiveController;
use App\Models\Registrant;
use App\Models\RegistrantDocument;
use Illuminate\Support\Facades\Storage;

function archiveEntries($response): array
{
    $zip = new ZipArchive;
    $zip->open($response->baseResponse->getFile()->getPathname());
    $names = array_map(fn (int $index): string => $zip->getNameIndex($index), range(0, $zip->numFiles - 1));
    $zip->close();

    sort($names);

    return $names;
}

function registrantWithFile(string $name, DocumentType $type = DocumentType::FamilyCard, int $slot = 1): Registrant
{
    $registrant = admissionRegistrant(RegistrantStatus::Review);
    $registrant->person->update(['name' => $name]);
    $path = "registrant-documents/{$registrant->id}/{$type->value}-{$slot}.pdf";
    Storage::disk(RegistrantDocument::DISK)->put($path, "%PDF {$name}");
    RegistrantDocument::factory()->create(['registrant_id' => $registrant->id, 'type' => $type, 'slot' => $slot, 'path' => $path]);

    return $registrant;
}

beforeEach(function () {
    Storage::fake(RegistrantDocument::DISK);
    $this->actingAs(userWithRole(DefaultRole::Admin));
});

test('one registration downloads as a ZIP with its files named by type', function () {
    $registrant = registrantWithFile('Budi Santoso');
    $path = "registrant-documents/{$registrant->id}/report-2.pdf";
    Storage::disk(RegistrantDocument::DISK)->put($path, '%PDF');
    RegistrantDocument::factory()->create(['registrant_id' => $registrant->id, 'type' => DocumentType::ReportCard, 'slot' => 2, 'path' => $path]);

    $response = $this->get(route('admin.registrants.documents.zip', $registrant))->assertOk()->assertDownload('berkas-budi-santoso.zip');

    expect(archiveEntries($response))->toBe([
        "budi-santoso-{$registrant->id}/kartu-keluarga-kk.pdf",
        "budi-santoso-{$registrant->id}/rapor-semester-1-5-2.pdf",
    ]);
});

test('a registration without files has nothing to download', function () {
    $this->get(route('admin.registrants.documents.zip', admissionRegistrant()))->assertRedirect();
});

test('the matching registrations download together with a folder per child', function () {
    $first = registrantWithFile('Ani');
    $second = registrantWithFile('Budi');
    admissionRegistrant();

    $response = $this->get(route('admin.registrants.documents.archive'))->assertOk();

    expect(archiveEntries($response))->toBe(["ani-{$first->id}/kartu-keluarga-kk.pdf", "budi-{$second->id}/kartu-keluarga-kk.pdf"]);
});

test('picked registrations are the only ones packed', function () {
    $first = registrantWithFile('Ani');
    registrantWithFile('Budi');

    $response = $this->get(route('admin.registrants.documents.archive', ['selected_only' => 1, 'ids' => [$first->id]]))->assertOk();

    expect(archiveEntries($response))->toBe(["ani-{$first->id}/kartu-keluarga-kk.pdf"]);
});

test('too many registrations are refused with a hint to narrow the filter', function () {
    registrantWithFile('Ani');
    RegistrantDocument::factory()->count(RegistrantDocumentArchiveController::MAX_REGISTRANTS)->create(['type' => DocumentType::Kip]);

    $this->get(route('admin.registrants.documents.archive'))->assertRedirect();
});

test('exporting needs the export ability and a single ZIP needs to see registrants', function () {
    $registrant = registrantWithFile('Ani');

    $this->actingAs(registrantViewer())->get(route('admin.registrants.documents.archive'))->assertForbidden();
    $this->actingAs(registrantViewer())->get(route('admin.registrants.documents.zip', $registrant))->assertOk();
    $this->actingAs(userWithRole(DefaultRole::User))->get(route('admin.registrants.documents.zip', $registrant))->assertForbidden();
});
