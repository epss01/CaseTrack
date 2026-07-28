# Follow-ups

Known gaps deferred out of the change that found them. Each entry should be actionable by
someone who wasn't in the conversation that created it.

---

## 1. `/cases` shows less than the dashboard, and it's the page built for volume

**Status:** open
**Raised:** 2026-07-28, during the investigator dashboard build (UI/UX review, second pass)
**Files:** `resources/views/cases/index.blade.php`, `app/Http/Controllers/CaseController.php`

### The problem

The two case lists have their information backwards relative to their jobs.

`/home` (the investigator dashboard, `resources/views/dashboard.blade.php`) shows
**Docket No. · Title · Status · Complexity · Date of Docket · Submission (120th Day)** — but
it is deliberately capped to the *active* caseload and is unpaginated
(`HomeController::index()`, see the `ponytail:` comment there). It is a snapshot.

`/cases` (`resources/views/cases/index.blade.php`) is the real worklist — it paginates at 15,
includes closed cases, and is where an investigator goes to work through a docket. It shows
only **Docket No. · Title · Status**. No complexity, no dates at all.

So the page designed for sitting down and working through many cases carries the least to
scan, and the page with the richer columns is the one that can't show you everything. Two
concrete consequences:

- An investigator whose active caseload outgrows one screen loses complexity and date
  visibility entirely the moment they move to `/cases`.
- Checking any date on a **closed** case means opening the case profile
  (`cases.show`), because neither list view carries dates for it — the dashboard excludes
  closed cases by design and `/cases` has no date column.

### What to do

Bring at least a date column (`date_of_docket`) and the complexity meter into
`cases/index.blade.php`. The meter markup and the status badge already exist and are reusable:

- `resources/views/cases/partials/status-badge.blade.php` — already shared by both views.
- The `.weight-meter` markup in `dashboard.blade.php` is **not** yet extracted to a partial.
  If it goes into a second view, extract it first rather than copying it — same reasoning that
  produced the status-badge partial.

`CaseController::index()` currently eager-loads only `investigator`; it will need
`->with('timeline')` to avoid an N+1 across a paginated page.

### The catch — supervisor view

**This is the part that makes it not a five-minute change.** `cases/index.blade.php` is shared
by both roles, and for a Supervisor it already renders an extra `Investigator` column
(`@if (Auth::user()->isSupervisor())`, twice — in `thead` and `tbody`). Adding two more columns
takes the supervisor's table to **six** data columns plus the action cell, on a page that
already has to fit `col-md-10`.

Decide deliberately, don't just append columns:

- Do both roles get the new columns, or only the investigator's own list?
- If both: does the supervisor's table need a different treatment at that width (fewer
  columns, a responsive priority order, or a wider container)?

Check the result at 375px — the table is inside `.table-responsive`, so it will scroll rather
than break, but six columns scrolling on a phone is a usability answer, not just a layout one.

### Explicitly not part of this

Do **not** add overdue/due-soon classification or deadline badges, and do not sort by
`submission_120th_day`. See item 2 — the deadline rules are blocked on a missing field, and
sorting by the 120th day asserts a rule that is wrong for torture cases.

---

## 2. Statutory deadline countdown is blocked on a missing case-type field

**Status:** blocked (needs a CHR decision, then a migration)
**Raised:** carried forward; restated 2026-07-28

The 30/60/120-day milestones in `case_timelines` cannot be turned into countdown alerts yet:
**the 60-day milestone applies only to torture cases, and there is no case-type/category
column anywhere in the schema** — not in the manuscript's design and not as built.
`DemoDataSeeder::bucketFor()` is an explicit placeholder for the real countdown logic and
deliberately leaves the 60-day column empty for this reason.

Consequences visible today:

- The dashboard shows `Submission (120th Day)` but deliberately **omits** the 60th day: a
  populated 60-day column on every row would read as universally applicable when it is not.
- Row ordering stays on `docket_no` rather than the nearest deadline, because ordering by the
  120th day asserts that 120 days is *the* deadline for every case.

Needs, in order: a ratified case-type vocabulary from CHR → a migration adding the column →
intake/edit capture → then `App\Services\CaseDeadlineService` (named in CLAUDE.md's forward
guidance; `app/Services/` does not exist yet).

### Questions CHR has to answer before any of this is written

Getting these wrong produces a system that confidently tells an investigator the wrong
statutory deadline, which is worse than having no countdown at all. Ask first, build second.

**On the case-type field**
1. Is a boolean "is this a torture case" enough, or is a full category taxonomy wanted
   (torture, EJK, illegal arrest, labour rights, child rights, …)? The 60-day rule only needs
   the former; reporting probably wants the latter.
2. If a taxonomy: does it follow the `roles` pattern (lookup table + model + constants) or a
   plain constrained column?
3. Is it required at intake? If so, what happens to cases already docketed — nullable with a
   backfill, or a forced default?

**On the deadline rules**
4. `extension_30_days`, `submission_60th_day`, `submission_120th_day` and `target_date_fir` are
   currently **stored** columns, written by hand via the Set Timeline form. Should the service
   *compute* them from `date_of_docket` instead? If yes, they become derived data and either
   stop being stored or become manual overrides — that is a migration and an architecture
   decision, not just date math.
5. `extension_30_days` is named like an extension *granted*, not a deadline. Is the 30-day
   milestone conditional on an extension actually being requested?
6. For torture cases, does the 60-day deadline **replace** the 120-day one or coexist with it?
7. Which actual satisfies which deadline — `date_submission_rop` for the ROP milestones and
   `date_fir_submitted` for the FIR target, or some other pairing?
8. Calendar days or working days? `CaseTimelineFactory::deadlinesFor()` currently uses
   `addDays()`, i.e. calendar, but that was a build convenience and not verified against the
   statute.
9. What threshold counts as "due soon" — 7 days, 14, something per-milestone?

---

## 3. `cases.status` has no ratified vocabulary

**Status:** open (needs a CHR decision)

Five values are in circulation; only three are constants on `CaseModel`
(`STATUS_DOCKETED`, `STATUS_PENDING_CLOSURE`, `STATUS_CLOSED`). `'Under investigation'` and
`'For review'` exist only in `CaseModelFactory` and `DemoDataSeeder`, and the column is a
plain `string` with a free-text input on the edit form.

This is now visible on the first screen after login: the dashboard's status-distribution bar
and badges assign colours per status. `resources/views/dashboard.blade.php` holds a fixed
colour map with a `crc32` fallback so unlisted values still render consistently — it is
presentation only and constrains nothing, but it is a place that will want revisiting once
the vocabulary is actually agreed.
