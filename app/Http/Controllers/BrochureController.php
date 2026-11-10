<?php

namespace App\Http\Controllers;

use App\Models\Organization;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BrochureController extends Controller
{
    /**
     * The brochure (PDF, JPG or PNG) of an active lembaga, open to everyone because it is shown on the front page.
     */
    public function __invoke(Organization $organization): StreamedResponse
    {
        abort_unless($organization->is_active && $organization->brochure_path !== null, 404);
        abort_unless(Storage::disk('public')->exists($organization->brochure_path), 404);

        $extension = strtolower(pathinfo($organization->brochure_path, PATHINFO_EXTENSION));
        $contentType = ['pdf' => 'application/pdf', 'png' => 'image/png', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg'][$extension] ?? 'application/octet-stream';

        return Storage::disk('public')->download($organization->brochure_path, 'brosur-'.Str::slug($organization->name).'.'.$extension, ['Content-Type' => $contentType]);
    }
}
