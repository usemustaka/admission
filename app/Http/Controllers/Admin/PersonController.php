<?php

namespace App\Http\Controllers\Admin;

use App\Actions\People\SavePerson;
use App\Contracts\HasLabel;
use App\Enums\Ability;
use App\Enums\DefaultRole;
use App\Enums\Gender;
use App\Enums\NoteUrgency;
use App\Enums\NoteVisibility;
use App\Enums\ParentKind;
use App\Enums\ParentRelation;
use App\Enums\PersonKind;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StorePersonRequest;
use App\Http\Requests\Admin\UpdatePersonRequest;
use App\Models\Note;
use App\Models\NoteShare;
use App\Models\Parentship;
use App\Models\Person;
use App\Models\User;
use App\Support\PersonOverview;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\Models\Role;

class PersonController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Person::class);

        $term = $request->string('cari')->toString();
        $kind = $request->enum('jenis', PersonKind::class);
        $quality = in_array($request->string('data')->toString(), ['nik', 'kontak'], true) ? $request->string('data')->toString() : '';
        $sort = $request->string('urut')->toString() === 'terbaru' ? 'terbaru' : 'nama';
        $direction = match ($request->string('arah')->toString()) {
            'asc' => 'asc',
            'desc' => 'desc',
            default => $sort === 'nama' ? 'asc' : 'desc',
        };

        $matching = fn (?PersonKind $kind, string $quality): Builder => Person::query()
            ->search($term)
            ->when($kind, fn (Builder $query, PersonKind $kind) => $query->ofKind($kind))
            ->when($quality === 'nik', fn (Builder $query) => $query->withoutCompleteNik())
            ->when($quality === 'kontak', fn (Builder $query) => $query->withoutContact());

        $people = $matching($kind, $quality)
            ->withExists([
                'registrants as is_applicant',
                'students as is_student',
                'childrenParentships as is_family',
                'employees as is_employee',
                'parentships as has_parent_contact' => fn (Builder $parentships) => $parentships->whereHas('relatedPerson', fn (Builder $parent) => $parent->whereNotNull('phone')->where('phone', '<>', '')),
            ]);

        if ($sort === 'nama') {
            $people->orderBy('name', $direction)->orderBy('id');
        } else {
            $people->orderBy('created_at', $direction)->orderBy('id', $direction);
        }

        return Inertia::render('admin/people/index', [
            'people' => $people
                ->paginate(15)
                ->withQueryString()
                ->through(fn (Person $person): array => [
                    ...$this->summary($person),
                    'roles' => PersonOverview::roles((bool) $person->is_student, (bool) $person->is_applicant, (bool) $person->is_family, (bool) $person->is_employee),
                    'nik_complete' => $person->nik !== null && strlen($person->nik) === 16,
                    'has_contact' => filled($person->phone) || (bool) $person->has_parent_contact,
                    'created_at' => $person->created_at?->toDateString(),
                ]),
            'filters' => [
                'cari' => $term,
                'jenis' => $kind?->value ?? '',
                'data' => $quality,
                'urut' => $sort,
                'arah' => $direction,
            ],
            'options' => ['kinds' => $this->options(PersonKind::cases())],
            'counts' => [
                'kinds' => [
                    'all' => $matching(null, $quality)->count(),
                    ...collect(PersonKind::cases())->mapWithKeys(fn (PersonKind $case): array => [$case->value => $matching($case, $quality)->count()])->all(),
                ],
                'data' => [
                    'nik' => $matching($kind, 'nik')->count(),
                    'kontak' => $matching($kind, 'kontak')->count(),
                ],
            ],
        ]);
    }

    public function create(): Response
    {
        Gate::authorize('create', Person::class);

        return Inertia::render('admin/people/form', [
            'person' => null,
            'genders' => $this->options(Gender::cases()),
        ]);
    }

    public function store(StorePersonRequest $request, SavePerson $savePerson): RedirectResponse
    {
        $nik = $request->validated('nik');
        $existing = $nik ? Person::query()->where('nik', $nik)->first() : null;

        if ($existing !== null) {
            Inertia::flash('toast', ['type' => 'info', 'message' => "NIK ini sudah terdaftar atas nama {$existing->name}, jadi kamu dialihkan ke datanya."]);

            return to_route('admin.people.show', $existing);
        }

        $person = $savePerson->handle(null, Arr::except($request->validated(), ['photo', 'remove_photo']), $request->file('photo'));

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Orang baru sudah ditambahkan.']);

        return to_route('admin.people.show', $person);
    }

    public function show(Request $request, Person $person): Response
    {
        Gate::authorize('view', $person);

        $user = $request->user();

        $person->load(['parentships.relatedPerson', 'childrenParentships.person']);

        $notes = $person->notes()
            ->visibleTo($user)
            ->with([
                'author.person',
                'shares.shareable' => fn (MorphTo $morph) => $morph->morphWith([User::class => ['person']]),
            ])
            ->latest()
            ->get();

        return Inertia::render('admin/people/show', [
            'person' => $this->detail($person),
            'parents' => $person->parentships->map(fn (Parentship $parentship): array => [
                'id' => $parentship->id,
                'person' => $this->summary($parentship->relatedPerson),
                'relation' => $parentship->relation->label(),
                'relation_value' => $parentship->relation->value,
                'kind' => $parentship->kind?->label(),
                'is_primary_contact' => $parentship->is_primary_contact,
            ])->values(),
            'dependents' => $person->childrenParentships->map(fn (Parentship $parentship): array => [
                'id' => $parentship->id,
                'person' => $this->summary($parentship->person),
                'relation' => $parentship->relation->label(),
            ])->values(),
            'notes' => $notes->map(fn (Note $note): array => $this->note($note, $user))->values(),
            'roles' => PersonOverview::rolesOf($person),
            'registrations' => PersonOverview::registrations($person),
            'can' => [
                'update' => $user->can('update', $person),
                'manageFamily' => $user->can(Ability::FamilyManage->value),
                'createNote' => $user->can('create', Note::class),
            ],
            'options' => [
                'relations' => $this->options(ParentRelation::cases()),
                'kinds' => $this->options(ParentKind::cases()),
                'urgencies' => $this->options(NoteUrgency::cases()),
                'visibilities' => $this->options(NoteVisibility::cases()),
                'users' => User::query()
                    ->with('person')
                    ->whereHas('roles', fn ($roles) => $roles->whereIn('name', [DefaultRole::SuperAdmin->value, DefaultRole::Admin->value, DefaultRole::Operator->value]))
                    ->whereKeyNot($user->id)
                    ->get()
                    ->map(fn (User $staff): array => ['value' => $staff->id, 'label' => $staff->name])
                    ->sortBy('label')
                    ->values(),
                'roles' => Role::query()
                    ->where('name', '!=', DefaultRole::User->value)
                    ->orderBy('name')
                    ->get()
                    ->map(fn (Role $role): array => ['value' => $role->id, 'label' => $role->name]),
            ],
        ]);
    }

    public function edit(Person $person): Response
    {
        Gate::authorize('update', $person);

        return Inertia::render('admin/people/form', [
            'person' => $this->detail($person),
            'genders' => $this->options(Gender::cases()),
        ]);
    }

    public function update(UpdatePersonRequest $request, Person $person, SavePerson $savePerson): RedirectResponse
    {
        $savePerson->handle(
            $person,
            Arr::except($request->validated(), ['photo', 'remove_photo']),
            $request->file('photo'),
            $request->boolean('remove_photo'),
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Data sudah disimpan.']);

        return to_route('admin.people.show', $person);
    }

    /**
     * @return array<string, mixed>
     */
    private function summary(Person $person): array
    {
        return [
            'id' => $person->id,
            'name' => $person->name,
            'nik' => $person->nik,
            'gender' => $person->gender?->label(),
            'phone' => $person->phone,
            'photo_url' => $person->photo_url,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function detail(Person $person): array
    {
        return [
            ...$this->summary($person),
            'gender_value' => $person->gender?->value,
            'birth_place' => $person->birth_place,
            'birth_date' => $person->birth_date?->toDateString(),
            'email' => $person->email,
            'is_alive' => $person->is_alive,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function note(Note $note, User $user): array
    {
        return [
            'id' => $note->id,
            'body' => $note->body,
            'urgency' => $note->urgency->value,
            'visibility' => $note->visibility->value,
            'shared_with_family' => $note->shared_with_family,
            'created_at' => $note->created_at?->translatedFormat('j M Y, H:i'),
            'author' => $note->author_id === null
                ? ['name' => 'Sistem', 'photo_url' => null]
                : ['name' => $note->author->name, 'photo_url' => $note->author->avatar],
            'is_mine' => $note->author_id === $user->id,
            'shared_with' => $note->shares->map(fn (NoteShare $share): array => [
                'type' => $share->shareable_type,
                'id' => $share->shareable_id,
                'name' => match (true) {
                    $share->shareable instanceof User, $share->shareable instanceof Role => $share->shareable->name,
                    default => '—',
                },
            ])->values(),
            'user_ids' => $note->shares->where('shareable_type', 'user')->pluck('shareable_id')->values(),
            'role_ids' => $note->shares->where('shareable_type', 'role')->pluck('shareable_id')->values(),
            'can_update' => $user->can('update', $note),
        ];
    }

    /**
     * @param  list<HasLabel>  $cases
     * @return list<array{value: int|string, label: string}>
     */
    private function options(array $cases): array
    {
        return array_map(fn (HasLabel $case): array => ['value' => $case->value, 'label' => $case->label()], $cases);
    }
}
