---
name: CaseTrack
description: Web-based investigation case assignment, monitoring, and reporting system for CHR Region VIII
colors:
  primary: "#1d4ed8"
  casetrack-navy: "#102f4f"
  casetrack-navy-light: "#1a4470"
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
    fontFamily: "Nunito, sans-serif"
    fontSize: "0.9rem"
    lineHeight: 1.6
  scale:
    note: "0.68rem"
    label: "0.72rem"
    meta: "0.78rem"
    caption: "0.8rem"
    subtitle: "0.95rem"
    headline: "1.35rem"
    display-on-navy: "1.5rem"
rounded:
  sm: "0.5rem"
  md: "0.625rem"
  lg: "0.875rem"
  pill: "999px"
  hairline: "1px"
---

# Design System: CaseTrack

## Overview

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
**The Status-Color Rule.** Status meaning is carried entirely by four soft tint pairs (blue / amber / slate / red), never by a solid Bootstrap `.text-bg-*` badge — those "shout" next to the navy identity bar. Amber means "waiting on someone else" (Pending Closure); it is not reused for "Due soon" on the deadline-alerts page, which gets its own red so two different urgency signals never collide on the same color.

## Typography

**Body Font:** Nunito, sans-serif (via Bunny Fonts)

**Character:** A rounder, friendlier sans than Bootstrap's system-font default, at a slightly smaller base size (0.9rem) with generous line-height (1.6) — built for scanning rows of case data, not for display type. There is no separate display/heading font; hierarchy comes from Bootstrap's heading weights plus the identity-bar treatment, not a second typeface.

### Supporting Sizes
Below body size, a small scale of one-off steps rather than named heading levels — each tied to exactly one component, not a reusable ramp:
- **display-on-navy** (1.5rem, 700): the identity-bar name — the largest text in the product.
- **headline** (1.35rem, 700): the stat-tile value.
- **subtitle** (0.95rem, 700): avatar initials.
- **caption** (0.8rem): role-pill text, the identity-bar meta line.
- **meta** (0.78rem): `.cell-sub`, the secondary line in a combined table cell.
- **label** (0.72rem, uppercase, tracked): eyebrow labels, stat-tile labels — the most-reused step in this scale.
- **note** (0.68rem): the stat-tile note line, sized down instead of lightened so it still clears contrast at the narrowest viewport (see the identity bar's `.stat-tile__note` in `_casetrack.scss`).

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
Bootstrap defaults (`.btn-primary`, `.btn-outline-*`) with the deeper primary blue swapped in; no custom button component exists. State-changing forms (logout, closure actions) use real `<form method="POST">` submit buttons rather than link-styled anchors, so a JS failure degrades to a working form instead of a 405.

### Navbar
White background, subtle shadow, brand mark is a 1.75rem rounded square with a navy→blue gradient and the "CT" initials rather than a logo image. Active nav link gets a tinted background (`rgba(primary, 0.08)`) plus the link's own color — Bootstrap ships no active-state treatment at all, so this was added deliberately. Nav items are role-gated inline (Blade `@if`/`@can`), not hidden by CSS — an unauthorized link never renders rather than being hidden-but-present.

### Identity Bar
The dashboard's signature component: navy gradient card, eyebrow label ("INVESTIGATOR DASHBOARD" style) above a large name, a role pill, an avatar built from initials (no avatar-photo column exists or is planned), and inline stat tiles. Heading order deliberately leads with the page's purpose and follows with whose data it is — "Dashboard" first, name second — not the reverse.

### Stat Tiles
Icon-in-a-translucent-square + label/value pair, used inside the identity bar. Supporting text sits at `rgba(255,255,255,0.72)` opacity except where a specific note (e.g., "N awaiting approval") needed to clear a measured 4.5:1 contrast floor at the narrowest viewport — there font-size carries the hierarchy instead of opacity.

### Status Tags
Soft-tint pill badges (see Colors → Named Rules), never Bootstrap's solid `.text-bg-*` badges. A pending-approval table row additionally gets a 3px amber left-border accent layered on top of Bootstrap's own `.table-warning` striping (must be applied via CSS variable override, not a competing box-shadow, or it silently loses to striping's higher specificity).

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
- **Don't** reuse amber for a second meaning on the same page as an existing amber signal (see the Status-Color Rule) — deadline-alerts' "Overdue" needed its own red specifically to avoid this collision.
- **Don't** revert to Bootstrap's stock `#0d6efd` blue or default `0.375rem` radius; both were deliberately overridden and reverting reads as "consumer-web" against this product's brief.
- **Don't** add a display/heading font distinct from Nunito, or a decorative illustration/hero treatment — no such elements exist anywhere in the product today.
