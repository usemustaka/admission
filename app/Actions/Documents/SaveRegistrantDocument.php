<?php

namespace App\Actions\Documents;

use App\Enums\DocumentType;
use App\Models\Registrant;
use App\Models\RegistrantDocument;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class SaveRegistrantDocument
{
    /**
     * Keep an uploaded file as one slot of a file type. Without a slot it takes the first free one; with a slot it
     * replaces what was there. The file is stored first and the old one removed only once the new one is recorded,
     * so a failure never leaves a registration pointing at a missing file.
     *
     * @throws ValidationException
     */
    public function handle(Registrant $registrant, DocumentType $type, UploadedFile $file, ?int $slot = null, ?User $by = null): RegistrantDocument
    {
        $disk = Storage::disk(RegistrantDocument::DISK);

        $stored = $file->store("registrant-documents/{$registrant->id}", RegistrantDocument::DISK);

        if ($stored === false) {
            throw ValidationException::withMessages(['file' => 'Berkas tidak bisa disimpan. Coba kirim ulang.']);
        }

        try {
            [$document, $replacedPath] = DB::transaction(function () use ($registrant, $type, $file, $slot, $by, $stored): array {
                $taken = $registrant->documents()->where('type', $type)->lockForUpdate()->pluck('slot')->all();
                $slot ??= collect(range(1, $type->maxFiles()))->first(fn (int $candidate): bool => ! in_array($candidate, $taken, true));

                if ($slot === null) {
                    throw ValidationException::withMessages(['file' => "Berkas {$type->label()} sudah penuh. Hapus salah satu dulu."]);
                }

                $existing = $registrant->documents()->where('type', $type)->where('slot', $slot)->first();
                $replacedPath = $existing?->path;

                $attributes = [
                    'path' => $stored,
                    'original_name' => $this->safeName($file),
                    'mime_type' => (string) $file->getMimeType(),
                    'size' => (int) $file->getSize(),
                    'uploaded_by_user_id' => $by?->id,
                ];

                $document = $existing === null
                    ? $registrant->documents()->create(['type' => $type, 'slot' => $slot, ...$attributes])
                    : tap($existing)->update($attributes);

                return [$document, $replacedPath];
            });
        } catch (\Throwable $exception) {
            $disk->delete($stored);

            throw $exception;
        }

        if ($replacedPath !== null && $replacedPath !== $stored) {
            $disk->delete($replacedPath);
        }

        return $document;
    }

    private function safeName(UploadedFile $file): string
    {
        return mb_substr(preg_replace('/[^\p{L}\p{N}\s._()-]+/u', '', $file->getClientOriginalName()) ?: 'berkas', -150);
    }
}
