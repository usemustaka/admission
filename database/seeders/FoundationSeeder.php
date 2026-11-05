<?php

namespace Database\Seeders;

use App\Models\Organization;
use App\Models\Setting;
use App\Models\Year;
use Illuminate\Database\Seeder;

class FoundationSeeder extends Seeder
{
    public function run(): void
    {
        Setting::query()->firstOrCreate(['key' => 'foundation.name'], ['value' => 'Yayasan Pendidikan']);

        foreach ([['SMP', 'smp'], ['SMA', 'sma'], ['Pesantren', 'pesantren']] as $order => [$name, $code]) {
            Organization::query()->firstOrCreate(
                ['code' => $code],
                ['name' => $name, 'category' => $code, 'sort_order' => $order + 1],
            );
        }

        $startYear = now()->month >= 7 ? now()->year : now()->year - 1;

        $year = Year::query()->firstOrCreate(
            ['name' => $startYear.'/'.($startYear + 1)],
            ['starts_on' => "{$startYear}-07-01", 'ends_on' => ($startYear + 1).'-06-30', 'is_active' => true],
        );

        $year->periods()->firstOrCreate(
            ['sequence' => 1],
            ['name' => 'Ganjil', 'starts_on' => "{$startYear}-07-01", 'ends_on' => "{$startYear}-12-31", 'is_active' => true],
        );
        $year->periods()->firstOrCreate(
            ['sequence' => 2],
            ['name' => 'Genap', 'starts_on' => ($startYear + 1).'-01-01', 'ends_on' => ($startYear + 1).'-06-30'],
        );
    }
}
