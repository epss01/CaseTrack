# CaseTrack — Project Memory

## What this is
CaseTrack: A Web-Based Investigation Case Assignment, Monitoring, and Reporting System.
Capstone project (CHR Region VIII use case). Standalone internal web app — NOT public-facing,
NOT an AI/chatbot system. Deterministic, rule-based deadline tracking only.

## Stack
- Backend: PHP 8.2, Laravel 11
- Database: MySQL (XAMPP, port 3307 — a separate standalone MySQL 8 runs on 3306; `.env`
  points at the XAMPP instance, database `casetrack`). Tests run on SQLite in-memory
  via `phpunit.xml`.
- Frontend: HTML5, CSS3, JavaScript, Bootstrap 5 (`laravel/ui` preset, not Breeze/Tailwind)
- Local dev: XAMPP

## Project knowledge base
This repo has a companion Obsidian vault at the path configured in
.claude/settings.json (additionalDirectories). Before implementing or
modifying anything related to the database schema, the WCS assignment
logic, or architecture decisions, read wiki/_master-index.md in that
vault first, then follow links to the relevant page(s) if one exists.
Treat the wiki as authoritative project context — don't re-derive
decisions that are already documented there. Never write to the vault;
it's read-only from this repo. If you notice the wiki is missing or
out of date on something you just built, tell me instead of updating
it yourself — I'll hand that off to Cowork.

## Session logging
When asked to "log this session," write the session note directly to
raw/sessions/YYYY-MM-DD.md in the vault (create the file if it doesn't
exist yet today, append if a note for today already exists from an
earlier session). Follow the format already established in prior
session notes there. This is the one write exception to the read-only
vault rule — raw/ is a capture zone, not curated knowledge, so it's
fine for Claude Code to own it directly. Still don't write to wiki/
directly: keep proposing the wiki/log.md append line and any
sprint-checklist.md changes at the bottom of the session note, for
Cowork to apply — exactly as before.

### Note for teammates
`.claude/settings.json` is committed and team-shared, but the vault path in it is
machine-specific. It currently points at this machine's location:

```
C:/Users/admin/Downloads/Capstone_CaseTrack/CaseTrack_Vault/CaseTrack/wiki
```

If your local vault lives somewhere else, update `permissions.additionalDirectories`
in `.claude/settings.json` to your own absolute path to the vault's
`CaseTrack/wiki` folder. (Avoid committing your local path change unless the whole
team moves — or override it in `.claude/settings.local.json`, which is gitignored.)

## Core entities (as built — these are the real table/model names)
The manuscript (Chapter III, Tables 3-4–3-10) uses `auth_user` / `casetrack_*` names.
**The code does not.** Use the as-built names below. See `wiki/project/database-schema.md`
in the vault for the full as-designed vs. as-built comparison.

- `roles` — `role_name`. Model `Role`, constants `Role::INVESTIGATOR` / `Role::SUPERVISOR`.
- `users` — investigator/staff accounts. `username` (unique), `password` (hashed),
  `first_name`, `last_name`, `office_region` (default 'CHR Region VIII'),
  `is_staff` (bool, see note below), `performance_rating` (decimal 2,1, default 1.0),
  `role_id` FK → `roles.id`. No email column — auth is username-based.
- `cases` — core case record. Model is `CaseModel`, not `Case` (reserved PHP word);
  `protected $table = 'cases'`. Fields: `docket_no` (unique), `case_title`,
  `incident_details`, `source_info` (nullable), `investigator_id` FK → `users.id`,
  `status` (NOT NULL, set server-side to `CaseModel::STATUS_DOCKETED` at intake),
  `complexity_weight` (int), `deleted_at` (soft deletes).
- `victims`, `respondents` — `case_id` FK (cascade on delete), `name`, `age` (nullable),
  `status`, `sector`.
- `complainants` — `case_id` FK (cascade on delete), `name`.
- `case_timelines` — `case_id` FK, **unique** (1:1 with a case, cascade on delete),
  `date_of_docket` (NOT NULL, captured at intake), plus `date_submission_rop`,
  `extension_30_days`, `submission_60th_day`, `submission_120th_day`, `target_date_fir`,
  `date_fir_submitted`, `date_submitted_to` — all nullable, filled in later via
  `CaseTimelineController` (Set Timeline action).
