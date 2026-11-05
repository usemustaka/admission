<?php

namespace Database\Seeders;

use App\Enums\Ability;
use App\Enums\DefaultRole;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (Ability::cases() as $ability) {
            Permission::findOrCreate($ability->value, 'web');
        }

        Role::findOrCreate(DefaultRole::SuperAdmin->value, 'web');
        Role::findOrCreate(DefaultRole::User->value, 'web');

        Role::findOrCreate(DefaultRole::Admin->value, 'web')
            ->syncPermissions(array_column(Ability::cases(), 'value'));

        Role::findOrCreate(DefaultRole::Operator->value, 'web')
            ->syncPermissions([
                Ability::AdminAccess->value,
                Ability::PersonView->value,
                Ability::PersonCreate->value,
                Ability::PersonUpdate->value,
                Ability::FamilyManage->value,
                Ability::NoteView->value,
                Ability::NoteCreate->value,
                Ability::WaveView->value,
                Ability::RulesetView->value,
                Ability::RegistrantView->value,
                Ability::RegistrantManage->value,
                Ability::PaymentView->value,
                Ability::PaymentVerify->value,
            ]);
    }
}
