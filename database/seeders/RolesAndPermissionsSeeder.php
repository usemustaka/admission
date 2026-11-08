<?php

namespace Database\Seeders;

use App\Enums\Ability;
use App\Enums\DefaultRole;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/**
 * Creates the permissions and the default roles. Safe to run again: it never removes or overwrites what an
 * admin changed on the roles screen. A default role only receives its default abilities when the role is first
 * created, or when an ability is new, so upgrades reach existing roles without undoing customisations.
 */
class RolesAndPermissionsSeeder extends Seeder
{
    /**
     * Default abilities of the committee operator. Bypass, template editing, settings, accounts, roles, years,
     * organizations, payment voiding and student corrections stay with admins.
     *
     * @return list<Ability>
     */
    public static function operatorAbilities(): array
    {
        return [
            Ability::AdminAccess,
            Ability::PersonView,
            Ability::PersonCreate,
            Ability::PersonUpdate,
            Ability::FamilyManage,
            Ability::NoteView,
            Ability::NoteCreate,
            Ability::WaveView,
            Ability::RulesetView,
            Ability::RegistrantView,
            Ability::RegistrantManage,
            Ability::RegistrantExport,
            Ability::RegistrantBulk,
            Ability::RegistrantPlace,
            Ability::RegistrantAnnounce,
            Ability::ExamScore,
            Ability::ParentPasswordReset,
            Ability::StudentView,
            Ability::PaymentView,
            Ability::PaymentRecord,
            Ability::PaymentVerify,
            Ability::PaymentExport,
        ];
    }

    /**
     * Default abilities of the test committee: sees registrants, enters scores, announces results.
     *
     * @return list<Ability>
     */
    public static function testCommitteeAbilities(): array
    {
        return [
            Ability::AdminAccess,
            Ability::PersonView,
            Ability::NoteView,
            Ability::NoteCreate,
            Ability::WaveView,
            Ability::RulesetView,
            Ability::RegistrantView,
            Ability::RegistrantBulk,
            Ability::RegistrantExport,
            Ability::RegistrantAnnounce,
            Ability::ExamScore,
        ];
    }

    /**
     * Default abilities of the finance committee: sees registrants and payments, verifies and records payments,
     * and places announced registrants as students.
     *
     * @return list<Ability>
     */
    public static function financeCommitteeAbilities(): array
    {
        return [
            Ability::AdminAccess,
            Ability::PersonView,
            Ability::NoteView,
            Ability::NoteCreate,
            Ability::RegistrantView,
            Ability::RegistrantPlace,
            Ability::StudentView,
            Ability::PaymentView,
            Ability::PaymentRecord,
            Ability::PaymentVerify,
            Ability::PaymentExport,
        ];
    }

    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $newAbilities = [];

        foreach (Ability::cases() as $ability) {
            $permission = Permission::findOrCreate($ability->value, 'web');

            if ($permission->wasRecentlyCreated) {
                $newAbilities[] = $ability->value;
            }
        }

        Role::findOrCreate(DefaultRole::SuperAdmin->value, 'web');
        Role::findOrCreate(DefaultRole::User->value, 'web');
        Role::findOrCreate(DefaultRole::Inactive->value, 'web');

        $this->seedRole(DefaultRole::Admin, array_column(Ability::cases(), 'value'), $newAbilities);
        $this->seedRole(DefaultRole::Operator, array_map(fn (Ability $ability): string => $ability->value, self::operatorAbilities()), $newAbilities);
        $this->seedRole(DefaultRole::TestCommittee, array_map(fn (Ability $ability): string => $ability->value, self::testCommitteeAbilities()), $newAbilities);
        $this->seedRole(DefaultRole::FinanceCommittee, array_map(fn (Ability $ability): string => $ability->value, self::financeCommitteeAbilities()), $newAbilities);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    /**
     * @param  list<string>  $defaults
     * @param  list<string>  $newAbilities
     */
    private function seedRole(DefaultRole $default, array $defaults, array $newAbilities): void
    {
        $role = Role::findOrCreate($default->value, 'web');

        $grant = $role->wasRecentlyCreated ? $defaults : array_values(array_intersect($defaults, $newAbilities));

        if ($grant !== []) {
            $role->givePermissionTo($grant);
        }
    }
}
