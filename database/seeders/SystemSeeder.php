<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Everything the system needs to run: roles, permissions, and base configuration. Safe to run again.
 */
class SystemSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            RolesAndPermissionsSeeder::class,
            FoundationSeeder::class,
        ]);
    }
}
