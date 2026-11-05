<?php

namespace App\Actions\People;

use App\Models\Parentship;
use Illuminate\Support\Facades\DB;

class SetPrimaryContact
{
    /**
     * Make this parent the only primary contact of the child.
     */
    public function handle(Parentship $parentship): void
    {
        DB::transaction(function () use ($parentship): void {
            Parentship::query()
                ->where('person_id', $parentship->person_id)
                ->whereKeyNot($parentship->id)
                ->update(['is_primary_contact' => false]);

            $parentship->update(['is_primary_contact' => true]);
        });
    }
}
