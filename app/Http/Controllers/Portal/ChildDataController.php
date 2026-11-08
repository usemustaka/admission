<?php

namespace App\Http\Controllers\Portal;

use App\Actions\Portal\SaveRegistrantData;
use App\Enums\AdmissionType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Portal\SaveRegistrantDataRequest;
use App\Models\Registrant;
use App\Support\RegistrantPresenter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class ChildDataController extends Controller
{
    public function edit(Registrant $registrant): Response|RedirectResponse
    {
        Gate::authorize('view', $registrant);

        if ($registrant->needs_identity_review) {
            Inertia::flash('toast', ['type' => 'info', 'message' => 'Data anak ini sedang dicocokkan panitia dulu, jadi belum bisa dibuka.']);

            return to_route('portal.children.show', $registrant);
        }

        return Inertia::render('portal/children/data', [
            'registrant' => RegistrantPresenter::summary($registrant),
            ...RegistrantPresenter::dataForm($registrant),
            'canEdit' => Gate::allows('update', $registrant),
        ]);
    }

    public function update(SaveRegistrantDataRequest $request, Registrant $registrant, SaveRegistrantData $saveRegistrantData): RedirectResponse
    {
        /** @var array<string, mixed> $child */
        $child = $request->validated('child') ?? [];
        /** @var array<string, array<string, mixed>> $parents */
        $parents = $request->validated('parents') ?? [];

        $saveRegistrantData->handle(
            $registrant,
            $child,
            $parents,
            $request->file('photo'),
            $request->boolean('remove_photo'),
            education: $request->validated('education'),
            admissionType: $request->enum('admission_type', AdmissionType::class),
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Datanya sudah disimpan sebagai draf.']);

        return to_route('portal.children.data.edit', $registrant);
    }
}
