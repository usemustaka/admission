<?php

namespace App\Policies;

use App\Models\Registrant;
use App\Models\User;

class RegistrantPolicy
{
    /**
     * Parents and guardians of the child can follow the registration.
     */
    public function view(User $user, Registrant $registrant): bool
    {
        return Registrant::query()->forParent($user)->whereKey($registrant->id)->exists();
    }

    /**
     * They can change the data only while it is still a draft or the committee asked for corrections, and not
     * while the committee is still confirming the child's NIK.
     */
    public function update(User $user, Registrant $registrant): bool
    {
        return $registrant->status->isEditableByParent()
            && ! $registrant->needs_identity_review
            && $this->view($user, $registrant);
    }
}
