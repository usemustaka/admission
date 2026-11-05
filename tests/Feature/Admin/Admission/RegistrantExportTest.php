<?php

use App\Enums\DefaultRole;
use App\Enums\ParentRelation;
use App\Enums\RegistrantStatus;
use App\Models\Organization;
use App\Models\Parentship;
use App\Models\Person;
use App\Models\Registrant;
use App\Models\User;
use Carbon\CarbonImmutable;

/**
 * @return list<list<string|null>>
 */
function csvRows(string $content): array
{
    $content = ltrim($content, "\xEF\xBB\xBF");

    return array_map(fn (string $line): array => str_getcsv($line, escape: ''), array_values(array_filter(explode("\n", $content))));
}

test('only staff who can see applicants can export', function () {
    $this->actingAs(User::factory()->create())->get(route('admin.registrants.export'))->assertForbidden();
});

test('the export is a UTF-8 CSV with a BOM so Excel reads Indonesian text', function () {
    $this->actingAs(userWithRole(DefaultRole::Operator));
    Registrant::factory()->create(['person_id' => Person::factory()->create(['name' => 'Zahra Ananda Putri'])->id]);

    $response = $this->get(route('admin.registrants.export'))->assertOk();
    $content = $response->streamedContent();

    expect($response->headers->get('content-type'))->toContain('text/csv')
        ->and($response->headers->get('content-disposition'))->toContain('pendaftar-')
        ->and(str_starts_with($content, "\xEF\xBB\xBF"))->toBeTrue()
        ->and(csvRows($content)[0][0])->toBe('Nama')
        ->and(csvRows($content)[1][0])->toBe('Zahra Ananda Putri');
});

test('the export follows the list filters and covers every page', function () {
    $this->actingAs(userWithRole(DefaultRole::Operator));
    $school = Organization::factory()->create();
    Registrant::factory()->count(30)->create(['organization_id' => $school->id, 'status' => RegistrantStatus::Review]);
    Registrant::factory()->count(2)->create(['status' => RegistrantStatus::Review]);
    Registrant::factory()->create(['organization_id' => $school->id, 'status' => RegistrantStatus::Draft]);

    $rows = csvRows($this->get(route('admin.registrants.export', ['lembaga' => $school->id, 'status' => 'review']))->streamedContent());

    expect($rows)->toHaveCount(31)
        ->and(collect(array_slice($rows, 1))->pluck(2)->unique()->all())->toBe([$school->name])
        ->and(collect(array_slice($rows, 1))->pluck(4)->unique()->all())->toBe(['Sedang diperiksa']);
});

test('the export keeps NIK and phone numbers as text and neutralizes formulas in names', function () {
    $this->actingAs(userWithRole(DefaultRole::Operator));
    $registrant = Registrant::factory()->create(['person_id' => Person::factory()->create(['name' => '=HYPERLINK("x")', 'nik' => '3201234567890123'])->id]);
    Parentship::factory()->primaryContact()->create([
        'person_id' => $registrant->person_id,
        'related_person_id' => Person::factory()->create(['name' => 'Bu Ani', 'phone' => '081234567890'])->id,
    ]);

    $row = csvRows($this->get(route('admin.registrants.export'))->streamedContent())[1];

    expect($row[0])->toBe("'=HYPERLINK(\"x\")")
        ->and($row[1])->toBe('="3201234567890123"')
        ->and($row[11])->toBe('Bu Ani')
        ->and($row[12])->toBe('="081234567890"');
});

test('staff who can see applicants but not export them get a 403', function () {
    $this->actingAs(registrantViewer());
    $registrant = Registrant::factory()->create();

    $this->get(route('admin.registrants.export'))->assertForbidden();
    $this->get(route('admin.registrants.export', ['selected_only' => 1, 'ids' => [$registrant->id]]))->assertForbidden();
});

test('the export can be limited to the registrants picked in the list', function () {
    $this->actingAs(userWithRole(DefaultRole::Operator));
    $picked = Registrant::factory()->count(2)->create();
    Registrant::factory()->count(3)->create();

    $rows = csvRows($this->get(route('admin.registrants.export', ['selected_only' => 1, 'ids' => $picked->pluck('id')->all()]))->streamedContent());

    expect($rows)->toHaveCount(3)
        ->and(collect(array_slice($rows, 1))->pluck(0)->sort()->values()->all())->toBe($picked->map(fn (Registrant $registrant) => $registrant->person->name)->sort()->values()->all());
});

