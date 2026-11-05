<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Users\ChangeUserRoles;
use App\Actions\Users\CreateStaffAccount;
use App\Actions\Users\DeactivateUser;
use App\Actions\Users\ReactivateUser;
use App\Actions\Users\SendPasswordSetupLink;
use App\Enums\Ability;
use App\Enums\DefaultRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreStaffRequest;
use App\Http\Requests\Admin\UpdateUserRolesRequest;
use App\Models\User;
use App\Support\ActiveSuperAdmins;
use App\Support\PasswordChange;
use App\Support\RoleRules;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Role;

class UserController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize(Ability::UserManage->value);

        $term = $request->string('cari')->toString();
        $role = $request->string('peran')->toString();
        $status = in_array($request->string('status')->toString(), ['aktif', 'nonaktif'], true) ? $request->string('status')->toString() : '';

        $matching = fn (string $role, string $status): Builder => $this->staff()
            ->when(trim($term) !== '', fn (Builder $query) => $query->where(function (Builder $query) use ($term): void {
                $like = '%'.addcslashes(trim($term), '%_\\').'%';

                $query->where('users.email', 'like', $like)
                    ->orWhereHas('person', fn (Builder $person) => $person->where('name', 'like', $like));
            }))
            ->when($role !== '', fn (Builder $query) => $query->whereHas('roles', fn (Builder $roles) => $roles->where('name', $role)))
            ->when($status === 'nonaktif', fn (Builder $query) => $query->whereHas('roles', fn (Builder $roles) => $roles->where('name', DefaultRole::Inactive->value)))
            ->when($status === 'aktif', fn (Builder $query) => $query->whereDoesntHave('roles', fn (Builder $roles) => $roles->where('name', DefaultRole::Inactive->value)));

        $roleNames = Role::query()->whereNotIn('name', RoleRules::nonStaffNames())->orderBy('name')->pluck('name');

        return Inertia::render('admin/users/index', [
            'users' => $matching($role, $status)
                ->with(['person', 'roles'])
                ->join('people', 'people.id', '=', 'users.person_id', 'left')
                ->orderByRaw('coalesce(people.name, users.email)')
                ->select('users.*')
                ->paginate(15)
                ->withQueryString()
                ->through(fn (User $user): array => $this->summary($user, $request->user())),
            'filters' => ['cari' => $term, 'peran' => $role, 'status' => $status],
            'options' => ['roles' => $roleNames->values()],
            'counts' => [
                'roles' => [
                    '' => $matching('', $status)->count(),
                    ...$roleNames->mapWithKeys(fn (string $name): array => [$name => $matching($name, $status)->count()])->all(),
                ],
                'status' => [
                    '' => $matching($role, '')->count(),
                    'aktif' => $matching($role, 'aktif')->count(),
                    'nonaktif' => $matching($role, 'nonaktif')->count(),
                ],
            ],
        ]);
    }

    public function create(Request $request): Response
    {
        Gate::authorize(Ability::UserManage->value);

        return Inertia::render('admin/users/form', [
            'roles' => $this->roleOptions($request->user())->where('assignable', true)->values(),
        ]);
    }

    public function store(StoreStaffRequest $request, CreateStaffAccount $createStaffAccount): RedirectResponse
    {
        $user = $createStaffAccount->handle($request->validated('name'), $request->validated('email'), $request->validated('role'));

        Inertia::flash('toast', ['type' => 'success', 'message' => "Undangan dikirim ke {$user->email}. Staf memilih passwordnya sendiri lewat tautan itu."]);

        return to_route('admin.users.show', $user);
    }

    public function show(Request $request, User $user): Response
    {
        Gate::authorize(Ability::UserManage->value);
        $this->ensureStaff($user);

        $actor = $request->user();
        $user->load(['person', 'roles']);

        return Inertia::render('admin/users/show', [
            'staff' => [
                ...$this->summary($user, $actor),
                'person_id' => $user->person_id,
                'password_change_required' => PasswordChange::isRequiredFor($user),
                'is_last_super_admin' => ActiveSuperAdmins::isLast($user),
            ],
            'roles' => $this->roleOptions($actor),
            'assigned' => $user->roles->pluck('name')->diff(RoleRules::nonStaffNames())->sort()->values(),
            'can' => [
                'manage' => $this->canManage($actor, $user),
                'viewPerson' => $user->person_id !== null && $actor->can(Ability::PersonView->value),
            ],
        ]);
    }

    public function update(UpdateUserRolesRequest $request, User $user, ChangeUserRoles $changeUserRoles): RedirectResponse
    {
        $this->ensureStaff($user);

        $changeUserRoles->handle($request->user(), $user, $request->validated('roles'));

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Peran sudah disimpan.']);

        return back();
    }

    public function deactivate(Request $request, User $user, DeactivateUser $deactivateUser): RedirectResponse
    {
        $this->authorizeAccountAction($request, $user);

        if ($request->user()->is($user)) {
            throw ValidationException::withMessages(['user' => 'Kamu tidak bisa menonaktifkan akunmu sendiri.']);
        }

        $deactivateUser->handle($user);

        Inertia::flash('toast', ['type' => 'success', 'message' => "Akun {$user->name} sudah dinonaktifkan."]);

        return back();
    }

    public function reactivate(Request $request, User $user, ReactivateUser $reactivateUser): RedirectResponse
    {
        $this->authorizeAccountAction($request, $user);

        $reactivateUser->handle($user);

        Inertia::flash('toast', ['type' => 'success', 'message' => "Akun {$user->name} aktif lagi."]);

        return back();
    }

    public function requirePasswordChange(Request $request, User $user): RedirectResponse
    {
        $this->authorizeAccountAction($request, $user);

        PasswordChange::require($user);

        Inertia::flash('toast', ['type' => 'success', 'message' => "{$user->name} akan diminta mengganti password saat masuk berikutnya."]);

        return back();
    }

    public function sendSetupLink(Request $request, User $user, SendPasswordSetupLink $sendSetupLink): RedirectResponse
    {
        $this->authorizeAccountAction($request, $user);

        $sendSetupLink->handle($user);

        Inertia::flash('toast', ['type' => 'success', 'message' => "Tautan atur password dikirim ke {$user->email}."]);

        return back();
    }

    /**
     * Accounts with any role besides `user`, including deactivated ones.
     *
     * @return Builder<User>
     */
    private function staff(): Builder
    {
        return User::query()->whereHas('roles', fn (Builder $roles) => $roles->whereNot('name', DefaultRole::User->value));
    }

    private function ensureStaff(User $user): void
    {
        abort_unless($this->staff()->whereKey($user->id)->exists(), 404);
    }

    /**
     * Only super admins may act on super admin accounts.
     */
    private function canManage(User $actor, User $user): bool
    {
        return $actor->hasRole(DefaultRole::SuperAdmin->value) || ! $user->hasRole(DefaultRole::SuperAdmin->value);
    }

    /**
     * @throws ValidationException
     */
    private function authorizeAccountAction(Request $request, User $user): void
    {
        Gate::authorize(Ability::UserManage->value);
        $this->ensureStaff($user);

        if (! $this->canManage($request->user(), $user)) {
            throw ValidationException::withMessages(['user' => 'Hanya super admin yang bisa mengubah akun super admin.']);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function summary(User $user, User $actor): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'photo_url' => $user->avatar,
            'roles' => $user->roles->pluck('name')->diff(RoleRules::nonStaffNames())->sort()->values(),
            'is_active' => ! $user->hasRole(DefaultRole::Inactive->value),
            'has_two_factor' => $user->two_factor_confirmed_at !== null,
            'email_verified' => $user->email_verified_at !== null,
            'is_self' => $actor->is($user),
            'created_at' => $user->created_at?->toDateString(),
        ];
    }

    /**
     * Roles that can be picked for a staff member, with whether the signed-in user may hand each one out.
     *
     * @return Collection<int, array{name: string, abilities_count: int, assignable: bool}>
     */
    private function roleOptions(User $actor): Collection
    {
        $assignable = RoleRules::assignableBy($actor)->pluck('name')->all();

        return Role::query()
            ->with('permissions')
            ->whereNotIn('name', RoleRules::nonStaffNames())
            ->orderBy('name')
            ->get()
            ->map(fn (Role $role): array => [
                'name' => $role->name,
                'abilities_count' => count(RoleRules::abilitiesOf($role)),
                'assignable' => in_array($role->name, $assignable, true),
            ])
            ->values();
    }
}
