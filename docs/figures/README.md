# Manuscript workflow figures

Stage 3 revision, 12 November 2026. SVG files are editable vector sources;
matching PNGs are manuscript insertion assets. `generate.py` reproduces both
using the installed Pillow library and Windows Arial fonts. No application or
database access is performed.

| Figure | Editable source | Manuscript asset |
| --- | --- | --- |
| 3.4 System context | [SVG](3-4-context.svg) | [PNG](3-4-context.png) |
| 3.5 Data flow | [SVG](3-5-data-flow.svg) | [PNG](3-5-data-flow.png) |
| 3.6 Use cases | [SVG](3-6-use-case.svg) | [PNG](3-6-use-case.png) |
| 3.7 Protected timeline save | [SVG](3-7-activity.svg) | [PNG](3-7-activity.png) |
| 3.8 Relational model | [SVG](3-8-erd.svg) | [PNG](3-8-erd.png) |

The figures describe the current prototype. External extension approval is
outside CaseTrack; recording a decision is an authorized case-data change.
The +90 calendar days from docket formula remains provisional. Unknown cases
receive no 60-day evaluation. The ERD groups fields for readability and does not
depict Laravel infrastructure tables. Its identifiers follow current migrations;
it replaces the obsolete `status_before_closure` field with classification and
version fields and includes the actual-date and extension columns.

The dedicated assigned-Investigator closure endpoint is depicted as implemented.
The conflicting ordinary-form closure wording in AGENTS.md is still flagged;
no application behavior was changed to reconcile it.

Regenerate from the repository root with a Python runtime containing Pillow:

```text
python docs/figures/generate.py
```

Read [current evidence and limitations](../TORTURE_DEADLINE_STATUS.md). The
figures are technical descriptions, not proof of CHR approval or acceptance.
