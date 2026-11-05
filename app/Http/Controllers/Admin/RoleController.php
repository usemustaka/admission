<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Roles\DeleteRole;
use App\Actions\Roles\DuplicateRole;
use App\Actions\Roles\SaveRole;
use App\Enums\Ability;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SaveRoleRequest;
use App\Models\User;
use App\Support\RoleRules;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Role;

class RoleController extends Controller
{
    /**
     * Module prefixes of the abilities, as shown on screen.
     *
     * @var array<string, string>
     */
    private const MODULE_TITLES = ['core' => 'Umum', 'admission' => 'Pendaftaran'];

    public function index(): Response
    {
        Gate::authorize(Ability::RoleManage->value);

        return Inertia::render('admin/roles/index', [
            'roles' => Role::query()
                ->with('permissions')
                ->withCount('users')
                ->orderBy('id')
                ->get()
                ->map(fn (Role $role): array => $this->summary($role))
                ->values(),
        ]);
    }

    public function create(Request $request): Response
    {
        Gate::authorize(Ability::RoleManage->value);

        return Inertia::render('admin/roles/form', [
            'role' => null,
            'groups' => $this->groups($request->user()),
        ]);
    }

    public function store(SaveRoleRequest $request, SaveRole $saveRole): RedirectResponse
    {
        $role = $saveRole->handle($request->user(), null, $request->validated('name'), $request->validated('abilities'));

        Inertia::flash('toast', ['type' => 'success', 'message' => "Peran {$role->name} sudah dibuat."]);

        return to_route('admin.roles.edit', $role);
    }

    public function edit(Request $request, Role $role): Response
    {
        Gate::authorize(Ability::RoleManage->value);

        $role->loadCount('users')->load('permissions');

        return Inertia::render('admin/roles/form', [
            'role' => [
                ...$this->summary($role),
                'abilities' => RoleRules::abilitiesOf($role),
            ],
            'groups' => $this->groups($request->user()),
        ]);
    }

    public function update(SaveRoleRequest $request, Role $role, SaveRole $saveRole): RedirectResponse
    {
        $saveRole->handle($request->user(), $role, $request->validated('name'), $request->validated('abilities'));

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Peran sudah disimpan.']);

        return to_route('admin.roles.edit', $role);
    }

    public function duplicate(Request $request, Role $role, DuplicateRole $duplicateRole): RedirectResponse
    {
        Gate::authorize(Ability::RoleManage->value);

        $copy = $duplicateRole->handle($request->user(), $role);

        Inertia::flash('toast', ['type' => 'success', 'message' => "Peran digandakan menjadi {$copy->name}."]);

        return to_route('admin.roles.edit', $copy);
    }

    public function destroy(Role $role, DeleteRole $deleteRole): RedirectResponse
    {
        Gate::authorize(Ability::RoleManage->value);

        $deleteRole->handle($role);

        Inertia::flash('toast', ['type' => 'success', 'message' => "Peran {$role->name} sudah dihapus."]);

        return to_route('admin.roles.index');
    }

    /**
     * @return array<string, mixed>
     */
    private function summary(Role $role): array
    {
        return [
            'id' => $role->id,
            'name' => $role->name,
            'users_count' => $role->users_count ?? $role->users()->count(),
            'abilities_count' => count(RoleRules::abilitiesOf($role)),
            'is_locked' => RoleRules::isLocked($role),
            'is_built_in' => RoleRules::isBuiltIn($role),
        ];
    }

    /**
     * Every ability, grouped by module, with whether the signed-in user holds it and so may hand it out.
     *
     * @return array<int, array{module: string, title: string, abilities: array<int, array{value: string, label: string, held: bool}>}>
     */
    private function groups(User $actor): array
    {
        return collect(Ability::cases())
            ->groupBy(fn (Ability $ability): string => $ability->module())
            ->map(fn ($abilities, string $module): array => [
                'module' => $module,
                'title' => self::MODULE_TITLES[$module] ?? ucfirst($module),
                'abilities' => $abilities->map(fn (Ability $ability): array => [
                    'value' => $ability->value,
                    'label' => $ability->label(),
                    'held' => $actor->can($ability->value),
                ])->values()->all(),
            ])
            ->values()
            ->all();
    }
}
