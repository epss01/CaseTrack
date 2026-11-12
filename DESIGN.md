---
name: CaseTrack
description: Institutional workspace for internal CHR Region VIII case management
colors:
  primary: "#1e3a5f"
  secondary: "#2c5282"
  casetrack-navy: "#1e3a5f"
  casetrack-gold: "#f5cc29"
  body-bg: "#f7f8fa"
  text: "#2d3748"
  muted: "#596579"
typography:
  body:
    fontFamily: "Source Sans 3, Segoe UI, sans-serif"
    fontSize: "1rem"
    lineHeight: 1.6
  headings:
    fontFamily: "Manrope, Segoe UI, sans-serif"
rounded:
  control: "0.25rem"
  panel: "0.5rem"
---

# CaseTrack design system

## Approved institutional redesign — 11 November 2026

CaseTrack is an Operate surface for authorized internal staff. Prioritize readable
records, discoverable actions, predictable forms, and deadline meaning.

Visual references: Claude's supplied **CaseTrack-design-review.pptx**, particularly
slide 12 / PDF option 2a for authentication, and **CaseTrack institutional redesign.pdf**.
Adapt flat surfaces, restrained colour and clear hierarchy from
[SAP DESIGN.md](https://github.com/VoltAgent/awesome-design-md/blob/main/design-md/sap/DESIGN.md)
in VoltAgent's awesome-design-md collection (MIT). This is an adaptation, not SAP
branding; no proprietary SAP typeface or assets are used.

The supplied reference describes older product behavior. Current application
routes, policies and services govern. Retain four statuses (Docketed, Under
investigation, For review, Closed), direct assigned-investigator closure/reopening,
registration pending administrator approval, username authentication, and
administrator password resets. Do not introduce closure-approval queues, email
reset links, hypothetical case data or mockup-only metrics.

## Tokens and typography

Use navy #1e3a5f for chrome and primary actions, secondary blue #2c5282 for links,
canvas #f7f8fa, slate text #2d3748, and white work panels. Gold #f5cc29 is the CT
identity accent. Status colours retain the existing contrast-tested pairs:
blue #1e40af / #dbeafe, amber #92400e / #fef3c7, red #991b1b / #fee2e2,
and slate #475569 / #e9eef5. Amber means due soon; red means overdue or danger.
Muted text is #596579; input boundaries are #8896a8.

Keep locally bundled Manrope headings and Source Sans 3 body, table and control
text, with Segoe UI/sans-serif fallbacks. No external font requests.
Body and controls are 16px; table/supporting text is 14px; page headings 28px;
section headings 20px. Use tabular numerals for tables and metrics.
Controls have 4px corners; panels 8px. Use subtle borders and flat fills without
decorative gradients or card elevation. Tables use 12px cell padding.

Source tokens live in resources/sass/_variables.scss. Shared components live in
resources/sass/_casetrack.scss, imported after Bootstrap. Reuse existing Blade
partials for icons, status/deadline badges, complexity meters and role-gated controls.

## Authentication

A dedicated guest layout uses 45% navy identity / 55% light form at 992px and
above, with a 400px form. At narrower widths, show a compact brand header and
single-column form. Registration uses the same layout and may scroll naturally.
Preserve password-manager autocomplete, password paste, password visibility,
remember-me, old username, actual validation/lockout messages and registration.
Explain shared-workstation remember-me use and administrator password resets.
Never show invented remaining attempts or lockout durations.

## Workspace and case workflow

Use labeled navy top navigation, gold CT mark, role-specific destinations,
far-right identity/role/logout, and a labeled Menu below the expanded breakpoint.
Investigators see Dashboard, Cases, Alerts and Reports; supervisors also see
Workload. Admins see Dashboard, Registrations, Accounts and Audit Log only.
Import remains contextual to Cases. Backend middleware and policies enforce access.

Dashboard and Alerts banners use a navy identity header with white text and
contrast-safe actions above a white count strip. Highlight active caseload in
slate, overdue in red and due soon in amber using the existing status pairs.
Keep light-outline controls within the navy header. Maintain existing metrics.
Use container-wide (1400px cap, responsive side padding) for tables/dashboards
and a centered container for forms. Filters retain visible labels and wrap.

Case details use inline Details, People and Timeline jump links. On screens
1200px and wider, place details/people beside the timeline; stack them below.
Retain every party and timeline field, supervisor edit reasons, optimistic locking,
and dedicated assigned-investigator closure confirmation. Intake/edit use named
field groups. Do not calculate deadlines in views or browser code.

Deadline badges combine visible text with decorative SVG: circle On track,
triangle Due soon, square Overdue, check-circle Submitted. Case-status labels
remain separate from deadline states. Show the 60-day RORP deadline only for
confirmed torture cases, with a clear caveat for unknown classification. The
timeline editor records externally granted extensions separately from initial-ROP
requests and explains the prototype 90-days-from-docketing assumption. Show the
effective due date and grant status without suggesting CaseTrack approves grants.
Charts and reports preserve existing
server-calculated values and accessible textual summaries.

## Accessibility and verification

Analytics leads with a navy Clearance Rate panel and a secondary Disposition Time
panel, followed by supporting period counts. Retain zero-based received/resolved
bars. A two-slice pie shows resolved-during-period versus pending-at-period-end
counts, with an explicit denominator and visible count/percentage legend. Exclude
earlier resolutions and show an empty message instead of a zero-total pie.
Clearance Rate can exceed 100% and must never be represented as a pie or capped.

Target WCAG 2.1 AA: 4.5:1 normal text, 3:1 large text/control boundaries, visible
keyboard focus, logical headings and explicit labels. Icons beside labels are
aria-hidden. Maintain native POST forms, error messages and server validation.
Keep controls at least 44px tall; preserve horizontal scrolling within table
regions rather than across the page. Reduced-motion preferences remain respected
by Bootstrap; no decorative motion is introduced.

For each UI increment, inspect real Laravel pages with synthetic records at
1440px, 1024px and 375px, including keyboard focus, validation recovery, modal
focus, zoom/reflow and role-specific navigation. Run Impeccable accessibility
checks and a separate usability review. Compare rendered pages with the approved
reference; capture implementation into Figma when available rather than treating
a disconnected mockup as proof of fidelity.

Preserve the Laravel/Blade/Bootstrap/Sass/Vite stack. Dark mode, new filters,
deadline previews, timeline visualizations and a framework migration are outside
this redesign. Schema, authorization, auditing, WCS and statutory arithmetic do
not change. The AGENTS.md ordinary-form closure instruction conflicts with current
code/wiki; preserve the implemented dedicated closure endpoint and flag the conflict.
