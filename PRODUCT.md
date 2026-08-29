# Product

<!-- impeccable:product-schema 1 -->

## Platform

web

## Users

Three roles, all internal CHR Region VIII staff, no public/external users:
- **Investigator** — works their own assigned caseload only (intake, timelines, closure requests).
- **Supervisor** — office-wide view; assigns/reassigns cases, sets performance ratings, confirms or rejects closure requests.
- **Admin** — never touches case data; approves/rejects registrations and manages accounts (activate/deactivate, role change, password reset).

## Product Purpose

A web-based investigation case assignment, monitoring, and reporting system for a human rights commission field office. Tracks statutory legal deadlines (30/60/120-day milestones), suggests workload-balanced case assignment, and keeps an audit trail of who did what to which case. Success is an investigator/supervisor never missing a statutory deadline and always being able to reconstruct who changed what, when.

## Positioning

Deterministic, rule-based deadline and workload tracking — explicitly not an AI/chatbot system and not a legal decision-making tool. The two mechanisms a generic ticketing/case-management tool wouldn't have out of the box: the Workload Capacity Score (`WCS_i = Σ C_j × (2 − P_i)`, suggesting rather than assigning cases) and maker-checker closure (investigator proposes, supervisor confirms, with the case's prior status restored on rejection).

## Operating Context

Internal, non-public tool. Investigators/supervisors work from the case list and dashboard daily; timelines and closure requests are the two case actions with the most process weight. Admins work almost exclusively from `/admin/registrations`, `/admin/users`, and `/admin/audit-logs`, and never open a case. Deployed on-prem style (XAMPP/MySQL + Herd PHP for local dev); no email-based flows (username-only auth, no password reset via email).

## Capabilities and Constraints

- Statutory deadline math (30/60/120-day) is centralized in `CaseDeadlineService`; nothing else may do date math on `case_timelines` columns (enforced by a pre-commit-style guard hook).
- Case status has three named constants (Docketed, Pending Closure, Closed) but no ratified full status vocabulary — free-text values are in circulation.
- No case-type/category field exists yet, so the 60-day (torture-case-only) milestone can't be computed correctly — a known, flagged gap, not something to silently work around.
- Every state-changing action on case or account data must write an `AuditLog` entry inside the same transaction; the audit trail has no diff column, so the constant name is the only record of what happened.
- Two roles enforce access at the route level (middleware) and the record level (policy); a third role (Admin) is gated by middleware only since it has no per-record decisions to make.

## Brand Commitments

Internal government case-management tool, not consumer software. The existing palette deliberately moves away from Bootstrap's stock blue ("reads consumer-web") toward a deeper navy/blue pairing, sharper contrast-tested status colors, and softer card radii than Bootstrap defaults. See `_variables.scss` / `_casetrack.scss` — these are treated as binding until `/impeccable document` formalizes them into DESIGN.md.

## Evidence on Hand

No testimonials, case studies, marketing copy, or external proof — this is an internal tool with no persuasion surface. Do not fabricate any.

## Product Principles

1. WCS suggests; it never assigns — the supervisor always makes the final call.
2. No action on case data ships without a role/policy check and an audit log entry.
3. Deadline/date logic stays deterministic and centralized; no AI-driven or ad hoc date math.
4. Cases are soft-deleted, never hard-deleted, so the audit trail is never left dangling.
5. UI density and clarity outrank visual flourish — this is an Operate surface for people reading fast, sometimes under deadline pressure, not a persuasion surface.

## Accessibility & Inclusion

WCAG 2.1 AA is an established, actively enforced standard for this project (existing contrast-ratio math in `_variables.scss`, e.g. the amber/red status colors were deliberately darkened after measuring against gamma variance). `/impeccable audit` is the a11y source of truth (decided 2026-08-05); the `ui-ux-reviewer` subagent covers usability/consistency/workflow instead.
