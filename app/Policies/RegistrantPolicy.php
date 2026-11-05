<?php

namespace App\Policies;

use App\Enums\Ability;
use App\Models\Registrant;
use App\Models\User;

class RegistrantPolicy
{
    /**
     * The committee can look at the list of applicants.
     */
    public function viewAny(User $user): bool
    {
        return $user->can(Ability::RegistrantView->value);
    }

    /**
     * The committee can check, change, announce and place applicants.
     */
    public function manage(User $user): bool
    {
        return $user->can(Ability::RegistrantManage->value);
    }

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

    /**
     * Parents who can edit the data can also pick the exam session and send the data to the committee.
     */
    public function submit(User $user, Registrant $registrant): bool
    {
        return $this->update($user, $registrant);
    }
}
