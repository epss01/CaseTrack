# CaseTrack — Project Memory

## What this is
CaseTrack: A Web-Based Investigation Case Assignment, Monitoring, and Reporting System.
Capstone project (CHR Region VIII use case). Standalone internal web app — NOT public-facing,
NOT an AI/chatbot system. Deterministic, rule-based deadline tracking only.

## Stack
- Backend: PHP, Laravel 11 (`v11.55.0` locked). **Requires PHP 8.4** — `composer.json`
  declares `"php": "^8.4"` and `vendor/composer/platform_check.php` hard-fails anything
  below 8.4.0. (Until 2026-07-29 the constraint said `^8.2` while the lock resolved
  against 8.4; the two now agree.) **XAMPP's bundled PHP (8.2.12, at `C:\xampp\php\php.exe`) cannot run
  `artisan` at all.** The working binary is Herd Lite:
  `C:\Users\admin\.config\herd-lite\bin\php.exe`. **It is NOT on PATH** — verified
  2026-07-28, `Get-Command php` in PowerShell and `php -v` in bash both come back empty.
  Always invoke it by absolute path; never fall back to the XAMPP one. E.g.:

  ```
  "C:/Users/admin/.config/herd-lite/bin/php.exe" artisan test
  ```

  Adding `%USERPROFILE%\.config\herd-lite\bin` to PATH would remove the need for this,
  but nothing in the repo depends on that being done.
- Database: MariaDB 10.4 (XAMPP's bundled database, not a separate MySQL install — verified
  2026-08-06, `SELECT VERSION()` on port 3307 returns `10.4.32-MariaDB`; README and this file
  previously said "MySQL" here, which was wrong), port 3307 — a separate standalone MySQL 8
  runs on 3306; `.env` points at the XAMPP instance, database `casetrack`. Tests run on SQLite
  in-memory via `phpunit.xml` — only the test suite uses SQLite, so anything driven through a
  browser is hitting the real MariaDB database. (`DB_CONNECTION=mysql` in `.env` is still
  correct — Laravel's MySQL driver speaks MariaDB's wire protocol natively.)
- Frontend: HTML5, CSS3, JavaScript, Bootstrap 5 (`laravel/ui` preset, not Breeze/Tailwind)
- Local dev: XAMPP for MariaDB, Herd Lite for PHP. A Docker alternative (`docker compose up`,
  `mariadb:10.4` + `php:8.4-cli`) also exists as of 2026-08-06 — see README's "Running with
  Docker" section. Additive only; doesn't replace this host setup.
- Composer lives beside the PHP binary and is likewise **not on PATH**:
  `C:\Users\admin\.config\herd-lite\bin\composer.phar`, invoked through the same
  absolute PHP path. (The `composer.phar` under `AppData\Roaming\Composer` is
  Composer's own global install dir, not the launcher — don't use it.)

### Dependency security — two advisories accepted, not patched (2026-07-29)
`composer audit` reports three advisories against `laravel/framework` v11.55.0, which are
two distinct issues (the CRLF one is listed twice, as GHSA-5vg9-5847-vvmq and again as its
CVE-2026-48019 entry). **Decision: both accepted unpatched.** Neither is reachable here:

- **CRLF injection in the default `email` validation rule** — nothing in `app/`, `routes/`
  or `config/` uses the `email` rule. Users have no email column; `routes/web.php` disables
  the email-based flows outright (`Auth::routes(['reset' => false, 'verify' => false])`)
  and `RegisterController::validator()` has no email field.
- **Temporary signed-URL path confusion** — no `signedRoute`, `temporarySignedRoute`,
  `hasValidSignature`, or `signed` middleware anywhere in the app.

Both advisories list the *entire* 11.x line as affected with fixes only at 12.60.0/12.61.1
— there is no patched 11.x release, so remediation means a **Laravel 12 major upgrade**.
Not worth the risk to the `laravel/ui` auth scaffolding and the test suite for issues the
app cannot reach.

