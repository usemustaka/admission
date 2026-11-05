<?php

namespace App\Actions\People;

use App\Models\Parentship;
use Illuminate\Support\Facades\DB;

class UnlinkParent
{
    /**
     * Remove a parent from a child. When that parent was the primary contact, the next parent with a phone number
     * (lowest id first) takes over; if no parent has a phone number the child is left without a primary contact.
     */
    public function handle(Parentship $parentship): void
    {
        DB::transaction(function () use ($parentship): void {
            $wasPrimaryContact = $parentship->is_primary_contact;

            $parentship->delete();

            if (! $wasPrimaryContact) {
                return;
            }

            $successor = Parentship::query()
                ->where('person_id', $parentship->person_id)
                ->whereHas('relatedPerson', fn ($query) => $query->whereNotNull('phone')->where('phone', '!=', ''))
                ->orderBy('id')
                ->first();

            if ($successor !== null) {
                app(SetPrimaryContact::class)->handle($successor);
            }
        });
    }
}
