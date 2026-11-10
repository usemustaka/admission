<?php

namespace App\Policies;

use App\Enums\Ability;
use App\Enums\DocumentType;
use App\Enums\RegistrantStatus;
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
     * Exporting needs the right to see the list as well as the right to export it.
     */
    public function export(User $user): bool
    {
        return $this->viewAny($user) && $user->can(Ability::RegistrantExport->value);
    }

    /**
     * Running a step over many applicants at once; each one still needs `manage`.
     */
    public function bulk(User $user): bool
    {
        return $this->viewAny($user) && $user->can(Ability::RegistrantBulk->value);
    }

    /**
     * Removing an invalid registration (and, optionally, the parent's login). Only for those who may also see it.
     */
    public function delete(User $user, Registrant $registrant): bool
    {
        return $this->viewAny($user) && $user->can(Ability::RegistrantDelete->value);
    }

    /**
     * Turning an announced applicant into a student.
     */
    public function announce(User $user): bool
    {
        return $this->viewAny($user) && $user->can(Ability::RegistrantAnnounce->value);
    }

    public function score(User $user): bool
    {
        return $this->viewAny($user) && $user->can(Ability::ExamScore->value);
    }

    /**
     * Grant a scholarship, or see who might get one.
     */
    public function grantScholarship(User $user): bool
    {
        return $this->viewAny($user) && $user->can(Ability::ScholarshipGrant->value);
    }

    public function place(User $user): bool
    {
        return $this->viewAny($user) && $user->can(Ability::RegistrantPlace->value);
    }

    /**
     * Jumping over the usual flow of statuses, with a note.
     */
    public function bypass(User $user): bool
    {
        return $this->manage($user) && $user->can(Ability::RegistrantBypass->value);
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

    /**
     * Upload or remove a file. The files a ruleset asks for follow the data: they can change while the parent may
     * still edit. Supporting files for a scholarship can be added until the child is placed.
     */
    public function manageDocument(User $user, Registrant $registrant, DocumentType $type): bool
    {
        if ($registrant->needs_identity_review || ! $this->view($user, $registrant)) {
            return false;
        }

        return $type->isSupporting()
            ? $registrant->status !== RegistrantStatus::Placed
            : $registrant->status->isEditableByParent();
    }

    /**
     * Open an uploaded file: the committee, and the parent of the child.
     */
    public function viewDocument(User $user, Registrant $registrant): bool
    {
        return $this->viewAny($user) || $this->view($user, $registrant);
    }
}
