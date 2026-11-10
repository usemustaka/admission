<?php

namespace App\Http\Controllers\Admin\Admission;

use App\Actions\Admission\FindShadowedRulesets;
use App\Actions\Admission\ResolveRuleset;
use App\Actions\Admission\SaveRuleset;
use App\Contracts\HasLabel;
use App\Enums\Ability;
use App\Enums\BillType;
use App\Enums\DocumentType;
use App\Enums\Gender;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Admission\SaveRulesetRequest;
use App\Models\Organization;
use App\Models\Ruleset;
use App\Models\RulesetBillItem;
use App\Models\Wave;
use App\Support\RegistrationFields;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class RulesetController extends Controller
{
    public function index(Request $request, ResolveRuleset $resolveRuleset, FindShadowedRulesets $findShadowedRulesets): Response
    {
        Gate::authorize('viewAny', Ruleset::class);

        $matches = collect();

        if ($request->boolean('uji')) {
            $matches = $resolveRuleset->matching(
                $request->integer('gelombang') ?: null,
                $request->integer('lembaga') ?: null,
                $request->enum('gender', Gender::class),
            );
        }

        $match = $matches->first();

        $rulesets = Ruleset::query()
            ->with(['wave', 'organization'])
            ->withCount('registrants')
            ->byPriority()
            ->get();
        $shadowed = $findShadowedRulesets->handle($rulesets);

        return Inertia::render('admin/rulesets/index', [
            'rulesets' => $rulesets->map(fn (Ruleset $ruleset): array => $this->summary($ruleset, $shadowed[$ruleset->id] ?? null)),
            'options' => $this->options(),
            'test' => [
                'ran' => $request->boolean('uji'),
                'match' => $match === null ? null : ['id' => $match->id, 'name' => $match->name],
                'matches' => $matches->map(fn (Ruleset $ruleset): array => [
                    'id' => $ruleset->id,
                    'name' => $ruleset->name,
                    'is_winner' => $ruleset->is($match),
                ])->values(),
                'filters' => [
                    'gelombang' => $request->input('gelombang', ''),
                    'lembaga' => $request->input('lembaga', ''),
                    'gender' => $request->input('gender', ''),
                ],
            ],
            'can' => ['manage' => $request->user()->can(Ability::RulesetManage->value)],
        ]);
    }

    public function create(): Response
    {
        Gate::authorize('create', Ruleset::class);

        return Inertia::render('admin/rulesets/form', [
            'ruleset' => null,
            'options' => $this->options(onlyActiveOrganizations: true),
        ]);
    }

    public function store(SaveRulesetRequest $request, SaveRuleset $saveRuleset): RedirectResponse
    {
        $ruleset = $this->save($request, $saveRuleset);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Aturan sudah dibuat. Sekarang isi tagihan, ujian, dan jadwalnya.']);

        return to_route('admin.rulesets.show', $ruleset);
    }

    public function show(Request $request, Ruleset $ruleset, FindShadowedRulesets $findShadowedRulesets): Response
    {
        Gate::authorize('view', $ruleset);

        $ruleset->load([
            'wave', 'organization', 'bills.items', 'examAspects', 'documents',
        ])->loadCount('registrants');

        $shadowed = $findShadowedRulesets->handle(Ruleset::query()->byPriority()->get());

        return Inertia::render('admin/rulesets/show', [
            'ruleset' => $this->summary($ruleset, $shadowed[$ruleset->id] ?? null),
            'bills' => collect(BillType::cases())->mapWithKeys(fn (BillType $type): array => [
                $type->value => $ruleset->bills->firstWhere('type', $type)?->items->map(fn (RulesetBillItem $item): array => [
                    'id' => $item->id,
                    'name' => $item->name,
                    'amount' => $item->amount,
                ])->values() ?? [],
            ]),
            'aspects' => $ruleset->examAspects->map(fn ($aspect): array => [
                'id' => $aspect->id,
                'name' => $aspect->name,
                'passing_score' => $aspect->passing_score,
            ])->values(),
            'documents' => collect(DocumentType::core())->map(fn (DocumentType $type): array => [
                'type' => $type->value,
                'label' => $type->label(),
                'choice' => match ($ruleset->documents->firstWhere('type', $type)?->is_required) {
                    true => 'required',
                    false => 'optional',
                    null => 'none',
                },
            ])->values(),
            'requiredFields' => [
                'groups' => RegistrationFields::groups(),
                'selected' => $ruleset->required_fields ?? RegistrationFields::BASELINE,
            ],
            'can' => ['update' => $request->user()->can('update', $ruleset), 'delete' => $request->user()->can('delete', $ruleset)],
        ]);
    }

    public function edit(Ruleset $ruleset): Response
    {
        Gate::authorize('update', $ruleset);

        return Inertia::render('admin/rulesets/form', [
            'ruleset' => $this->summary($ruleset),
            'options' => $this->options(onlyActiveOrganizations: true, keepOrganizationId: $ruleset->organization_id),
        ]);
    }

    public function update(SaveRulesetRequest $request, Ruleset $ruleset, SaveRuleset $saveRuleset): RedirectResponse
    {
        $this->save($request, $saveRuleset, $ruleset);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Aturan sudah diperbarui.']);

        return to_route('admin.rulesets.show', $ruleset);
    }

    public function destroy(Ruleset $ruleset): RedirectResponse
    {
        Gate::authorize('delete', $ruleset);

        if ($ruleset->registrants()->exists()) {
            Inertia::flash('toast', ['type' => 'error', 'message' => 'Aturan ini sudah dipakai pendaftar, jadi tidak bisa dihapus. Nonaktifkan saja lewat Ubah syarat supaya tidak dipakai pendaftar baru.']);

            return back();
        }

        $ruleset->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Aturan sudah dihapus.']);

        return to_route('admin.rulesets.index');
    }

    private function save(SaveRulesetRequest $request, SaveRuleset $saveRuleset, ?Ruleset $ruleset = null): Ruleset
    {
        return $saveRuleset->handle(
            $ruleset,
            $request->string('name')->toString(),
            $request->integer('wave_id') ?: null,
            $request->integer('organization_id') ?: null,
            $request->enum('gender', Gender::class),
            $request->boolean('is_active'),
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function summary(Ruleset $ruleset, ?Ruleset $shadowedBy = null): array
    {
        return [
            'id' => $ruleset->id,
            'name' => $ruleset->name,
            'priority' => $ruleset->priority,
            'is_active' => $ruleset->is_active,
            'wave_id' => $ruleset->wave_id,
            'organization_id' => $ruleset->organization_id,
            'gender_value' => $ruleset->gender?->value,
            'wave' => $ruleset->wave?->name,
            'organization' => $ruleset->organization?->name,
            'gender' => $ruleset->gender?->label(),
            'registrants_count' => $ruleset->registrants_count ?? 0,
            'shadowed_by' => $shadowedBy === null ? null : ['id' => $shadowedBy->id, 'name' => $shadowedBy->name],
        ];
    }

    /**
     * The choices of the forms and filters. A form only offers active organizations, plus the one the ruleset
     * already has, so an inactive organization keeps showing on the rulesets that were made for it.
     *
     * @return array<string, array<int, array{value: int|string, label: string}>>
     */
    private function options(bool $onlyActiveOrganizations = false, ?int $keepOrganizationId = null): array
    {
        return [
            'waves' => Wave::query()->orderByDesc('id')->get()->map(fn (Wave $wave): array => ['value' => $wave->id, 'label' => $wave->name])->values()->all(),
            'organizations' => Organization::query()
                ->when($onlyActiveOrganizations, fn ($query) => $query->where(fn ($query) => $query->where('is_active', true)->when($keepOrganizationId, fn ($query) => $query->orWhere('id', $keepOrganizationId))))
                ->orderBy('sort_order')->orderBy('name')->get()->map(fn (Organization $organization): array => ['value' => $organization->id, 'label' => $organization->name])->values()->all(),
            'genders' => array_map(fn (HasLabel $gender): array => ['value' => $gender->value, 'label' => $gender->label()], Gender::cases()),
        ];
    }
}
