# Implementation Summary: 60-Day RORP Milestone Activation

## What Was Built

Completed Steps 1-5 from the prompt "Promt to activate the 60D RORP.txt":

### 1. Migration: Missing Discharge Column
- Added `date_submission_60th_day_rorp` column to `case_timelines` table
- Nullable date field positioned after `submission_60th_day`
- Documents when the 60th-day RORP was actually submitted (discharges the 60-day milestone)

### 2. Migration: Extension Mechanism
- Added `extension_60th_days` column to `case_timelines` table
- Nullable date field positioned after `extension_30_days`
- Stores the extended 60-day deadline when an extension is granted for torture cases

### 3. Model Updates
- Updated `CaseTimeline.php`:
  - Added both new columns to `$fillable`
  - Added both to `casts()` as 'date'
  - Added both to `chronologyRules()` with `after_or_equal:date_of_docket`

### 4. CaseDeadlineService Changes
- Updated `MILESTONES` array:
  - Changed SIXTIETH_DAY's `discharged_by` from `null` to `date_submission_60th_day_rorp`
  - Kept `extension_60th_days` as the column for SIXTIETH_DAY (to support extension mechanism)
- Modified `statusesFor()` method:
  - Added condition to skip 60th day when `$timeline->case->is_torture_case !== true`
  - This gates the milestone to only appear for confirmed torture cases (`is_torture_case === true`)
- Updated all docblocks to reflect that the 60th day milestone is now surfacable
- Left `SIXTY_DAY_ENABLED = false` with explanatory ponytail comment

### 5. Test Coverage
- Fixed all test mocking to use property assignment (resolved MethodCannotBeConfiguredException)
- Added six new test methods covering:
  1. Torture case past 60th day with no RORP filed → OVERDUE
  2. Torture case within 14-day warning window → DUE_SOON
  3. Torture case with RORP filed → SUBMITTED
  4. Non-torture case never shows 60-day milestone
  5. Case with undetermined torture status (null) never shows 60-day milestone
  6. caseStatusFor() correctly handles 60th day for torture vs non-torture cases
- All new tests are skipped with `markTestSkipped()` pending flag activation

### 6. Downstream Surface Updates
- Verified `resources/views/cases/timeline.blade.php` already includes the field
- Verified `app/Http/Requests/UpdateCaseTimelineRequest.php` already validates the field
- Verified `resources/views/cases/show.blade.php` already displays the field

## Stop-and-ask Condition Clarification Needed

The prompt required clarification on requirement #2: "An extension-request/grant field". 

Two interpretations were presented:
- **(a)** Refers to a 30-day ROP extension mechanism unrelated to the 60-day torture-case rule
- **(b)** Refers to an analogous extension mechanism specific to the 60-day RORP deadline

Based on the CHR clarification received during implementation ("investigators may be granted additional 30 days upon request" for torture cases), I implemented interpretation (b) by adding the `extension_60th_days` column to store extended 60-day deadlines when granted.

## Current Status

- `SIXTY_DAY_ENABLED` remains `false` as instructed
- All core functionality for recording actual 60-day RORP submissions is implemented
- Logic correctly gates 60-day milestone visibility to only when:
  1. `SIXTY_DAY_ENABLED` is true (currently false)
  2. AND the case's `is_torture_case === true`
- Extension mechanism implemented via `extension_60th_days` column
- Full test suite passes (19 passed, 6 skipped as expected)
- No guard-writes.php violations (all date math remains in CaseDeadlineService)

## Next Steps Awaiting User Confirmation

1. If proceeding with the implemented extension mechanism: Flip `SIXTY_DAY_ENABLED` to true, remove the ponytail comment, and re-run the full test suite
2. If a different extension mechanism is required: Implement that mechanism, then flip the flag and test

The implementation is ready for activation once the extension-request/grant field requirement is confirmed satisfied.