**Revisit when — and only when — either vector is introduced:** an `email` validation rule
(including re-enabling password reset or email verification), or any signed/temporary URL.
Adding either makes the upgrade a prerequisite, not a nice-to-have.

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

`.claude/launch.json` — the dev-server config the Browser pane uses — has the same
problem but no portable form: `runtimeExecutable` must be an absolute path to your PHP
binary, because the launcher spawns it directly with no shell, so `${env:USERPROFILE}`
and `~` are passed through literally rather than expanded (tested 2026-07-28). The file
is therefore **gitignored**, with `.claude/launch.json.example` committed in its place.
To set up: copy the example to `.claude/launch.json` and replace `runtimeExecutable`
with your own absolute path to `herd-lite/bin/php.exe`.

## Core entities (as built — these are the real table/model names)
The manuscript (Chapter III, Tables 3-4–3-10) uses `auth_user` / `casetrack_*` names.
**The code does not.** Use the as-built names below. See `wiki/project/database-schema.md`
in the vault for the full as-designed vs. as-built comparison.

- `roles` — `role_name`. Model `Role`, constants `Role::INVESTIGATOR` / `Role::SUPERVISOR` /
  `Role::ADMIN` (added 2026-07-31). `Role::CASE_HANDLING` is still only the first two —
  Admin deliberately never enters it, since `CaseModelPolicy` and three nav links treat
  membership in that constant as "may open, view, or edit a case," which an Admin never
  does. `Role::ALL` (all three) exists solely for `RoleSeeder`.
- `users` — investigator/staff/admin accounts. `username` (unique), `password` (hashed),
  `first_name`, `last_name`, `office_region` (default 'CHR Region VIII'),
  `is_staff` (bool, see note below), `performance_rating` (decimal 2,1, default 1.0),
  `role_id` FK → `roles.id`. No email column — auth is username-based. Added 2026-07-31:
  `registration_status` (string, default `'pending'`; constants `User::REGISTRATION_PENDING`
  / `_APPROVED` / `_REJECTED`), `approved_by` (nullable FK → `users.id`, `nullOnDelete`),
  `approved_at` (nullable timestamp), `is_active` (bool, default `true`).
- `cases` — core case record. Model is `CaseModel`, not `Case` (reserved PHP word);
  `protected $table = 'cases'`. Fields: `docket_no` (unique), `case_title`,
  `incident_details`, `source_info` (nullable), `investigator_id` FK → `users.id`,
  `status` (NOT NULL, set server-side to `CaseModel::STATUS_DOCKETED` at intake),
  `status_before_closure` (nullable — see Maker-checker below), `complexity_weight` (int),
  `deleted_at` (soft deletes).
  `status` is free text with three named constants (`STATUS_DOCKETED`,
  `STATUS_PENDING_CLOSURE`, `STATUS_CLOSED`); `'Under investigation'` and `'For review'`
  are also in circulation via the factory and `DemoDataSeeder` but are **not** constants.
  There is no ratified status vocabulary yet — flag it rather than inventing one.
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

## Maker-checker on case closure (built)
Investigator = Maker, Supervisor = Checker. Closure is the only action behind it — the one
high-risk action the manuscript names (Use Case Fig. 3-3, Activity Fig. 3-4).

```
Docketed / Under investigation / For review
        │ (Maker: PUT cases/{case}/closure)
        ▼
  Pending Closure ──(Checker confirms)──▶ Closed
        │
        └────────(Checker rejects)──────▶ back to status_before_closure
```

- No approvals table and no state-machine library: the pending state is a value in
  `cases.status`, because that column was already unconstrained.
- `status_before_closure` holds where the case sat when closure was proposed, so a
  rejection reverts exactly rather than flattening every case to `Docketed`. Set on
  propose, cleared on either resolution, null at all other times.
- `CaseModelPolicy::proposeClosure()` (assigned investigator or supervisor) and
  `resolveClosure()` (supervisor only). Confirm and reject share one policy method —
  same decision, same role, opposite conclusions.
