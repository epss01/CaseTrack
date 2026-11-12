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

The `wiki/` portion of the vault is read-only for project work, not unless explicitly told to update it. If it is
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

The November 9, 2026 scope amendment permits retrospective K-means only in
the read-only Case profiles report. It groups authorized FIR submissions by
recorded complexity, party record count, and docket-to-FIR duration. Assignment,
statutory deadlines, case status, and legal decisions remain outside clustering.

- CaseTrack is a standalone internal investigation case assignment,
  monitoring, and reporting system. It is deterministic and rule-based; it is
  not an AI/chatbot system.
- Backend: PHP 8.4 and Laravel 11. The local XAMPP runtime is PHP 8.4.27 at
  `C:/xampp/php/php.exe` and is on `PATH`. Do not use an 8.4 release below
  8.4.27 because it is affected by CVE-2026-109618 and CVE-2026-109619.
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
  deterministic. Evaluate the 60-day RORP milestone only for confirmed torture
  cases. The approved prototype assumption uses docket date +90 calendar days
  for an externally granted +30-day extension; CaseTrack records the grant,
  never approves it. This assumption still requires CHR validation before adoption.
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
- After generating code, run the Laravel test suite using the patched PHP binary:

  ```text
  php artisan test
  ```

- After changes under `resources/sass/` or `resources/js/`, run `npm run build`.
  Blade, PHP, and route changes do not require an asset rebuild.
- For changed Blade views, check both accessibility with the Impeccable audit
  and usability/workflow with the UI/UX review process. Consult `PRODUCT.md`
  and `DESIGN.md` before making new design decisions.
- Flag assumptions about fields, rules, or unresolved product decisions rather
  than guessing silently.

## Adaptive Codex workflow

Use `.codex/README.md` for the walkthrough and validation limits. These rules
request delegation where indicated; do not spawn agents just to fill roles.

- The main agent coordinates, plans proportionally, implements, verifies, and
  integrates. Simple wording changes, isolated low-risk fixes, and minor
  refactors stay with the main agent. Respect "work solo" or disabled roles.
- For bounded features and nontrivial bugs, make a short plan. Request an
  independent `reviewer` for changes to application behavior or material risk.
  Use `researcher` only when a bounded investigation saves substantial reading.
- For authorization, schema, WCS/deadlines, broad architecture, or destructive
  operations, gather evidence and present concrete consequential decisions for
  approval before implementing those decisions. Existing authorization persists;
  routine implementation choices do not need repeated approval. After changes,
  request independent review. An ad-hoc `gpt-6-astra` agent is appropriate only
  for difficult architecture or unresolved substantive findings.
- Default models: main `gpt-6.1-sol`/medium; `researcher` `gpt-6-luna`/high;
  `reviewer` `gpt-6.1-sol`/high; `manuscript_writer` `gpt-6.1-sol`/medium.
  These are explicit model-selection instructions for delegated work. Leave
  the user's chosen main model alone during an active chat.
- Use at most two subagents concurrently, with no recursive delegation. Give
  each a bounded question, relevant paths, authority/conflicts, permitted edits,
  acceptance criteria, and expected output. Prefer fresh contexts for independent
  review and compact handoffs over copying the entire conversation.
- Use the named agent when supported. If the current client has no named-agent
  selector, read its `.codex/agents/<name>.toml` and pass its instructions and
  model explicitly to native delegation. With the collaboration tool, model
  overrides require `fork_turns="none"`; include project rules in the handoff.
  Report this fallback rather than claiming automatic agent-file discovery.
- Only the coordinator writes files by default. Specialists return findings or
  draft text. If implementation is explicitly delegated, transfer exclusive
  ownership of named files and wait before editing them again. Shared working
  directories and "read-only" instructions are not isolated sandboxes.
- Review a stable diff against requirements and actual code. Return concrete
  findings with file/line evidence, impact, and missing tests. Allow one review
  pass and one focused recheck; unresolved significant issues prevent a claim of
  completion. Do not silently downgrade an unavailable independent review to
  self-review. On delegation failure, continue safe independent work, disclose
  the failure, and use an available model or work serially.
- Run the checks required above before reporting completion; use the existing
  Pint check for changed PHP. Configuration/docs-only work needs configuration,
  link, and diff checks rather than the application test suite. Report exactly
  what ran, what passed, and what remains unverified. Never reuse old test counts
  as evidence for the current change.
- Use `manuscript_writer` for requested academic drafting or substantial
  revisions, not routine session summaries. Identify the current target/version
  before edits; the local July PDF is only a baseline. Provide evidence and
  unresolved claims. Preserve EVSU/APA requirements and the distinction between
  synthetic technical results and actual CHR evaluation. No fabricated sources,
  participants, results, deployment claims, or case-identifying examples.
- The researcher may prepare documentation facts; only the coordinator appends
  a session note when the user requests logging. Use Asia/Singapore dates and
  the existing note format. Keep wiki changes as proposals in that note. Do not
  add automatic session-writing hooks or edit a live manuscript without scope
  authorizing that document edit. Never put credentials or case data in notes.
- Do not commit, push, deploy, or change external services without authorization.
  Preserve Claude tooling, existing skills, hooks, and user changes. Reuse domain
  checklists only after checking for outdated assumptions.
- Known conflict: the closure instruction above describes ordinary-form closure,
  while current code and the wiki describe a dedicated closure endpoint. Flag
  this when relevant; do not change application behavior or rewrite either
  source silently. Historical wiki stack descriptions and Claude audit
  exceptions are not evidence of current implementation.
