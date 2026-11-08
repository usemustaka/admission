<?php

namespace App\Http\Controllers;

use App\Models\RegistrantDocument;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class RegistrantDocumentController extends Controller
{
    /**
     * An uploaded file, shown in the browser. Open to the committee and to the parent of the child, never to anyone else.
     */
    public function __invoke(RegistrantDocument $document): StreamedResponse
    {
        Gate::authorize('viewDocument', $document->registrant);

        $disk = Storage::disk(RegistrantDocument::DISK);

        abort_unless($disk->exists($document->path), 404);

        return $disk->response($document->path, $document->original_name, [
            'Content-Type' => $document->mime_type,
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