- `CaseModel::statusRulesFor($case)` keeps the ordinary edit form out of the closure
  states, so status can't be typed straight to `Closed`. It still lets a case resubmit
  its *own* current status, or an already-closed case could never be edited again.
- **`scopeActive()` excludes only `STATUS_CLOSED`, so a Pending Closure case still counts
  toward its investigator's WCS.** Deliberate: it isn't closed until the Checker says so.
- **Open for CHR:** a Supervisor passes `proposeClosure()` too, so nothing stops one
  proposing and then confirming their own closure. Rejections also carry no reason field.
  Both are process questions, not code gaps — don't "fix" either without asking.

## Roles / access rules
- **Investigator** — sees and manages only their own assigned cases.
- **Supervisor** — office-wide: sees and edits every case, and alone may delete, reassign,
  or set performance ratings.
- **Admin** (added 2026-07-31) — never touches case data. Scoped to exactly two things:
  approving/rejecting new registrations and managing existing accounts (activate/deactivate,
  role change, password reset). Gated by `role:Admin` middleware alone, no policy class —
  same convention as `/workload`, since nothing here is a per-case decision.
- Authorization is driven by `roles`/`role_id`, via `User::hasRole()` /
  `isSupervisor()` / `isInvestigator()` / `isAdmin()`. **`is_staff` still exists on `users`
  but nothing reads it for authorization** — it's only written by the factory/seeder and
  asserted in one auth test. Don't add new logic that branches on it; use roles.
- Two enforcement layers, both backend, never UI-only:
  - `EnsureUserHasRole` middleware, aliased `role` — coarse route gate,
    e.g. `role:Investigator,Supervisor` on the case group, `role:Supervisor` on `/workload`,
    `role:Admin` on `/admin/*`.
  - `CaseModelPolicy` — per-record decisions (`viewAny`, `view`, `create`, `update`,
    `delete`, `reassign`), wired via `authorizeResource()`.
  - List queries use `CaseModel::scopeVisibleTo($user)`, which mirrors `view()`.
- A page with no per-case decision to make (like `/workload`, `/admin/registrations`,
  `/admin/users`) is gated by middleware alone — don't invent a policy class for it.

## Registration approval and account management (built 2026-07-31)
Closes two findings from the 2026-07-30 `security-reviewer` run: self-registration landed
anyone as Investigator with no gate, and there was no way to disable a departing or
compromised account short of a direct database edit.

