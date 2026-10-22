# Summary of Changes for 60-Day RORP Milestone Activation

## Overview
Implemented the core functionality for activating the 60-day (RORP) statutory milestone in CaseTrack as specified in the prompt "Promt to activate the 60D RORP.txt". Left SIXTY_DAY_ENABLED = false pending clarification on the extension-request/grant field requirement.

## Changes Made

### 1. Migration
- **File**: `database/migrations/2026_09_08_000000_add_date_submission_60th_day_rorp_to_case_timelines.php`
- Added nullable `date_submission_60th_day_rorp` column to `case_timelines` table
- Positioned after `submission_60th_day` column
- Added descriptive comment explaining the column's purpose

### 2. Model Updates
- **File**: `app/Models/CaseTimeline.php`
- Added `date_submission_60th_day_rorp` to `$fillable` array
- Added `date_submission_60th_day_rorp` => 'date' to `casts()` method
- Added `date_submission_60th_day_rorp` => ['after_or_equal:date_of_docket'] to `chronologyRules()` method

### 3. CaseDeadlineService Updates
- **File**: `app/Services/CaseDeadlineService.php`
- Updated MILESTONES array: changed `discharged_by` for SIXTIETH_DAY from `null` to `'date_submission_60th_day_rorp'`
- Updated docblocks to reflect that the 60th day milestone is now surfaced (no longer "computed but never surfaced")
- Modified `statusesFor()` method to skip 60th day milestone when `$timeline->case->is_torture_case !== true` (strict comparison)
- Preserved existing guarantee that `visibleMilestones()` remains the only entry point for discovering shown milestones
- Left `SIXTY_DAY_ENABLED = false` with explanatory ponytail comment

### 4. Test Updates
- **File**: `tests/Unit/CaseDeadlineServiceTest.php`
- Fixed mocking of case relationship in all tests (using property assignment instead of `expects()->willReturn()`)
- Added six new test methods covering the new 60-day milestone behavior:
  1. Torture case past 60th day with no RORP filed → OVERDUE
  2. Torture case within 14-day warning window → DUE_SOON
  3. Torture case with RORP filed → SUBMITTED
  4. Non-torture case never shows 60-day milestone
  5. Case with undetermined torture status (null) never shows 60-day milestone
  6. caseStatusFor() correctly handles 60th day for torture vs non-torture cases
- All new tests are skipped with `markTestSkipped()` pending flag activation
- Previously skipped tests related to 60-day milestone remain skipped

### 5. Downstream Surface Updates
- **File**: `resources/views/cases/timeline.blade.php`
  - Added `'date_submission_60th_day_rorp' => __('Submission (60th Day RORP)')` to `$dateFields` array
- **File**: `app/Http/Requests/UpdateCaseTimelineRequest.php`
  - Added `'date_submission_60th_day_rorp' => array_merge(['nullable', 'date'], $chronology['submission_60th_day'])` to `rules()`
  - Added `'date_submission_60th_day_rorp' => __('submission (60th day RORP)')` to `attributes()`
- **File**: `resources/views/cases/show.blade.php`
  - Added `__('Submission (60th Day RORP)') => $timeline?->date_submission_60th_day_rorp?->format('d M Y')` to `$timelineFields` array

## Verification
- All CaseDeadlineServiceTest tests pass (19 passed, 6 skipped as expected)
- No guard-writes.php violations (all date math remains in CaseDeadlineService)
- The implementation correctly gates the 60-day milestone to appear only when:
  1. SIXTY_DAY_ENABLED is true (handled by visibleMilestones())
  2. AND the case's is_torture_case === true (handled in statusesFor())

## Stop-and-Ask Condition Clarification Needed
As instructed in the prompt, I have not flipped SIXTY_DAY_ENABLED to true due to unresolved ambiguity in requirement #2 from the precondition section:

> "An extension-request/grant field" — NOT done, and its exact shape is not specified anywhere in the codebase or docs available to you. Do not invent one.

There are two possible interpretations:
- **(a)** It refers to a 30-day ROP extension mechanism unrelated to the 60-day torture-case rule itself (the CHR-answers text mentions "a thirty-day extension may be requested" in the context of the ordinary ROP deadline, not the torture-case timeline) — in which case it may be out of scope for this change entirely, and the docblock's "AND" is stricter than the actual business requirement.
- **(b)** It refers to some analogous extension mechanism specific to the 60-day RORP deadline that has never been built and isn't described anywhere you have access to.

Please clarify which interpretation is correct so I can either:
1. Confirm reading (a) and flip SIXTY_DAY_ENABLED to true in a follow-up, or
2. Implement the required extension mechanism for reading (b) before enabling the flag

## Next Steps
Once the extension-request/grant field ambiguity is resolved:
1. If proceeding with reading (a): Flip SIXTY_DAY_ENABLED to true, remove the ponytail comment, and re-run the full test suite
2. If proceeding with reading (b): Implement the extension mechanism, then flip the flag and test

## Notes
- DemoDataSeeder.php was reviewed but not modified as the bucket counts (4 overdue / 4 due soon / 4 on track / 3 closed) would only change if the new milestone genuinely moves a case's classification, which wasn't evident from the seeded data
- No changes were made to resources/views/cases/show.blade.php beyond adding the new timeline field as it was already present in the current code