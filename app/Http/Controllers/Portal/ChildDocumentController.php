<?php

namespace App\Http\Controllers\Portal;

use App\Actions\Documents\DeleteRegistrantDocument;
use App\Actions\Documents\SaveRegistrantDocument;
use App\Enums\DocumentType;
use App\Enums\RegistrantStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Portal\StoreRegistrantDocumentRequest;
use App\Models\Registrant;
use App\Models\RegistrantDocument;
use App\Support\DocumentKind;
use App\Support\RegistrantDocuments;
use App\Support\RegistrantPresenter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class ChildDocumentController extends Controller
{
    public function edit(Registrant $registrant): Response|RedirectResponse
    {
        Gate::authorize('view', $registrant);

        if ($registrant->needs_identity_review) {
            Inertia::flash('toast', ['type' => 'info', 'message' => 'Data anak ini sedang dicocokkan panitia dulu, jadi berkas belum bisa diunggah.']);

            return to_route('portal.children.show', $registrant);
        }

        return Inertia::render('portal/children/documents', [
            'registrant' => RegistrantPresenter::summary($registrant),
            ...RegistrantDocuments::overview($registrant),
            'support' => ['kip_number' => $registrant->kip_number, 'note' => $registrant->support_note],
            'canEditRequired' => $registrant->status->isEditableByParent(),
            'canEditSupporting' => $registrant->status !== RegistrantStatus::Placed,
            'maxKilobytes' => RegistrantDocument::MAX_KILOBYTES,
        ]);
    }

    public function store(StoreRegistrantDocumentRequest $request, Registrant $registrant, SaveRegistrantDocument $saveRegistrantDocument): RedirectResponse
    {
        $kind = DocumentKind::resolve($registrant, $request->string('type')->toString());

        if ($kind === null || ! RegistrantDocuments::accepts($registrant, $kind)) {
            return back()->withErrors(['type' => 'Berkas ini tidak diminta untuk pendaftaran ini.']);
        }

        $saveRegistrantDocument->handle(
            $registrant,
            $kind,
            $request->file('file'),
            $request->filled('slot') ? $request->integer('slot') : null,
            $request->user(),
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Berkas sudah diunggah.']);

        return back();
    }

    public function destroy(Registrant $registrant, RegistrantDocument $document, DeleteRegistrantDocument $deleteRegistrantDocument): RedirectResponse
    {
        abort_unless($document->registrant_id === $registrant->id, 404);
        Gate::authorize('manageDocument', [$registrant, DocumentKind::resolve($registrant, $document->type) ?? DocumentKind::builtin(DocumentType::OtherSupport)]);

        $deleteRegistrantDocument->handle($document);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Berkas sudah dihapus.']);

        return back();
    }

    /**
     * The number of the Kartu Indonesia Pintar and a free note about anything else that supports the registration.
     */
    public function support(Request $request, Registrant $registrant): RedirectResponse
    {
        Gate::authorize('manageDocument', [$registrant, DocumentKind::builtin(DocumentType::OtherSupport)]);

        $validated = $request->validate([
            'kip_number' => ['nullable', 'string', 'max:30', 'regex:/^[0-9A-Za-z\- ]+$/'],
            'support_note' => ['nullable', 'string', 'max:1000'],
        ], ['kip_number.regex' => 'Nomor KIP hanya boleh berisi angka, huruf, dan tanda hubung.'], ['kip_number' => 'Nomor KIP', 'support_note' => 'Keterangan']);

        $registrant->update([
            'kip_number' => filled($validated['kip_number'] ?? null) ? trim($validated['kip_number']) : null,
            'support_note' => filled($validated['support_note'] ?? null) ? trim($validated['support_note']) : null,
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Keterangan sudah disimpan.']);

        return back();
    }
}
