<?php

namespace App\Actions\Documents;

use App\Models\RegistrantDocument;
use Illuminate\Support\Facades\Storage;

class DeleteRegistrantDocument
{
    public function handle(RegistrantDocument $document): void
    {
        $path = $document->path;

        $document->delete();

        Storage::disk(RegistrantDocument::DISK)->delete($path);
    }
}
