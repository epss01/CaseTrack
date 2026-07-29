---
name: deadline-milestone
description: Compute or display the statutory 30/60/120-day case deadlines and their countdown alerts off case_timelines. Use when building due-date alerts, overdue flags, dashboard deadline counts, or any date math on a case timeline field.
---

# Statutory deadline milestones

Deterministic, rule-based date arithmetic. **No AI, no heuristics, no
"approximately"** — this is a delimitation of the project, not a preference.
Every rule below reads a stored date or applies a fixed offset to one; nothing
here infers, estimates, or rounds.

## Read this first: the 60-day rule is blocked, and already gated

The **60th-day milestone applies only to torture cases**, and it is blocked on
**two** missing fields, not one:

1. There is no case-type or category field anywhere in the schema — not in the
   manuscript's design, not in the migrations. Nothing can say which cases the
   rule binds.
2. **No column records that a 60th-day report was filed.** `date_submission_rop`
   and `date_fir_submitted` are the only actuals in the schema, and neither is
   the RORP. So even once a case type exists, the milestone could report
   overdue, due soon or on track and **never Submitted** — only the FIR could
   discharge it.

**Do not guess a workaround.** Do not infer case type from `case_title`,
`incident_details`, or `source_info` — text inference is exactly the
NLP/text-mining this project excludes. Do not silently apply the 60-day rule
to every case.

**This is already handled — don't rebuild it.** `CaseDeadlineService::SIXTY_DAY_ENABLED`
is `false`, and `visibleMilestones()` filters the milestone out of everything
`statusesFor()` returns, which is the only route into a controller or a view.
The 60-day offset is still *computed* and still *seeded*; only the surfacing is
gated. There is deliberately **no** second entry point that returns the gated
milestone anyway — don't add a `$includeGated` flag or an `allMilestones()`
sibling to make a test see it, because that is the exact door the gate exists to
close. `deadlineOn($docket, 60)` covers the arithmetic with no backdoor.

Flipping the flag needs **both** gaps closed and the milestone scoped to the case
types it actually binds. If a task asks for the 60-day, say so rather than
flipping it — and note that both fields should go to CHR together, since adding
only the case type produces a milestone that can go overdue but never be
satisfied. See `wiki/project/chr-open-questions.md` item 3.

## Where the code goes

**All of it goes in `App\Services\CaseDeadlineService`.** The class exists
(built 2026-07-29 with the countdown alerts) — extend it rather than starting
anywhere else.

Nothing outside that class may do date math on a `case_timelines` field, and
this is **enforced, not advised**: `.claude/hooks/guard-writes.php` is a
`PostToolUse` hook that fails any file under `app/` other than the service
itself which names one of the seven timeline columns *and* calls
`addDays`/`subDays`/`diffIn*`/`Carbon::`/`strtotime`. A controller reaching for
Carbon is stopped by a failing hook, not caught in review. Write the controller
and the view with no date math from the outset; do not plan to refactor into
compliance afterwards.

Formatting an already-cast attribute with `->format()` is fine — the rule is
about arithmetic, not display.

Controllers call the service; Blade views render what it returns.
`AlertController` and `ReportController` are the worked examples — read either
for the shape this produces.

The service holds no Eloquent queries: it takes a `CaseTimeline` (or plain
dates) and returns values. That is what makes it unit-testable without a
database, which is the whole reason it exists. Keep it that way.

## The source fields

All on `case_timelines`, one row per case (`case_id` is unique).

**Read the roles, not the names.** Several of these columns are named as though
they record something that happened; most of them record a *date something is
due*. Getting this backwards is the single easiest way to write a deadline
feature that reports nonsense.

| Column | Nullable | Role | Meaning |
|---|---|---|---|
| `date_of_docket` | no | **anchor** | Captured at intake. Every computed milestone counts from this. |
| `extension_30_days` | yes | **deadline** | The 30-day deadline. **Not** "an extension that was granted" — despite the name, and despite the CHR sheet's column header reading that way. |
| `submission_60th_day` | yes | **deadline** | The 60-day deadline (torture cases — see blocker above). |
| `submission_120th_day` | yes | **deadline** | The 120-day deadline. |
| `date_submission_rop` | yes | **actual** | Report of Proceedings actually submitted. Discharges the 30-day. |
| `date_fir_submitted` | yes | **actual** | Final Investigation Report actually submitted. Discharges everything. |
| `target_date_fir` | yes | target | The office's own target, `date_of_docket + 100`. **Not statutory** — missing it is "behind target", not "late", so it raises no alert. |
| `date_submitted_to` | yes | **not a date** | A `string`: the recipient office. Never do date math on it. |

Seven of the eight are nullable and are filled in later via
`CaseTimelineController`. **A null is not zero and not "today"** — it means the
milestone has not happened. Guard every one.

`extension_30_days` is the one to be careful with, because two sources disagree:
the CHR source sheet's header (col Q, "REQUEST FOR 30-DAYS EXTENSION") reads like
an actual, while `CaseTimelineFactory`, `DemoDataSeeder`, `CaseDeadlineService`
and the user's 2026-07-27 confirmation all treat it as the 30-day deadline. **The
code's reading is the ratified one.** If a task depends on the other reading,
raise it rather than switching.

