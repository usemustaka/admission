<?php

namespace App\Actions\People;

use App\Models\Person;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class SavePerson
{
    /**
     * Create or update a person, replacing or removing their profile photo when asked.
     *
     * @param  array<string, mixed>  $data
     */
    public function handle(?Person $person, array $data, ?UploadedFile $photo = null, bool $removePhoto = false): Person
    {
        $person ??= new Person;
        $person->fill($data);

        if ($photo !== null || $removePhoto) {
            if ($person->photo_path !== null) {
                Storage::disk('public')->delete($person->photo_path);
            }

            $person->photo_path = $photo?->store('people/photos', 'public') ?: null;
        }

        $person->save();

        return $person;
    }
}