test('exporting the selection needs a selection', function () {
    $this->actingAs(userWithRole(DefaultRole::Operator));

    $this->get(route('admin.registrants.export', ['selected_only' => 1]))->assertSessionHasErrors('ids');
    $this->get(route('admin.registrants.export', ['selected_only' => 1, 'ids' => range(1, 201)]))->assertSessionHasErrors('ids');
});

test('the export keeps its first columns and adds the details of the child, the parents and the scores after them', function () {
    $this->actingAs(userWithRole(DefaultRole::Operator));
    $registrant = admissionRegistrant(RegistrantStatus::Review, [
        'submitted_name' => 'Zahra A.',
        'exam_scores' => [88, 64],
        'submitted_at' => '2026-11-03 07:15:00',
    ]);
    $registrant->person->update(['name' => 'Zahra Ananda', 'birth_place' => 'Bandung', 'birth_date' => '2018-02-09', 'address' => 'Jl. Melati 3', 'nisn' => '0012345678']);
    $registrant->person->parentships()->delete();
    Parentship::factory()->primaryContact()->create(['person_id' => $registrant->person_id, 'relation' => ParentRelation::Father, 'related_person_id' => Person::factory()->create(['name' => 'Pak Ahmad', 'phone' => '081200001111'])->id]);
    Parentship::factory()->create(['person_id' => $registrant->person_id, 'relation' => ParentRelation::Mother, 'related_person_id' => Person::factory()->create(['name' => 'Bu Rani', 'phone' => '081200002222'])->id]);

    $rows = csvRows($this->get(route('admin.registrants.export'))->streamedContent());
    $row = array_combine($rows[0], $rows[1]);

    expect(array_slice($rows[0], 0, 14))->toBe(['Nama', 'NIK', 'Lembaga', 'Gelombang', 'Status', 'Perlu cek NIK', 'Pembayaran', 'Total tagihan', 'Sudah dibayar', 'Jadwal ujian', 'Hasil ujian', 'Nama kontak', 'Nomor HP kontak', 'Tanggal daftar'])
        ->and($row['Nama diketik orang tua'])->toBe('Zahra A.')
        ->and($row['Tempat lahir'])->toBe('Bandung')
        ->and($row['Tanggal lahir'])->toBe('2018-02-09')
        ->and($row['NISN'])->toBe('="0012345678"')
        ->and($row['Alamat'])->toBe('Jl. Melati 3')
        ->and($row['Nama ayah'])->toBe('Pak Ahmad')
        ->and($row['HP ayah'])->toBe('="081200001111"')
        ->and($row['Nama ibu'])->toBe('Bu Rani')
        ->and($row['HP ibu'])->toBe('="081200002222"')
        ->and($row['Nama wali'])->toBe('')
        ->and($row['HP wali'])->toBe('')
        ->and($row['Tanggal kirim'])->toBe('2026-11-03 07:15')
        ->and($row['Nilai Tes tulis'])->toBe('88')
        ->and($row['Nilai Wawancara'])->toBe('64')
        ->and($row['Hasil ujian'])->toBe('Tuntas');
});

