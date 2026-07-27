---
name: case-policy-check
description: Wire authorization for a new controller action or route that touches case data — the CaseModelPolicy method plus the EnsureUserHasRole route wiring, following the pattern already in the repo. Use whenever adding a route, controller action, or form request that reads or writes cases, victims, respondents, complainants, case_timelines, or audit_logs.
---

# Wiring authorization for a case-data action

CaseTrack enforces access in two layers, both backend. Never rely on hiding a
button in a Blade view — the tests in `tests/Feature/CaseAccessControlTest.php`
exist because direct-URL access is the threat.

## Step 1 — decide which layer the action belongs to

Ask one question: **does the decision depend on which case it is?**

- **Yes** (this case belongs to this investigator) → policy method on
  `CaseModelPolicy`, plus the route in the existing case group.
- **No** (the whole page is supervisor-only, nothing per-record) → route
  middleware alone. `WorkloadController` is the precedent: it has no policy
  class, because `CaseModelPolicy` only knows how to reason about a single
  case. Do not invent a policy class for a page with no case in scope.

## Step 2 — the route

Both role-gated groups already exist in `routes/web.php`. Add to one of them
rather than declaring new middleware:

```php
// Per-case decisions — CaseModelPolicy then narrows it per record.
Route::middleware(['auth', 'role:Investigator,Supervisor'])->group(function () { ... });

// Office-wide, supervisor-only, no per-case decision.
Route::middleware(['auth', 'role:Supervisor'])->group(function () { ... });
```

Role names come from `Role::INVESTIGATOR` / `Role::SUPERVISOR`. The middleware
alias `role` maps to `EnsureUserHasRole`, which 403s on a missing or wrong role.

## Step 3 — the policy method

Add to `app/Policies/CaseModelPolicy.php`, matching the existing shape:

```php
/**
 * Determine whether the user may <action>.
 *
 * <One line on why this role boundary and not another.>
 */
public function yourAction(User $user, CaseModel $case): bool
{
    // Office-wide action: supervisor-only, including on an investigator's
    // own case — follow delete()/reassign().
    return $user->isSupervisor();

    // Casework action: the assigned investigator, or any supervisor —
    // follow view()/update().
    return $user->isSupervisor() || $this->isAssignedTo($user, $case);
}
```

Use `$user->isSupervisor()` / `isInvestigator()` / `hasRole()`. **Never branch
on `is_staff`** — the column still exists but nothing reads it for
authorization.

## Step 4 — invoke it

`CaseController::__construct()` calls `authorizeResource()`, which covers the
seven resource actions only (`index`→`viewAny`, `create`/`store`→`create`,
`show`→`view`, `edit`/`update`→`update`, `destroy`→`delete`).

A **custom** action is not covered and must authorize explicitly, as
`confirmDelete()`, `reassignForm()` and `reassign()` do:

```php
$this->authorize('yourAction', $case);
```

If the action has a FormRequest, leave its `authorize()` returning `true` and
say so in a docblock — `ReassignCaseRequest` is the precedent. Authorization
lives in the policy; the request validates.

## Step 5 — list queries

Any query returning multiple cases goes through `CaseModel::visibleTo($user)`,
which mirrors `view()`. A policy check on a single record does not protect a
listing.

## Step 6 — the tests that make it real

Add to `tests/Feature/CaseAccessControlTest.php` (or the closest existing
file). Three cases, minimum:

1. The permitted role reaches it and gets `assertOk()`.
2. The denied role gets `assertForbidden()` — hit the **raw URL**, not
   `route()` with a helper that might hide the failure.
3. State did not change on the denied attempt (`$model->fresh()`).

Use `User::factory()->investigator()` / `->supervisor()` and
`CaseModel::factory()->assignedTo($user)`.

## Before you finish

- Does the action change case data? Then it also needs an audit entry — see
  the `maker-checker` skill and `AuditLog::record()`.
- Run `php artisan test`. The Stop hook will block the turn if it fails.