- **Registration.** `RegisterController` now lets a registrant pick Investigator or
  Supervisor (`Role::selectableRoleRule()` — `Rule::exists('roles','id')->whereIn(
  'role_name', Role::CASE_HANDLING)` — is the boundary that keeps Admin unreachable from
  this form; the same rule gates the account-management role-change screen). Every new
  account lands `registration_status = 'pending'` (also the column's own default, so a row
  inserted any other way still can't authenticate) and is logged straight back out —
  `RegisterController::registered()` undoes the login `RegistersUsers::register()` performs
  by default. `POST /register` carries `throttle:5,1`.
- **Login gate.** `LoginController::credentials()` adds `registration_status => approved`
  and `is_active => true` as query constraints (`EloquentUserProvider::retrieveByCredentials()`
  treats every non-password key that way), so a pending/rejected/deactivated account fails
  with the same generic "these credentials do not match" message a wrong password gets — no
  way to distinguish a disqualified username from one that doesn't exist. That only stops a
  *new* login; `EnsureAccountIsActive` (appended to the `web` middleware group globally, not
  a route group) ends an *already-authenticated* session the moment its account stops being
  approved and active, so a deactivation isn't outlived by a session started before it.
- **Login rate limiting (added 2026-08-19): inherited from the framework, not route
  middleware.** `LoginController` uses `AuthenticatesUsers`, which pulls in
  `ThrottlesLogins` — `login()` already checks `hasTooManyLoginAttempts()` before every
  attempt and calls `incrementLoginAttempts()` on failure, so `POST /login` needs no
  `throttle:` middleware of its own; adding one would just be a second, redundant limiter.
  Keyed `strtolower(username)|ip` (`ThrottlesLogins::throttleKey()`), so a lockout on one
  username never blocks a different one from the same client, and vice versa. Attempt cap
  stays at the trait default of 5 (matching registration's `throttle:5,1`); the decay
  window is widened from the trait's 60-second default to 15 minutes via
  `LoginController::$decayMinutes` — 60 seconds was too short to meaningfully slow a
  password-guessing script. A correct password before the cap clears the counter
  (`clearLoginAttempts()`), so mistyping twice and then succeeding isn't penalized.
  **`AuditLog::ACTION_LOGIN_LOCKOUT`** fires from an `Illuminate\Auth\Events\Lockout`
  listener in `AppServiceProvider::boot()` (same placement as the `Login` listener) —
  but only when the attempted username resolves to a real approved+active account.
  Same reasoning as "not audited: failed logins" below: an unknown, pending, rejected, or
  deactivated username resolves no user to attribute an entry to, so a lockout against one
  is silently not recorded — logging only the attributable sliver would look like lockout
  coverage without being it. `target_user_id` is always null on this action (a lockout has
  no target, only an account being guessed against).
- **Admin provisioning: `php artisan make:admin` only.** No route, no view — interactive
  prompt for username/name/password, same validation shape as registration. Registration
  can't reach Admin (`selectableRoleRule()`) and neither can the role-change screen
  (`UserAccountController::updateRole()` refuses an Admin *target*); this command is the
  entire provisioning surface. Needed one extra line in `bootstrap/app.php`
  (`->withCommands([...])`) — `withRouting(commands: 'routes/console.php')` only registers
  that one file as a command-route source, it does **not** auto-discover
  `app/Console/Commands` the way Laravel's default skeleton does.
- **Account management** (`UserAccountController`, `/admin/users`): activate/deactivate,
  role change (Investigator ↔ Supervisor only — never a destination *or* source, so an
  Admin row is never role-changeable), and admin-performed password reset (admin types the
  new password directly; relies on the model's `hashed` cast auto-hashing on `update()`, the
  same mechanism `RegisterController::create()`'s explicit `Hash::make()` is idempotent
  against). Every mutating action refuses to target the acting admin's own account
  (`abort_if($user->is($request->user()), 403)`) — no self-lockout, no self-promotion.
- **Deactivation is a soft flag, never deletion** — `audit_logs.user_id` is `NOT NULL` with
  no `onDelete` clause (RESTRICT by omission; unlike `audit_logs.case_id`, which is
  `nullOnDelete`), so a user with any audit history can't be hard-deleted regardless. Demonstrated
  live during this build: deleting a just-provisioned admin account failed at the database
  until its own audit rows were removed first.
- **New `AuditLog` constants**, same add-only convention as every other action: `ACTION_REGISTRATION_APPROVED`,
  `ACTION_REGISTRATION_REJECTED`, `ACTION_ACCOUNT_ACTIVATED`, `ACTION_ACCOUNT_DEACTIVATED`,
  `ACTION_ACCOUNT_ROLE_CHANGED`, `ACTION_ACCOUNT_PASSWORD_RESET` — all `case_id => null`
  (same precedent as the rating-change and export entries), each written inside the same
  `DB::transaction()` as its mutation. **Known limitation, inherited not introduced:**
  `audit_logs` has no target-user column, so these name the *admin who acted*, not who was
  acted on — the same open gap already logged against the rating-change entry. Distinct
  activated/deactivated constants at least keep the direction recoverable from the constant
  alone.
- **Open, not yet reconciled:** the vault (`wiki/project/admin-role-scoping.md`) has one
  password-reset decision still on the table between "admin performs the reset directly"
  (built) and "user requests, admin approves" — flagged back to JP to reconcile, not
  resolved by this build.

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
- Keep controllers thin. **All deadline/date math lives in `App\Services\CaseDeadlineService`**
  (built 2026-07-29 with the countdown alerts). It takes a `CaseTimeline` and returns values,
  with no Eloquent queries of its own, so it is unit-testable without a database.
  **Nothing outside that class may do date math on a `case_timelines` column** — this is
  enforced, not just advised: `.claude/hooks/guard-writes.php` fails any file under `app/`
  that names one of the seven timeline columns *and* calls `addDays`/`subDays`/`diffIn*`/
  `Carbon::`/`strtotime`. Anything needing a deadline asks the service for it; formatting an
  already-cast attribute with `->format()` is fine. Read `AlertController` or
  `ReportController` for the shape this produces.
- Every new feature that touches case data needs a role/policy check.
- Every state-changing action on case data should write an audit entry via
  `AuditLog::record($user, $case, $action, $target = null)` (`$case` may be null for actions
  not scoped to one case, like a rating change; `$target` is the user acted *on*, when there
  is one), called **inside** the action's `DB::transaction()`.
  **Every state-changing site now does this** (nine case-data sites closed 2026-07-30, six
  more added 2026-07-31 for registration/account actions, four more added 2026-08-01 for
  authentication/authorization events, one more added 2026-08-19 for the login lockout —
  twenty total, nineteen constants): intake
  (`ACTION_CREATE`), ordinary edits (`ACTION_EDIT`), Set Timeline
  (`ACTION_TIMELINE_UPDATE`), delete, reassign, performance-rating changes, all three
  closure steps (`ACTION_CLOSURE_PROPOSED` / `_CONFIRMED` / `_REJECTED`), registration
  approve/reject (`ACTION_REGISTRATION_APPROVED` / `_REJECTED`), account management
  (`ACTION_ACCOUNT_ACTIVATED` / `_DEACTIVATED` / `_ROLE_CHANGED` / `_PASSWORD_RESET`), a
  successful login (`ACTION_LOGIN`), a new account coming into existence
  (`ACTION_ACCOUNT_CREATED` — self-registration and `make:admin`), a denied request
  (`ACTION_ACCESS_DENIED`, see below), and a login lockout against a real account
  (`ACTION_LOGIN_LOCKOUT`, see below).
  **The constant carries the whole meaning** — `audit_logs` has no diff column and no
  field list, so an act the constant doesn't name is unrecoverable from the trail. Hence a
  distinct constant per closure step (a trail that can't tell a request from an approval
  can't say who asked for a case to be closed), and hence `ACTION_EDIT` rather than reusing
  `ACTION_UPDATE`. Note the historical split: **`ACTION_UPDATE` means a reassignment**
  (and, with a null `case_id`, a rating change) — it predates `ACTION_EDIT` and was left
  where it was rather than renamed. Don't collapse the two.
  One deliberate exception to "state-changing": `ACTION_EXPORTED` records a **read** — the
  CSV download at `/reports/export`, which takes case data out of the system. It has a null
  `case_id` (an export spans a filtered set) and no `DB::transaction()`, being a lone insert
  with nothing to roll back beside it. Viewing the same listing as a page is **not** audited;
  logging page views would bury the entries that record actual changes.
  Three sites are deliberately not a controller action: `ACTION_LOGIN` fires from an
  `Illuminate\Auth\Events\Login` listener in `AppServiceProvider::boot()`, not a
  `LoginController` hook, so a remember-me cookie resuming a session — which never touches
  the controller — is still audited; it's guarded to only fire when the account is approved
  and active, the same two flags `LoginController::credentials()` already gates login on, so
  a registration's own momentary self-login-then-logout and a deactivated account's
  remember-me cookie getting bounced by `EnsureAccountIsActive` don't get recorded as if they
  survived. `ACTION_ACCESS_DENIED` fires from an `$exceptions->render()` callback in
  `bootstrap/app.php`, catching both `CaseModelPolicy` denials (`AuthorizationException`) and
  `EnsureUserHasRole`/`abort_if()` denials (Symfony `HttpException`, filtered to status 403)
  in one place; it records only when `$request->user()` isn't null (a guest is redirected,
  never 403'd, and `audit_logs.user_id` is `NOT NULL`) and sets `case_id` when the denied
  route already resolved a `CaseModel`. **Not audited: failed logins.** Almost none are
  attributable — `LoginController::credentials()` adds `registration_status`/`is_active` as
  query constraints, so an unknown, pending, rejected, or deactivated username never resolves
  a user for `record()` to attribute an entry to, and there's no username column to fall back
  to. Logging only the attributable sliver (right username, wrong password) would look like
  failed-login coverage without being it. `ACTION_LOGIN_LOCKOUT` fires from an
  `Illuminate\Auth\Events\Lockout` listener, same placement as `ACTION_LOGIN`'s — the
  attempt cap is `ThrottlesLogins`, not route middleware, see the Login rate limiting note
  above — and the same attributability logic as failed logins applies: only fired when the
  attempted username resolves to a real approved+active account, silently skipped otherwise.
  `target_user_id` is always null (a lockout has no target, only an account being guessed
  against).
  **`target_user_id`** (nullable FK → `users.id`, `nullOnDelete` — descriptive, not the
  attribution the row exists for, so unlike `user_id` it isn't RESTRICT) closes the "who was
  acted on" gap flagged across three prior sessions: every account/registration/rating-change
  entry used to name only the actor. Populated at all seven of those sites plus the three new
  actor-is-target sites (self-registration, `make:admin`, and `users:rotate-password`, whose
  existing `--actor=` flag was already solving a different problem — an operator acting *on*
  someone else). **Still not populated:** the reassignment site (`ACTION_UPDATE` in
  `CaseController::reassign`) — its target is arguably the incoming investigator, but that
  row already carries a `case_id` that makes it independently readable, and reassignment
  itself is out of scope for the pass that added the column. One-line follow-up, not urgent.
- **`/admin/audit-logs`** (`AuditLogController`, `role:Admin` middleware only, no policy
  class — same convention as `/admin/registrations` and `/admin/users`) is the trail's first
  read path: filterable by acting user, action, and date range, paginated at the query level
  like `/reports` and `/workload` (`paginate(25)->withQueryString()`). Deliberately no link
  from a row's case to `reports.show` — Admin never opens, views, or edits a case, and that
  link would 403 the only role that can see the page; the docket number is shown as plain
  text instead.
- Share validation rules rather than duplicating them across requests — see
  `Role::assignableInvestigatorRule()` and `CaseModel::complexityWeightRules()`.

## Workflow notes
- **Design review split (decided 2026-08-05):** `/impeccable audit` is the WCAG 2.1 AA
  source of truth (contrast, ARIA, keyboard, semantic HTML, forms) — it's backed by a
  deterministic detector and scores accessibility as one of five fixed dimensions. The
  `ui-ux-reviewer` subagent no longer covers accessibility; it's scoped to usability,
  cross-view consistency, and workflow/information-architecture judgment calls that
  `/impeccable audit` doesn't make. Run both when reviewing a new or changed Blade view —
  they don't overlap. `PRODUCT.md` and `DESIGN.md` at the project root back the Impeccable
  skill; see them (and `.impeccable/config.json`, which registers `.blade.php` with the
  detector) before assuming a design decision isn't already written down. The per-edit and
  Stop-time Impeccable hook is on, layered with the existing `guard-writes.php` hook — both
  fire on `Edit`/`Write`, independently.
- Work in small, single-feature increments (one controller/feature/migration at a time).
- After generating code, run `php artisan test` before moving to the next task.
- **Changed anything under `resources/sass/` or `resources/js/`? Run `npm run build`.**
  `public/build` is gitignored, so compiled assets exist only where they were built, and a
  *stale* build fails silently — the page renders with the previous build's CSS and the change
  just doesn't appear. `tests/Feature/AssetsAreBuiltTest.php` fails when the build is missing
  or older than its source, so the suite catches it; that test skips itself while `npm run dev`
  is running. Blade, PHP and routes are read at request time and need no rebuild.
- Flag any assumption you make about a field or rule instead of guessing silently.
