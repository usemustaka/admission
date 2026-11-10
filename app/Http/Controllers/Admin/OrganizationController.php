<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Academic\DeleteOrganization;
use App\Actions\Academic\MoveOrganization;
use App\Enums\Ability;
use App\Enums\OrganizationCategory;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SaveOrganizationRequest;
use App\Models\Organization;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
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
                    'category' => $organization->category?->value,
                    'category_label' => $organization->category?->label(),
                    'is_active' => $organization->is_active,
                    'has_brochure' => $organization->brochure_path !== null,
                    'rulesets_count' => $organization->rulesets_count,
                    'registrants_count' => $organization->registrants_count,
                    'students_count' => $organization->students_count,
                    'in_use' => $organization->rulesets_count + $organization->registrants_count + $organization->students_count + $organization->employees_count > 0,
                ])
                ->values(),
            'categories' => OrganizationCategory::options(),
        ]);
    }

    public function store(SaveOrganizationRequest $request): RedirectResponse
    {
        Organization::query()->create([
            ...$request->safe()->only(['name', 'code', 'category']),
            'brochure_path' => $this->storeBrochure($request),
            'is_active' => $request->boolean('is_active', true),
            'sort_order' => (int) Organization::query()->max('sort_order') + 1,
        ]);

        $this->flash('Lembaga baru sudah dibuat.');

        return to_route('admin.organizations.index');
    }

    public function update(SaveOrganizationRequest $request, Organization $organization): RedirectResponse
    {
        $old = $organization->brochure_path;
        $brochure = $this->storeBrochure($request);
        $removed = $brochure === null && $request->boolean('remove_brochure');

        $organization->update([
            ...$request->safe()->only(['name', 'code', 'category']),
            'brochure_path' => $brochure ?? ($removed ? null : $old),
            'is_active' => $request->has('is_active') ? $request->boolean('is_active') : $organization->is_active,
        ]);

        if ($old !== null && ($brochure !== null || $removed)) {
            Storage::disk('public')->delete($old);
        }

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

    /**
     * Keeps an uploaded brochure on the public disk and returns its path, or null when none was sent.
     */
    private function storeBrochure(SaveOrganizationRequest $request): ?string
    {
        $file = $request->file('brochure');

        return $file === null ? null : $file->store('brochures', 'public');
    }

    private function flash(string $message): void
    {
        Inertia::flash('toast', ['type' => 'success', 'message' => $message]);
    }
}