test('exports that mix rulesets get one score column per aspect, and a registrant without an aspect leaves it empty', function () {
    $this->actingAs(userWithRole(DefaultRole::Operator));
    $standard = admissionRegistrant(RegistrantStatus::Review, ['exam_scores' => [90, 80]]);
    $other = Registrant::factory()->create([
        'status' => RegistrantStatus::Review,
        'ruleset_snapshot' => ['ruleset_id' => 99, 'name' => 'Lain', 'bills' => [], 'exam_aspects' => [['name' => 'Wawancara', 'passing_score' => 50], ['name' => 'Baca Quran', 'passing_score' => 60]]],
        'exam_scores' => [55, null],
    ]);
    $without = Registrant::factory()->create(['status' => RegistrantStatus::Review]);

    $rows = csvRows($this->get(route('admin.registrants.export'))->streamedContent());
    $header = $rows[0];
    $byId = collect(array_slice($rows, 1))->mapWithKeys(fn (array $row): array => [$row[0] => array_combine($header, $row)]);

    expect(array_slice($header, 26))->toBe(['Nilai Tes tulis', 'Nilai Wawancara', 'Nilai Baca Quran'])
        ->and(array_slice($byId[$standard->person->name], 26))->toBe(['Nilai Tes tulis' => '90', 'Nilai Wawancara' => '80', 'Nilai Baca Quran' => ''])
        ->and(array_slice($byId[$other->person->name], 26))->toBe(['Nilai Tes tulis' => '', 'Nilai Wawancara' => '55', 'Nilai Baca Quran' => ''])
        ->and(array_slice($byId[$without->person->name], 26))->toBe(['Nilai Tes tulis' => '', 'Nilai Wawancara' => '', 'Nilai Baca Quran' => '']);
});

test('every text cell is neutralized against formulas while NIK and phone numbers stay text with their zeros', function () {
    $this->actingAs(userWithRole(DefaultRole::Operator));
    $registrant = Registrant::factory()->create([
        'submitted_name' => '+cmd|calc',
        'person_id' => Person::factory()->create(['name' => '@SUM(1)', 'nik' => '=1+1', 'nisn' => '-5x', 'birth_place' => '=Bandung', 'address' => "\tJl. Tab"])->id,
        'organization_id' => Organization::factory()->create(['name' => '-Sekolah'])->id,
    ]);
    Parentship::factory()->primaryContact()->create(['person_id' => $registrant->person_id, 'relation' => ParentRelation::Mother, 'related_person_id' => Person::factory()->create(['name' => '=Ibu', 'phone' => '0812-3456 7890'])->id]);
    Parentship::factory()->create(['person_id' => $registrant->person_id, 'relation' => ParentRelation::Guardian, 'related_person_id' => Person::factory()->create(['name' => '+Wali', 'phone' => '=cmd'])->id]);

    $rows = csvRows($this->get(route('admin.registrants.export'))->streamedContent());
    $row = array_combine($rows[0], $rows[1]);

    expect($row['Nama'])->toBe("'@SUM(1)")
        ->and($row['NIK'])->toBe("'=1+1")
        ->and($row['NISN'])->toBe("'-5x")
        ->and($row['Lembaga'])->toBe("'-Sekolah")
        ->and($row['Nama diketik orang tua'])->toBe("'+cmd|calc")
        ->and($row['Tempat lahir'])->toBe("'=Bandung")
        ->and($row['Alamat'])->toBe("'\tJl. Tab")
        ->and($row['Nama ibu'])->toBe("'=Ibu")
        ->and($row['HP ibu'])->toBe('="081234567890"')
        ->and($row['Nama wali'])->toBe("'+Wali")
        ->and($row['HP wali'])->toBe("'=cmd")
        ->and($row['Nama kontak'])->toBe("'=Ibu")
        ->and($row['Nomor HP kontak'])->toBe('="081234567890"');
});

test('the file name says what was exported and when, in ASCII and in WIB', function () {
    $this->actingAs(userWithRole(DefaultRole::Operator));
    $this->travelTo(CarbonImmutable::parse('2026-11-05 23:30:00', 'UTC'));
    $school = Organization::factory()->create();
    $registrant = Registrant::factory()->create(['organization_id' => $school->id, 'status' => RegistrantStatus::Review]);

    expect($this->get(route('admin.registrants.export'))->headers->get('content-disposition'))->toContain('filename=pendaftar-2026-11-06.csv')
        ->and($this->get(route('admin.registrants.export', ['status' => 'review', 'lembaga' => $school->id, 'bayar' => 'menunggu', 'cari' => 'Ünïcode Ané! 100%']))->headers->get('content-disposition'))
        ->toContain("filename=pendaftar-review-lembaga-{$school->id}-bayar-menunggu-cari-unicode-ane-100-2026-11-06.csv")
        ->and($this->get(route('admin.registrants.export', ['selected_only' => 1, 'ids' => [$registrant->id]]))->headers->get('content-disposition'))->toContain('filename=pendaftar-terpilih-1-2026-11-06.csv');
});
