# Project instructions

## Project knowledge base

This repo has a companion Obsidian vault at:

```
C:/Users/admin/Downloads/Capstone_CaseTrack/CaseTrack_Vault/CaseTrack
```

Before implementing or modifying anything related to the database schema, the
WCS assignment logic, or architecture decisions, read:

```
C:/Users/admin/Downloads/Capstone_CaseTrack/CaseTrack_Vault/CaseTrack/wiki/_master-index.md
```

Then follow links to the relevant page(s) if one exists. Treat the wiki as
authoritative project context—do not re-derive decisions that are already
documented there.

The `wiki/` portion of the vault is read-only for project work. If it is
missing or out of date on something just built, report it instead of updating
it directly.

## Session logging

When asked to log the session, write the session note directly to:

```
C:/Users/admin/Downloads/Capstone_CaseTrack/CaseTrack_Vault/CaseTrack/raw/sessions/YYYY-MM-DD.md
```

Create the file if it does not exist for today; otherwise append to today’s
note. Follow the format established in prior session notes.

This is the one write exception to the read-only vault rule: `raw/` is a
capture zone. Do not write directly to `wiki/`; include proposed
`wiki/log.md` and `wiki/roadmap/sprint-checklist.md` changes at the bottom of
the session note for later application.

## Project scope and stack

- CaseTrack is a standalone internal investigation case assignment,
  monitoring, and reporting system. It is deterministic and rule-based; it is
  not an AI/chatbot system.
- Backend: PHP 8.4 and Laravel 11. Use Herd Lite's PHP binary at
  `C:/Users/admin/.config/herd-lite/bin/php.exe`; XAMPP's PHP 8.2 binary cannot
  run this project. Composer is invoked through that PHP binary using
  `C:/Users/admin/.config/herd-lite/bin/composer.phar`.
- Database: MariaDB 10.4 on port 3307 for browser-driven development. Tests
  use in-memory SQLite. The Laravel `mysql` driver is correct for MariaDB.
- Frontend: HTML5, CSS3, JavaScript, Bootstrap 5 via `laravel/ui`; do not
  introduce Breeze or Tailwind without an explicit decision.

## Security and dependency decisions

- The two currently accepted Laravel 11 dependency advisories are not
  reachable because the app has no email validation/auth flows and no signed
  or temporary URLs. Revisit the Laravel 12 upgrade if either vector is
  introduced; adding either makes the upgrade a prerequisite.
- Authorization must be enforced in the backend at both layers: route role
  middleware and per-record policies. Never rely on UI-only restrictions.
- Use roles and `role_id` (`hasRole()`, `isSupervisor()`,
  `isInvestigator()`, `isAdmin()`), not the legacy `is_staff` field, for
  authorization.
- Investigators manage only their assigned cases. Supervisors have office-wide
  case access and alone may delete, reassign, or set performance ratings.
  Admins never access case data; they only manage registrations and accounts.
- Admin-only pages without per-case decisions use role middleware alone; do
  not invent policy classes for them.
- Account deactivation is a soft flag, never deletion. Admin account mutations
  must not target the acting admin's own account.

## Data and business rules

- Use the as-built table/model names. The case model is `CaseModel`, not
  `Case`; it maps to `cases`.
- Cases are soft-deleted, never hard-deleted. A case may have zero or more
  victims, respondents, and complainants, and exactly one case timeline.
- The ratified case-status vocabulary is `Docketed`, `Under investigation`,
  `For review`, and `Closed`. Flag requests for any additional status instead
  of inventing one.
- WCS is `sum(complexity_weight * (2 - performance_rating))` over active
  cases. Complexity is a human-entered value from 1-5; performance rating is
  human-entered from 0.1-1.0. WCS suggests an assignment; it never assigns
  one automatically.
- Statutory deadlines, alerts, dashboard aggregates, and WCS must remain
  deterministic. The 60th-day torture-case rule is blocked by the missing
  case-type field; flag it rather than inventing a workaround.
- Only the assigned investigator may close or reopen a case through the
  ordinary case edit form. Supervisors may edit non-closure fields office-wide
  but cannot change a case across the `Closed` boundary. Closed cases do not
  count as active for WCS.

## Implementation rules

- Follow the existing Laravel naming, folder, and migration conventions.
  Schema changes require migrations; never hand-edit the database.
- Keep controllers thin and share validation rules instead of duplicating
  them. Reuse existing helpers such as
  `Role::assignableInvestigatorRule()` and
  `CaseModel::complexityWeightRules()`.
- All deadline/date arithmetic for `case_timelines` belongs in
  `App\Services\CaseDeadlineService`. Other code may format an already-cast
  date, but must not calculate deadlines independently.
- Every new feature touching case data needs the appropriate role and policy
  check.
- Every state-changing action must write the appropriate audit entry inside
  the same database transaction using `AuditLog::record(...)`. Use distinct
  action constants when the audit trail would otherwise lose the action's
  meaning. The deliberate exception is the non-mutating CSV export, which is
  audited as an export without a transaction; ordinary page views are not
  audited.
- Do not add AI, NLP, text-mining, legal decision-making, evidence-analysis,
  or public/external-user features. CaseTrack tracks statuses and timelines
  for authorized internal users.

## Development workflow

- Work in small, single-feature increments.
- After generating code, run the Laravel test suite using the PHP 8.4 binary:

  ```text
  C:/Users/admin/.config/herd-lite/bin/php.exe artisan test
  ```

- After changes under `resources/sass/` or `resources/js/`, run `npm run build`.
  Blade, PHP, and route changes do not require an asset rebuild.
- For changed Blade views, check both accessibility with the Impeccable audit
  and usability/workflow with the UI/UX review process. Consult `PRODUCT.md`
  and `DESIGN.md` before making new design decisions.
- Flag assumptions about fields, rules, or unresolved product decisions rather
  than guessing silently.
