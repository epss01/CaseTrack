---
name: deadline-milestone
description: Compute or display the statutory 30/60/120-day case deadlines and their countdown alerts off case_timelines. Use when building due-date alerts, overdue flags, dashboard deadline counts, or any date math on a case timeline field.
---

# Statutory deadline milestones

Deterministic, rule-based date arithmetic. **No AI, no heuristics, no
"approximately"** — this is a delimitation of the project, not a preference.
Every rule below is a fixed offset from a stored date.

## Read this first: the 60-day rule is blocked

The **60th-day milestone applies only to torture cases**, and there is no
case-type or category field anywhere in the schema — not in the manuscript's
design, not in the migrations.

**Do not guess a workaround.** Do not infer case type from `case_title`,
`incident_details`, or `source_info` — text inference is exactly the
NLP/text-mining this project excludes. Do not silently apply the 60-day rule
to every case.

When a task requires the 60-day classification, stop and report:

> The 60-day milestone needs a case-type field that doesn't exist in the
> schema. Options: (a) add a `cases.case_type` column via migration, (b) scope
> this work to the 30- and 120-day milestones only. Which do you want?

`DemoDataSeeder::bucketFor()` is the existing precedent — it deliberately
excludes the 60-day column for this reason. Follow it; don't "fix" it.

## Where the code goes

**All of it goes in `App\Services\CaseDeadlineService`.** That class does not
exist yet — `app/Services/` has not been created. Creating it is step one of
the first task that needs it.

Nothing outside that class may do date math on a `case_timelines` field. The
`PostToolUse` hook in `.claude/settings.json` flags violations once the class
exists. Controllers call the service; Blade views render what it returns.

Keep the service free of Eloquent queries where practical — take a
`CaseTimeline` (or plain dates) and return values. That is what makes it
unit-testable, which is the whole reason it exists.

## The source fields

All on `case_timelines`, one row per case (`case_id` is unique):

| Column | Nullable | Meaning |
|---|---|---|
| `date_of_docket` | no | Captured at intake. **The anchor every milestone counts from.** |
| `date_submission_rop` | yes | Report of Proceedings submitted |
| `extension_30_days` | yes | 30-day extension granted |
| `submission_60th_day` | yes | 60th-day submission (torture cases — see blocker) |
| `submission_120th_day` | yes | 120th-day submission |
| `target_date_fir` | yes | Target for the Final Investigation Report |
| `date_fir_submitted` | yes | FIR actually submitted |

Seven of the eight are nullable and are filled in later via
`CaseTimelineController`. **A null is not zero and not "today"** — it means the
milestone has not happened. Guard every one.

A case docketed before the timeline feature landed may have no
`case_timelines` row at all (`CaseTimelineController` uses `updateOrCreate`
for exactly this). Handle a null `$case->timeline` without throwing.

## The rules

Deadline = `date_of_docket` + N days, where N is 30, 60, or 120.

Status of a milestone, in this order:

1. **Submitted** — the milestone's own date column is non-null. Done; no
   countdown, no alert.
2. **Overdue** — deadline is before today.
3. **Due soon** — deadline is within the warning window of today.
4. **On track** — anything else.

Use Carbon's whole-day comparison (`startOfDay()`), not raw timestamps — a
case docketed at 4pm is not half a day less overdue than one docketed at 9am.
Count calendar days; there is no business-day or holiday rule specified. If a
task implies one, flag it rather than inventing a holiday calendar.

Put the warning-window length and the milestone offsets in named constants on
the service, not scattered as literals.

## Anything not specified above

The countdown-alert feature has one placeholder in the repo
(`DemoDataSeeder::bucketFor()`) and no real implementation. If a task needs a
rule this file does not state — a business-day calendar, what an extension
does to the 120-day clock, whether `target_date_fir` overrides the computed
deadline — **ask rather than decide**. Check `wiki/project/database-schema.md`
in the vault first; if it's silent there too, that's a real open question and
worth surfacing.

## Tests

Add `tests/Unit/CaseDeadlineServiceTest.php`. Unit, not Feature — no HTTP, no
database. Cover, at minimum:

- Each milestone's boundary: the day before, the day of, the day after.
- A submitted milestone reports Submitted regardless of how overdue it is.
- Null milestone columns don't throw.
- A case with no timeline row at all doesn't throw.

Freeze time (`$this->travelTo(...)`) — a test that passes only on the day it
was written is worse than no test.
