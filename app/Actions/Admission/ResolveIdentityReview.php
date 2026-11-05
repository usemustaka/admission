<?php

namespace App\Actions\Admission;

use App\Actions\Portal\LinkRegisteringParent;
use App\Models\Registrant;
use Illuminate\Support\Facades\DB;

class ResolveIdentityReview
{
    public function __construct(private LinkRegisteringParent $linkRegisteringParent) {}

    /**
     * The committee confirmed that the NIK and the child match. The registering parent is now linked to the
     * child, so the child's details become visible and editable for them.
     */
    public function handle(Registrant $registrant): void
    {
        DB::transaction(function () use ($registrant): void {
            $registrant->update(['needs_identity_review' => false]);

            $this->linkRegisteringParent->handle($registrant);
        });
    }
}
