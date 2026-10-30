---
name: CaseTrack
description: Web-based investigation case assignment, monitoring, and reporting system for CHR Region VIII
colors:
  primary: "#1d4ed8"
  casetrack-navy: "#102f4f"
  casetrack-navy-light: "#1a4470"
  casetrack-gold: "#f5cc29"
  body-bg: "#f8fafc"
  card-border: "#e6ecf3"
  table-border: "#eef2f7"
  status-blue-text: "#1e40af"
  status-blue-bg: "#dbeafe"
  status-amber-text: "#92400e"
  status-amber-bg: "#fef3c7"
  status-slate-text: "#475569"
  status-slate-bg: "#e9eef5"
  status-red-text: "#991b1b"
  status-red-bg: "#fee2e2"
  on-navy: "#ffffff"
  on-navy-secondary: "rgba(255, 255, 255, 0.72)"
  on-navy-divider: "rgba(255, 255, 255, 0.15)"
  on-navy-chip-border: "rgba(255, 255, 255, 0.3)"
  on-navy-tile-bg: "rgba(255, 255, 255, 0.12)"
  on-navy-avatar-bg: "rgba(255, 255, 255, 0.16)"
  on-navy-avatar-border: "rgba(255, 255, 255, 0.25)"
  on-navy-icon: "rgba(255, 255, 255, 0.85)"
  weight-meter-track: "#cbd5e1"
typography:
  body:
    fontFamily: "Source Sans 3, Segoe UI, sans-serif"
    fontSize: "1rem"
    lineHeight: 1.6
  headings:
    fontFamily: "Manrope, Segoe UI, sans-serif"
  scale:
    page-title: "1.75rem"
    section-heading: "1.25rem"
    subsection: "1.125rem"
    minor-heading: "1.0625rem"
    body: "1rem"
    supporting: "0.875rem"
    navigation-control: "1rem"
    analytics-value: "2rem"
    headline: "1.35rem"
    subtitle: "0.95rem"
rounded:
  sm: "0.5rem"
  md: "0.625rem"
  lg: "0.875rem"
  pill: "999px"
  hairline: "1px"
---

# Design System: CaseTrack

## Overview

### Approved workspace refinement — 28 October 2026

Use a labeled navy topbar with a gold CT badge and CaseTrack wordmark, neutral work
surfaces, and blue actions. The CHR logo remains deferred. Gold is a brand accent;
amber remains reserved for deadline meaning. The topbar collapses to a labeled
Menu at narrow widths, with identity, role and Logout in the far-right dropdown.

Investigators see Dashboard, Cases, Alerts and Reports. Supervisors also see
Workload. Both roles have Import cases in the Cases header. Investigator imports
default blank assignees to self and reject another named assignee. Admins see
Dashboard, Registrations, Accounts and Audit Log, without case destinations.
Their dashboard shows account totals and the five oldest pending registrations. Reports has
visible Case reports / Analytics local links.

Case details use inline Details, People and Timeline sections with jump links.
Closing/reopening is a dedicated assigned-Investigator action with confirmation;
reopening requires choosing one of the three active statuses. Ordinary editing
cannot cross the Closed boundary. Keep all existing people and timeline fields.

Analytics foregrounds received, FIR-date resolutions and period-end backlog.
The received/resolved bars share a zero-based scale; backlog is separate.
Metric values use 2rem and the Menu control uses 1rem. Historical trends and
new case-list filters are deferred. Keep the existing Bootstrap/Sass stack.

**Creative North Star: "The Case File Desk"**

CaseTrack is an Operate surface: investigators and supervisors reading fast, sometimes against a statutory clock, and admins doing narrow account-management tasks. Every visual decision so far has favored scanability and correctness over expression — a deeper navy/blue replacing Bootstrap's stock blue because the default "reads consumer-web" for a Commission case-management tool, and status colors chosen and re-measured for contrast rather than picked for looks. This is not a greenfield system waiting for a personality; it already has one, documented as inline rationale rather than a formal spec until now.

