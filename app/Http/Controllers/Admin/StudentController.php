<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Admission\CancelPlacement;
use App\Actions\Admission\CorrectStudent;
use App\Enums\Ability;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\CancelPlacementRequest;
use App\Http\Requests\Admin\UpdateStudentRequest;
use App\Models\Organization;
use App\Models\Student;
use App\Models\Year;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class StudentController extends Controller
{
    private const SORTS = ['nama', 'nis', 'ditempatkan'];

    public function index(Request $request): Response
    {
        Gate::authorize(Ability::StudentView->value);

        $user = $request->user();
        $term = trim($request->string('cari')->toString());
        $organizationId = $request->integer('lembaga') ?: null;
        $yearId = $request->integer('tahun') ?: null;
        $sort = in_array($request->string('urut')->toString(), self::SORTS, true) ? $request->string('urut')->toString() : 'nama';
        $direction = match ($request->string('arah')->toString()) {
            'asc' => 'asc',
            'desc' => 'desc',
            default => $sort === 'ditempatkan' ? 'desc' : 'asc',
        };

        $students = Student::query()
            ->select('students.*')
            ->join('people', 'people.id', '=', 'students.person_id')
            ->with(['person', 'organization', 'year'])
            ->when($term !== '', function (Builder $query) use ($term): void {
                $escaped = str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $term);

                $query->where(function (Builder $query) use ($escaped): void {
                    $query->whereRaw("people.name like ? escape '!'", ["%{$escaped}%"])
                        ->orWhereRaw("students.nis like ? escape '!'", ["%{$escaped}%"])
                        ->orWhereRaw("people.nik like ? escape '!'", ["{$escaped}%"]);
                });
            })
            ->when($organizationId, fn (Builder $query) => $query->where('students.organization_id', $organizationId))
            ->when($yearId, fn (Builder $query) => $query->where('students.year_id', $yearId));

        match ($sort) {
            'nis' => $students->orderBy('students.nis', $direction),
            'ditempatkan' => $students->orderBy('students.created_at', $direction),
            default => $students->orderBy('people.name', $direction),
        };

        $students->orderBy('students.id');

        return Inertia::render('admin/students/index', [
            'students' => $students->paginate(20)->withQueryString()->through(fn (Student $student): array => [
                'id' => $student->id,
                'person_id' => $student->person_id,
                'registrant_id' => $student->registrant_id,
                'name' => $student->person->name,
                'nik' => $student->person->nik,
                'photo_url' => $student->photo_path ? Storage::disk('public')->url($student->photo_path) : $student->person->photo_url,
                'nis' => $student->nis,
                'organization' => $student->organization->name,
                'year' => $student->year?->name,
                'year_id' => $student->year_id,
                'placed_at' => $student->created_at?->toDateString(),
            ]),
            'filters' => [
                'cari' => $term,
                'lembaga' => $organizationId === null ? '' : (string) $organizationId,
                'tahun' => $yearId === null ? '' : (string) $yearId,
                'urut' => $sort,
                'arah' => $direction,
            ],
            'options' => [
                'organizations' => Organization::query()->orderBy('sort_order')->orderBy('name')->get()->map(fn (Organization $organization): array => ['value' => $organization->id, 'label' => $organization->name])->values(),
                'years' => Year::query()->orderByDesc('starts_on')->get()->map(fn (Year $year): array => ['value' => $year->id, 'label' => $year->name, 'is_active' => $year->is_active])->values(),
            ],
            'can' => [
                'manage' => $user->can(Ability::StudentManage->value),
                'viewPerson' => $user->can(Ability::PersonView->value),
                'viewRegistrant' => $user->can(Ability::RegistrantView->value),
            ],
        ]);
    }

    public function update(UpdateStudentRequest $request, Student $student, CorrectStudent $correctStudent): RedirectResponse
    {
        $changed = $correctStudent->handle(
            $student,
            $request->user(),
            $request->validated('nis'),
            Year::query()->findOrFail((int) $request->validated('year_id')),
        );

        Inertia::flash('toast', $changed
            ? ['type' => 'success', 'message' => 'Data siswa sudah dikoreksi dan dicatat di catatan anak.']
            : ['type' => 'info', 'message' => 'Tidak ada yang berubah.']);

        return back();
    }

    public function cancelPlacement(CancelPlacementRequest $request, Student $student, CancelPlacement $cancelPlacement): RedirectResponse
    {
        $cancelPlacement->handle($student, $request->user(), $request->validated('reason'));

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Penempatan dibatalkan. Pendaftar kembali berstatus sudah diumumkan.']);

        return back();
    }
}
