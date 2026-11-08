<?php

namespace App\Support;

use App\Models\Registrant;
use App\Models\RegistrantDocument;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use ZipArchive;

/**
 * Packs the files uploaded for registrations into a ZIP, one folder per child, so the committee can download them in
 * one go. The archive is written to a temporary file that the caller sends and removes.
 */
class RegistrantDocumentArchive
{
    /**
     * Build the ZIP of the given registrations and return the path of the temporary file, or null when none of them
     * has a file.
     *
     * @param  Collection<int, Registrant>  $registrants  with `documents` and `person` loaded
     */
    public static function build(Collection $registrants): ?string
    {
        $disk = Storage::disk(RegistrantDocument::DISK);
        $path = tempnam(sys_get_temp_dir(), 'berkas');
        $zip = new ZipArchive;

        if ($path === false || $zip->open($path, ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Arsip berkas tidak bisa dibuat.');
        }

        $added = 0;

        foreach ($registrants as $registrant) {
            $folder = Str::slug($registrant->person->name).'-'.$registrant->id;

            foreach ($registrant->documents as $document) {
                if (! $disk->exists($document->path)) {
                    continue;
                }

                $extension = pathinfo($document->path, PATHINFO_EXTENSION) ?: 'pdf';
                $name = Str::slug($document->type->label().' '.($document->type->maxFiles() > 1 ? $document->slot : ''));

                $zip->addFile($disk->path($document->path), "{$folder}/{$name}.{$extension}");
                $added++;
            }
        }

        $zip->close();

        if ($added === 0) {
            @unlink($path);

            return null;
        }

        return $path;
    }
}