**Key Characteristics:**
- Deliberately restrained: no gradients, no illustration, no marketing-style hero moments — this is a work tool.
- Accessibility-led: several color choices in this file exist because a prior choice measured under WCAG 2.1 AA contrast in a specific real condition (small bold text, narrow viewport, gamma variance) and was corrected.
- Density with room to breathe: Bootstrap's default type scale and spacing were kept mostly as-is; the departures (radius, primary blue, table width) are targeted, not a full re-skin.

## Colors

Two families: one for chrome/brand (navy, primary blue), one for status meaning (blue/amber/slate/red), plus a tinted neutral background instead of white.

### Primary
- **CaseTrack Blue** (`#1d4ed8`): links, primary buttons, active nav state, weight-meter fill. Deliberately deeper than Bootstrap's default `#0d6efd`.

### Neutral
- **Page Wash** (`#f8fafc`): body background — enough tint that cards need a shadow, not just a border, to read as raised.
- **Card Border** (`#e6ecf3`) / **Table Border** (`#eef2f7`): hairline dividers, one shade apart so tables feel lighter than cards.
- **Commission Navy** (`#102f4f`) → **Navy Light** (`#1a4470`): the identity-bar gradient on dashboards — the one place the brand asserts itself with weight.

### On Navy
White at a fixed set of opacity steps, each tied to a role rather than picked per-component — this is a deliberate ramp, not eight ad hoc values:
- **on-navy** (`#fff`, 100%): the identity-bar name and stat-tile values — the only full-opacity white.
- **on-navy-icon** (85%): stat-tile icons.
- **on-navy-secondary** (72%): eyebrow labels, meta text, stat-tile labels — reused across four components, the most common step after 100%.
- **on-navy-chip-border** (30%) / **on-navy-avatar-border** (25%): borders on the role pill and avatar circle, close enough to read as one "outline on dark" family.
- **on-navy-avatar-bg** (16%) / **on-navy-tile-bg** (12%): fills for the avatar circle and stat-tile icon squares.
- **on-navy-divider** (15%): the rule between the identity block and the stat tiles.

### Named Rules
**The Status-Color Rule.** Status meaning is carried by soft tint pairs, never by a solid Bootstrap `.text-bg-*` badge — those "shout" next to the navy identity bar. Active case states use blue and Closed uses slate. Amber remains available for due-soon alert/report states, while red denotes overdue or danger conditions.

## Typography

**Fonts:** locally bundled Source Sans 3 for body text, controls and tables;
Manrope for headings and the wordmark. Font files and their OFL licenses live
under resources/fonts. No external font requests are needed.

- Page titles: 1.75rem (28px), weight 700.
- Section headings: 1.25rem (20px), weight 600.
- Body and controls: 1rem (16px), weight 400; labels use 600.
- Supporting text: 0.875rem (14px).
- Metrics: 2rem, weight 700, with tabular numerals.

### Compact forms

Analytics, Reports, Audit Log and Cases search use 12px padding and 8px gaps.
Labels remain visible; fields and actions share a row where space permits and
wrap on narrow screens. No separate Filter header band. The Accounts role
selector is 10rem wide with a nonshrinking Save button in the wide table layout.

### Analytics explanations

Keep descriptions short and expand FIR as Final Investigation Report. Keep the
distinction between FIR submission and Closed status visible, alongside relevant
data-quality notices. Detailed formulas use a native expandable section. Undefined
rates say Not available with a reason. Calculations and chart scales are unchanged.

## Layout

Two page widths coexist deliberately: `.container` (Bootstrap default, capped ~960–1140px) for narrow forms — auth screens, single-record views — and `.container-wide` (capped 1400px, `padding-inline: 2rem`) for table-first pages (dashboard, cases, reports, alerts, workload). The wide cap exists because `.container-fluid` alone let case tables stretch until row-scanning broke down on large monitors; it is a ceiling, not full-bleed, and it is padded so the card never touches the viewport edge below the cap.

Data tables additionally: mark only docket numbers, dates, and badges `.text-nowrap` (never the whole row), let `overflow-wrap: anywhere` catch unbreakable strings like docket numbers, and give the trailing action column `width: 1%` so it never absorbs the squeeze that a long case title should take instead.

## Elevation & Depth