- `audit_logs` — `user_id` FK, `case_id` FK **nullable** (`nullOnDelete`),
  `action_performed`, `timestamp` (`useCurrent`; this table has no created_at/updated_at
  pair — `AuditLog` maps `CREATED_AT = 'timestamp'` and `UPDATED_AT = null`).

A case has many victims, many respondents, and many complainants (zero or more — a case
opened from a media report may name no complainant). It has one timeline.

Cases are **soft-deleted**, never hard-deleted: `audit_logs.case_id` is `nullOnDelete`,
so a hard delete would blank the very DELETE entry that makes deletion traceable.

## Workload Capacity Score (built)
`WCS_i = Σ_{j ∈ A_i} C_j × (2 − P_i)`, implemented in `User::workloadCapacityScore()`.

- `A_i` — the active caseload: `CaseModel::scopeActive()` (excludes `STATUS_CLOSED` and,
  via SoftDeletes, deleted cases).
- `C_j` — `cases.complexity_weight`, a required 1–5 judgment at intake
  (`CaseModel::complexityWeightRules()`).
- `P_i` — `users.performance_rating`, 0.1–1.0, supervisor-set on `/workload`. Default 1.0
  means an unrated investigator scores the plain sum of weights.

Lower relative WCS = suggested for a new assignment. **WCS suggests; it never assigns** —
the supervisor still chooses. Both `C_j` and `P_i` are human-entered judgment calls, not
derived from history; that's a build decision, not something the source doc specifies.

## Roles / access rules
- **Investigator** — sees and manages only their own assigned cases.
- **Supervisor** — office-wide: sees and edits every case, and alone may delete, reassign,
  or set performance ratings.
- Authorization is driven by `roles`/`role_id`, via `User::hasRole()` /
  `isSupervisor()` / `isInvestigator()`. **`is_staff` still exists on `users` but nothing
  reads it for authorization** — it's only written by the factory/seeder and asserted in
  one auth test. Don't add new logic that branches on it; use roles.
- Two enforcement layers, both backend, never UI-only:
  - `EnsureUserHasRole` middleware, aliased `role` — coarse route gate,
    e.g. `role:Investigator,Supervisor` on the case group, `role:Supervisor` on `/workload`.
  - `CaseModelPolicy` — per-record decisions (`viewAny`, `view`, `create`, `update`,
    `delete`, `reassign`), wired via `authorizeResource()`.
  - List queries use `CaseModel::scopeVisibleTo($user)`, which mirrors `view()`.
- A page with no per-case decision to make (like `/workload`) is gated by middleware alone
  — don't invent a policy class for it.

## Business logic that MUST stay deterministic (no AI/LLM involved)
- Statutory legal deadlines: 30-day, 60-day, and 120-day milestones off `case_timelines`.
- Due-date alerts: flag cases approaching or past these milestones.
- Dashboard aggregates: case counts by status, per region/investigator.
- The WCS calculation above.

**Known blocker:** the 60th-day milestone applies only to torture cases, and there is no
case-type/category field anywhere in the schema — manuscript or built. `DemoDataSeeder::bucketFor()`
is an explicit placeholder for the real countdown logic and deliberately skips the 60-day
column for this reason. Flag this rather than inventing a workaround.

## Explicit delimitations (do not build these)
- No AI-powered chatbot, no NLP/text-mining features.
- No legal decision-making or evidence analysis logic — the system tracks status/timelines only.
- No public/external user access — internal authorized users only.

## Conventions
- Follow existing Laravel conventions already in the repo (naming, folder structure, migrations).
- Write migrations for any schema change; don't hand-edit the database.
- Keep controllers thin. **Forward guidance:** when the countdown-alert feature is built,
  put its deadline/date-math in a dedicated `App\Services\CaseDeadlineService` so it's
  unit-testable. **That class does not exist yet** — `app/Services/` has not been created.
  Until it does, there is no date math on timeline fields anywhere in the app code.
- Every new feature that touches case data needs a role/policy check.
- Every state-changing action on case data should write an audit entry via
  `AuditLog::record($user, $case, $action)` (`$case` may be null for actions not scoped to
  one case, like a rating change). Today only delete, reassign, and performance-rating
  changes do this — intake and edit do not yet.
- Share validation rules rather than duplicating them across requests — see
  `Role::assignableInvestigatorRule()` and `CaseModel::complexityWeightRules()`.

## Workflow notes
- Work in small, single-feature increments (one controller/feature/migration at a time).
- After generating code, run `php artisan test` before moving to the next task.
- Flag any assumption you make about a field or rule instead of guessing silently.
