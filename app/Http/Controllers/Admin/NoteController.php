<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Notes\NoteData;
use App\Actions\Notes\SaveNote;
use App\Contracts\HasNoteSubject;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreNoteRequest;
use App\Http\Requests\Admin\UpdateNoteRequest;
use App\Models\Note;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class NoteController extends Controller
{
    public function store(StoreNoteRequest $request, SaveNote $saveNote): RedirectResponse
    {
        $noteable = Relation::getMorphedModel($request->string('noteable_type')->toString())::findOrFail($request->integer('noteable_id'));

        abort_unless($noteable instanceof HasNoteSubject, 422);

        $this->ensureFamilyShareIsPossible($request->boolean('shared_with_family'), $noteable->notePersonId());

        $saveNote->handle(NoteData::fromRequest($request), author: $request->user(), noteable: $noteable);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Catatan sudah disimpan.']);

        return back();
    }

    public function update(UpdateNoteRequest $request, Note $note, SaveNote $saveNote): RedirectResponse
    {
        $this->ensureFamilyShareIsPossible($request->boolean('shared_with_family'), $note->person_id);

        $saveNote->handle(NoteData::fromRequest($request), $note);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Catatan sudah diperbarui.']);

        return back();
    }

    public function destroy(Note $note): RedirectResponse
    {
        Gate::authorize('delete', $note);

        $note->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Catatan sudah dihapus.']);

        return back();
    }

    private function ensureFamilyShareIsPossible(bool $sharedWithFamily, ?int $personId): void
    {
        if ($sharedWithFamily && $personId === null) {
            throw ValidationException::withMessages([
                'shared_with_family' => 'Catatan ini tidak terkait dengan anak, jadi tidak bisa dibagikan ke keluarga.',
            ]);
        }
    }
}