A case may also have no `case_timelines` row at all — see the end of "The rules"
below for what that means and what not to do with it.

## The rules

**Where a deadline comes from — the stored column wins, the offset is the
fallback:**

```
deadline = the milestone's own column where it is set,
           date_of_docket + N days where it is null      (N = 30, 60, 120)
```

Both halves matter. The Set Timeline form is free entry with no cross-field
validation, so **overriding what an investigator typed would silently discard
it** — the stored value is the office's own record. And the offset fallback is
what lets a case holding only a date of docket raise an alert at all; reading
the stored column alone reports a fully-null timeline as fine.

**What discharges a deadline — a *different* column, never its own:**

| Milestone | Deadline column | Discharged by |
|---|---|---|
| 30-day | `extension_30_days` | `date_submission_rop` |
| 60-day | `submission_60th_day` | *nothing exists* — see blocker |
| 120-day | `submission_120th_day` | `date_fir_submitted` |

A deadline only binds while its submission is outstanding. **A filed FIR
discharges every milestone**, whichever earlier columns were left blank — it is
the last document a case files, so once it is in nothing on the timeline is
outstanding. Without that rule the alerts page permanently nags cases that have
filed their final report.

Status of a milestone, in this order:

1. **Submitted** — the discharging column above is non-null (or the FIR is
   filed). Done; no countdown, no alert. **Not** the milestone's own column —
   that holds the deadline, so reading it here marks a case Submitted the moment
   its deadline is recorded.
2. **Overdue** — deadline is before today.
3. **Due soon** — deadline is today or within `WARNING_WINDOW_DAYS` (**14**) of
   it. A deadline falling *today* is due soon, not overdue.
4. **On track** — anything else.

Use Carbon's whole-day comparison (`startOfDay()`), not raw timestamps — a
case docketed at 4pm is not half a day less overdue than one docketed at 9am.
Count calendar days; there is no business-day or holiday rule specified. If a
task implies one, flag it rather than inventing a holiday calendar.

The warning window and the milestone offsets are already named constants on the
service (`WARNING_WINDOW_DAYS`, `EXTENSION_DAYS`, `SIXTIETH_DAY`,
`HUNDRED_TWENTIETH_DAY`, `FIR_TARGET_DAYS`). Read them; don't restate the
numbers as literals anywhere else.

A case may have **no `case_timelines` row at all** — one docketed before the
timeline feature landed (`CaseTimelineController` uses `updateOrCreate` for
exactly that). `statusesFor()` returns an empty array for it. **That is not
"on track"** and must not be folded into it: a case nobody has measured is not
a case that is fine. Report it as untracked, the way `/alerts` and `/reports`
both do.

## Anything not specified above

The countdown-alert feature is **built** — `CaseDeadlineService` plus the
`/alerts` page, with `DemoDataSeeder::bucketFor()` now delegating to the service
rather than restating the rule. Read the service before assuming a rule is
missing; most of what this file describes is stated there in code, and the two
must not drift apart again.

If a task genuinely needs a rule neither states — a business-day calendar, what
an extension does to the 120-day clock, whether `target_date_fir` should ever
override a computed deadline — **ask rather than decide**. That instruction has
earned its place: on the build, the warning-window length and the
stored-versus-computed question were both genuinely unspecified and both
correctly became user decisions. Check `wiki/project/database-schema.md` in the
vault first; if it's silent there too, that's a real open question and worth
surfacing.

## Tests

`tests/Unit/CaseDeadlineServiceTest.php` exists and covers the rules above.
**Extend it; don't start a parallel file.** Unit, not Feature — no HTTP, no
`RefreshDatabase`, timelines built unsaved. It extends `Tests\TestCase` rather
than PHPUnit's, because Eloquent's date casting needs a booted application to
resolve the date factory.

**Pass `$today` explicitly** rather than freezing the clock with `travelTo()` —
`statusesFor()` and `caseStatusFor()` both take an optional
`?CarbonImmutable $today` for exactly this, so a test states the date it is
reasoning about instead of mutating global time. A test that passes only on the
day it was written is worse than no test.

New behaviour needs, at minimum:

- Each milestone's boundary: the day after the deadline (overdue), the deadline
  falling today (due soon, **not** overdue), the last day of the warning window
  (due soon), one day past it (on track).
- A discharged milestone reports Submitted however overdue it is — and a filed
  FIR discharges every milestone.
- A stored column overrides the computed offset; a null column falls back to it.
- Null columns, a null timeline, and a timeline with no date of docket don't throw.

**If a test needs the 60-day milestone, assert the gate rather than skipping.**
The existing 60-day tests originally called `markTestSkipped` when the flag was
on, which meant flipping the flag *skipped* them instead of failing — they could
not demonstrate they would catch a leak. They now assert up front that the
fixture really is past its 60th day, so the gate assertions cannot pass
vacuously. Keep that property in anything new.
