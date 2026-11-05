<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Academic\DeleteOrganization;
use App\Actions\Academic\MoveOrganization;
use App\Enums\Ability;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SaveOrganizationRequest;
use App\Models\Organization;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class OrganizationController extends Controller
{
    public function index(): Response
    {
        Gate::authorize(Ability::OrganizationManage->value);

        return Inertia::render('admin/organizations/index', [
            'organizations' => Organization::query()
                ->withCount(['rulesets', 'registrants', 'students', 'employees'])
                ->orderBy('sort_order')
                ->orderBy('name')
                ->orderBy('id')
                ->get()
                ->map(fn (Organization $organization): array => [
                    'id' => $organization->id,
                    'name' => $organization->name,
                    'code' => $organization->code,
                    'category' => $organization->category,
                    'is_active' => $organization->is_active,
                    'rulesets_count' => $organization->rulesets_count,
                    'registrants_count' => $organization->registrants_count,
                    'students_count' => $organization->students_count,
                    'in_use' => $organization->rulesets_count + $organization->registrants_count + $organization->students_count + $organization->employees_count > 0,
                ])
                ->values(),
        ]);
    }

    public function store(SaveOrganizationRequest $request): RedirectResponse
    {
        Organization::query()->create([
            ...$request->safe()->only(['name', 'code', 'category']),
            'is_active' => $request->boolean('is_active', true),
            'sort_order' => (int) Organization::query()->max('sort_order') + 1,
        ]);

        $this->flash('Lembaga baru sudah dibuat.');

        return to_route('admin.organizations.index');
    }

    public function update(SaveOrganizationRequest $request, Organization $organization): RedirectResponse
    {
        $organization->update([
            ...$request->safe()->only(['name', 'code', 'category']),
            'is_active' => $request->has('is_active') ? $request->boolean('is_active') : $organization->is_active,
        ]);

        $this->flash('Lembaga sudah diperbarui.');

        return to_route('admin.organizations.index');
    }

    public function destroy(Organization $organization, DeleteOrganization $deleteOrganization): RedirectResponse
    {
        Gate::authorize(Ability::OrganizationManage->value);

        $deleteOrganization->handle($organization);

        $this->flash("Lembaga {$organization->name} sudah dihapus.");

        return to_route('admin.organizations.index');
    }

    public function move(Request $request, Organization $organization, MoveOrganization $moveOrganization): RedirectResponse
    {
        Gate::authorize(Ability::OrganizationManage->value);

        $validated = $request->validate(['direction' => ['required', Rule::in(['up', 'down'])]]);

        $moveOrganization->handle($organization, $validated['direction']);

        return back();
    }

    private function flash(string $message): void
    {
        Inertia::flash('toast', ['type' => 'success', 'message' => $message]);
    }
}
