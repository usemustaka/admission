<?php

namespace Database\Seeders;

use App\Enums\OrganizationCategory;
use App\Models\Organization;
use App\Models\Setting;
use App\Models\Year;
use Illuminate\Database\Seeder;

class FoundationSeeder extends Seeder
{
    public function run(): void
    {
        Setting::query()->firstOrCreate(['key' => 'foundation.name'], ['value' => 'Yayasan Pendidikan']);

        foreach ([['SMP', 'smp', OrganizationCategory::School], ['SMA', 'sma', OrganizationCategory::School], ['Pesantren', 'pesantren', OrganizationCategory::Pesantren]] as $order => [$name, $code, $category]) {
            Organization::query()->firstOrCreate(
                ['code' => $code],
                ['name' => $name, 'category' => $category, 'sort_order' => $order + 1],
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
