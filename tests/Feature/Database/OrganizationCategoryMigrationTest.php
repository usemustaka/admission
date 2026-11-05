<?php

use Illuminate\Support\Facades\DB;

test('free text categories are mapped onto the fixed ones and unknown ones are cleared', function () {
    $ids = [];

    foreach (['SMP' => 'sekolah', 'sma' => 'sekolah', ' SD ' => 'sekolah', 'MTs' => 'madrasah', 'Pondok' => 'pesantren', 'pesantren' => 'pesantren', 'Bootcamp' => 'bootcamp', 'kursus' => 'bootcamp', 'hal lain' => null] as $typed => $expected) {
        $ids[] = [DB::table('organizations')->insertGetId(['name' => $typed, 'code' => 'kode-'.count($ids), 'category' => $typed, 'created_at' => now(), 'updated_at' => now()]), $expected];
    }

    $none = DB::table('organizations')->insertGetId(['name' => 'Kosong', 'code' => 'kosong', 'category' => null, 'created_at' => now(), 'updated_at' => now()]);

    (require database_path('migrations/2026_11_06_000002_use_organization_category_enum.php'))->up();

    foreach ($ids as [$id, $expected]) {
        expect(DB::table('organizations')->where('id', $id)->value('category'))->toBe($expected);
    }

    expect(DB::table('organizations')->where('id', $none)->value('category'))->toBeNull();
});
