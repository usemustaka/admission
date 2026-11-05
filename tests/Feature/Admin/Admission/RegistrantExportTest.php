<?php

use App\Enums\DefaultRole;
use App\Enums\RegistrantStatus;
use App\Models\Organization;
use App\Models\Parentship;
use App\Models\Person;
use App\Models\Registrant;
use App\Models\User;

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