Flat by default, with a single low, ambient card shadow (`box-shadow: 0 1px 2px rgba(15, 23, 42, 0.04)`) rather than a visible border alone — necessary specifically because cards sit on the tinted `#f8fafc` wash, where a hairline border reads flat. No hover-elevation, no layered shadow scale: this system has one elevation step, not a ramp.

### Named Rules
**The One-Shadow Rule.** There is exactly one shadow value in the system. A second, heavier shadow would compete with the identity-bar gradient as the page's one moment of visual weight.

## Shapes

Radius is bumped up from Bootstrap's default `0.375rem` across the board — `sm: 0.5rem`, base `0.625rem`, `lg: 0.875rem` (identity bar, larger surfaces) — because the stock radius "looks sharper than the softer cards the interface is modelled on." Status tags and the role pill go further, to a full pill (`rounded.pill: 999px`), the one fully-round shape in the system, reserved for small inline metadata rather than containers. One deliberate exception below the scale: the weight-meter's segments use a near-square `hairline: 1px` radius — at 0.3rem wide, anything on the `sm` step or above would just look circular, so this is a one-off, not a smaller step in the scale.

## Components

### Buttons
Bootstrap defaults (`.btn-primary`, `.btn-outline-*`) with the deeper primary blue swapped in; no custom button component exists. State-changing actions use real `<form method="POST">` submit buttons rather than link-styled anchors, so a JS failure degrades to a working form instead of a 405.

### Navbar
Navy topbar with labeled links and a 2rem (32px) rounded gold CT badge with navy
letters beside CaseTrack. Active links use blue plus an underline. A labeled Menu
control replaces the expanded links on narrow screens. Navigation is role-gated
in Blade and mirrors backend authorization.

### Identity Bar
The dashboard's signature component: navy gradient card, bold page title above the account name, a role pill, an avatar built from initials (no avatar-photo column exists or is planned), and inline stat tiles. Heading order deliberately leads with the page's purpose and follows with whose data it is — "Dashboard" first, name second — not the reverse.

### Stat Tiles
Icon-in-a-translucent-square + label/value pair, used inside the identity bar. Supporting text sits at `rgba(255,255,255,0.72)` opacity. The investigator and supervisor dashboards each use three balanced tiles, stacking at narrow widths and switching to three columns from the medium breakpoint.

### Status Tags
Soft-tint pill badges (see Colors → Named Rules), never Bootstrap's solid `.text-bg-*` badges. Deadline and report rows may use the shared `.row-pending` amber tint; case tables do not add a closure-specific row flag.

### Data Tables
Horizontally-scrolling wrapper, one shared `.table-data` treatment across every case-list-style table (dashboard, cases, reports, alerts, workload). See Layout for the column-width rules. A combined deadline-and-submission fact renders in one cell (`.cell-sub` for the secondary line) rather than two columns, since splitting them cost width to re-say one fact.

### Weight Meter
A 5-segment bar (`.weight-meter`) replacing a bare digit for `complexity_weight` (1–5): filled segments in primary blue, unfilled in `weight-meter-track` (`#cbd5e1`) — chosen because a fainter grey made unfilled segments disappear against the row background.

## Do's and Don'ts

### Do:
- **Do** use the soft-tint badge pairs for any new status/meaning color; measure new pairs against WCAG 2.1 AA before adding them (existing pairs range 6.4:1–7.15:1).
- **Do** use `.container-wide` for any new table-first or dashboard-style page; keep `.container` for forms and single-record views.
- **Do** gate navigation and UI affordances by role in Blade (`@can`/`@if`), matching the existing pattern, rather than hiding them client-side.
- **Do** use real `<form method="POST">` submit controls for any state-changing action, never a styled anchor.

### Don't:
- **Don't** introduce a second shadow value, a hover-elevation effect, or any glow/gradient-text treatment — this system is deliberately flat and restrained (Operate mode, not Persuade).
- **Don't** use amber for active case status; reserve it for due-soon alert/report states and use red for overdue or danger conditions.
- **Don't** revert to Bootstrap's stock `#0d6efd` blue or default `0.375rem` radius; both were deliberately overridden and reverting reads as "consumer-web" against this product's brief.
- **Don't** add fonts beyond the approved Manrope / Source Sans 3 pairing, or decorative hero treatments.
