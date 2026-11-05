<?php

namespace App\Actions\Admission;

use App\Actions\Notes\WritePersonAuditNote;
use App\Actions\Portal\LinkRegisteringParent;
use App\Models\Registrant;
use App\Models\User;
use App\Support\IdentityMismatchNote;
use Illuminate\Support\Facades\DB;

class ResolveIdentityReview
{
    public function __construct(
        private LinkRegisteringParent $linkRegisteringParent,
        private WritePersonAuditNote $writePersonAuditNote,
    ) {}

    /**
     * The committee confirmed that the NIK and the child match. The registering parent is now linked to the
     * child, so the child's details become visible and editable for them. The warning the system left on the
     * child is no longer urgent, and the committee member leaves a private note about it.
     *
     * The registration is read again under a row lock: a second click finds nothing left to resolve.
     */
    public function handle(Registrant $registrant, ?User $by = null): void
    {
        DB::transaction(function () use ($registrant, $by): void {
            $fresh = Registrant::query()->lockForUpdate()->findOrFail($registrant->getKey());
            $registrant->setRawAttributes($fresh->getAttributes(), sync: true);

            if (! $registrant->needs_identity_review) {
                return;
            }

            $registrant->update(['needs_identity_review' => false]);

            $this->linkRegisteringParent->handle($registrant);

            if ($by === null) {
                return;
            }

            $child = $registrant->person;

            IdentityMismatchNote::close($child, $registrant->submitted_name, $by);

            $this->writePersonAuditNote->handle(
                $child,
                $by,
                "Pencocokan NIK pendaftaran {$this->label($registrant)}: nama yang diketik orang tua \"{$registrant->submitted_name}\" dinyatakan sama dengan \"{$child->name}\".",
            );
        });
    }

    private function label(Registrant $registrant): string
    {
        $registrant->loadMissing(['organization', 'wave']);

        return "{$registrant->organization->name} ({$registrant->wave->name})";
    }
}
