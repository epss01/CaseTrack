---
name: maker-checker
description: Scaffold an approval-required action where an Investigator proposes and a Supervisor confirms before it takes effect — case closure being the named example. Use when building any high-risk case action that needs secondary validation.
---

# Maker-checker actions

Investigator = **Maker** (proposes). Supervisor = **Checker** (confirms).
The action does not take effect until the Checker confirms.

Not built yet — the manuscript models it (Use Case Fig. 3-3, Activity
Fig. 3-4) and `wiki/project/wcs-assignment-logic.md` tracks it as planned. The
named high-risk example is **case closure**.

## Do not build an approval engine

No `approvals` table, no generic state machine, no `Approvable` interface, no
new middleware. Every piece needed already exists:

| Need | Use |
|---|---|
| Who may propose / who may confirm | `CaseModelPolicy` + `EnsureUserHasRole` |
| Recording who did what, when | `AuditLog::record()` |
| Holding the pending state | a status value on the record itself |
| Atomicity | `DB::transaction()` |

## The pending state

Prefer an intermediate value in `cases.status` over a new column. Closure:

```
Docketed  --(maker proposes)-->  Pending Closure  --(checker confirms)-->  Closed
                                        |
                                        +--(checker rejects)--> back to Docketed
```

Add the value as a constant on `CaseModel` beside `STATUS_DOCKETED` and
`STATUS_CLOSED`.

**Check what this does to `scopeActive()`.** It excludes only `STATUS_CLOSED`,
so a Pending Closure case still counts toward the investigator's Workload
Capacity Score. That is correct — the case is not closed until the Checker
says so — but confirm it's the behaviour you want, because it silently moves
who gets suggested for new assignments.

If the action isn't status-shaped (something with no natural pending value on
the record), that needs a new column or table. **Stop and ask** rather than
picking one — it's a schema decision, and schema decisions get recorded in the
vault.

## The four pieces

**1. Two policy methods, not one.** Propose and confirm are different
permissions held by different roles. Follow the `case-policy-check` skill:

```php
public function proposeClosure(User $user, CaseModel $case): bool
{
    return $user->isSupervisor() || $this->isAssignedTo($user, $case);
}

public function confirmClosure(User $user, CaseModel $case): bool
{
    return $user->isSupervisor();
}
```

**2. Two routes**, both in the existing `role:Investigator,Supervisor` group in
`routes/web.php`. Both are custom actions, so `authorizeResource()` does not
cover them — call `$this->authorize(...)` explicitly, as `reassign()` does.

**3. Audit both ends, inside the transaction.** The proposal is as much a
recorded act as the confirmation — an audit trail showing only the outcome
can't answer who asked for it:

```php
DB::transaction(function () use ($request, $case) {
    $case->update(['status' => CaseModel::STATUS_PENDING_CLOSURE]);

    AuditLog::record($request->user(), $case, AuditLog::ACTION_UPDATE);
});
```

Add a specific `ACTION_*` constant per step (e.g. `ACTION_CLOSURE_PROPOSED`,
`ACTION_CLOSURE_CONFIRMED`) rather than reusing bare `ACTION_UPDATE` — the
whole value of the trail is telling the two apart.

**4. A confirmation page, not a JS dialog.** `cases/confirm-delete.blade.php`
is the precedent: a real GET route rendering a page, so the confirmation step
survives with scripting off.

## The check that actually matters

A Maker must not be able to confirm their own proposal. Test it directly:

```php
public function test_an_investigator_cannot_confirm_their_own_proposal(): void
{
    $investigator = User::factory()->investigator()->create();
    $case = CaseModel::factory()->assignedTo($investigator)->create([
        'status' => CaseModel::STATUS_PENDING_CLOSURE,
    ]);

    $this->actingAs($investigator)
        ->put("/cases/{$case->id}/closure/confirm")
        ->assertForbidden();

    $this->assertSame(CaseModel::STATUS_PENDING_CLOSURE, $case->fresh()->status);
}
```

Hit the raw URL, and assert the state did **not** move. Also cover: a
supervisor can confirm; confirming a case that was never proposed is rejected;
and both steps wrote their audit rows.

## Open question to raise, not answer

Whether a Supervisor proposing an action can then confirm it themselves.
`CaseModelPolicy` currently gives supervisors everything an investigator has,
so the policies above let them. Whether that defeats the point of
maker-checker is a CHR process question — surface it, don't decide it.
