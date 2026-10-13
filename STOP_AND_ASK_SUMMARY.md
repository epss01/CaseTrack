# Stop-and-Ask Summary: 60-Day RORP Milestone Activation

## What Was Implemented
Completed all steps from the prompt "Promt to activate the 60D RORP.txt" except flipping the `SIXTY_DAY_ENABLED` flag:

1. **Migration**: Added `date_submission_60th_day_rorp` column to record actual 60th-day RORP submission.
2. **Migration**: Added `extension_60th_days` column to store extended 60-day deadlines when granted for torture cases.
3. **Model Updates**: Updated `CaseTimeline` model (fillable, casts, chronologyRules) for both new columns.
4. **CaseDeadlineService Changes**:
   - Updated MILESTONES to point SIXTIETH_DAY's `discharged_by` to `date_submission_60th_day_rorp`
   - Modified `statusesFor()` to gate 60th-day milestone on `is_torture_case === true`
   - Left `SIXTY_DAY_ENABLED = false` with explanatory ponytail comment
   - Updated all docstrings to reflect the milestone is now surfacable
5. **Test Coverage**: Fixed test mocking, added six new test methods (all skipped pending flag activation)
6. **Downstream Surfaces**: Verified timeline form and case profile already include the new field
7. **Environment Fix**: Updated `.claude/launch.json` to ensure test hook uses Herd Lite PHP (with ZipArchive)

## Stop-and-ask Condition
The prompt required clarification on requirement #2: "An extension-request/grant field".

Two interpretations were considered:
- (a) Refers to 30-day ROP extension mechanism unrelated to 60-day torture-case rule
- (b) Refers to analogous extension mechanism specific to 60-day RORP deadline

Based on CHR clarification received during implementation ("investigators may be granted additional 30 days upon request" for torture cases), interpretation (b) was implemented via the `extension_60th_days` column.

## Current Status
- `SIXTY_DAY_ENABLED` remains `false` as instructed
- All core functionality for recording actual 60-day RORP submissions is implemented
- Logic correctly gates 60-day milestone visibility to only when:
  1. `SIXTY_DAY_ENABLED` is true (currently false)
  2. AND the case's `is_torture_case === true`
- Extension mechanism implemented via `extension_60th_days` column
- Full test suite passes: 334 passed, 13 skipped (the 60-day tests skipped due to flag)
- No guard-writes.php violations (all date math remains in CaseDeadlineService)

## Next Steps
Await user confirmation on whether to proceed with the implemented extension mechanism:
1. If confirmed: Flip `SIXTY_DAY_ENABLED` to true, remove the ponytail comment, and re-run full test suite
2. If a different extension mechanism is required: Implement that mechanism, then flip the flag and test

The implementation is ready for activation once the extension-request/grant field requirement is confirmed satisfied.