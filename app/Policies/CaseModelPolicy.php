<?php

namespace App\Policies;

use App\Models\CaseModel;
use App\Models\Role;
use App\Models\User;

/**
 * Authorization for case records.
 *
 * Supervisors have office-wide oversight: they may see and edit every case.
 * Investigators are confined to the cases assigned to them.
 *
 * Abilities that are not defined here (delete) are denied by default.
 */
class CaseModelPolicy
{
    /**
     * Determine whether the user may docket a new case.
     *
     * Both roles run intake. Who the case is assigned to is constrained
     * separately by StoreCaseRequest.
     */
    public function create(User $user): bool
    {
        return $user->hasRole(...Role::CASE_HANDLING);
    }

    /**
     * Determine whether the user may list cases.
     *
     * The listing itself is scoped by CaseModel::scopeVisibleTo(), so an
     * investigator reaching the index only ever sees their own caseload.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasRole(...Role::CASE_HANDLING);
    }

    /**
     * Determine whether the user may view a specific case.
     *
     * This is the check that blocks direct-URL access to another
     * investigator's case.
     */
    public function view(User $user, CaseModel $case): bool
    {
        return $user->isSupervisor() || $this->isAssignedTo($user, $case);
    }

    /**
     * Determine whether the user may edit a specific case.
     */
    public function update(User $user, CaseModel $case): bool
    {
        return $user->isSupervisor() || $this->isAssignedTo($user, $case);
    }

    /**
     * Determine whether the case is assigned to the given investigator.
     */
    protected function isAssignedTo(User $user, CaseModel $case): bool
    {
        return $user->isInvestigator() && $case->investigator_id === $user->id;
    }
}
