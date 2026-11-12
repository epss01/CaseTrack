# Torture-case monitoring: current status

Updated 12 November 2026. This supersedes pre-activation descriptions in
`STOP_AND_ASK_SUMMARY.md`; dated earlier results remain historical.

## Implemented behavior

- `CaseDeadlineService::SIXTY_DAY_ENABLED` is true. Only
  `cases.is_torture_case === true` receives the 60-day RORP evaluation.
  Unknown is not a confirmed No and displays an unevaluated notice.
- The normal deadline is docket date +60 calendar days unless a stored base
  deadline overrides it. A recorded external +30-day grant takes precedence.
  New grants and recalculations use docket date +90, regardless of recording
  date. **That 90-day formula is a prototype assumption awaiting CHR validation
  before office adoption.** Neither the source letter nor workbook defines it.
- The recorded CHR answer of 1 August 2026 supports torture-only monitoring and
  a possible additional 30 days. Approval stays outside CaseTrack. Assigned
  Investigators and Supervisors record the external decision and reference or
  explanatory note; neither becomes an extension approver in the application.
- Requests alone do not change deadlines. RORP actual submission discharges
  the 60-day milestone; FIR submission discharges all tracked milestones.
  The warning window is 14 days, due today is not overdue, and overdue starts
  after the due date. Existing 30/120-day behavior is preserved.
- Timeline saves reload and lock the parent case, reauthorize, check
  `lock_version`, and save dates, one version increment and audits atomically.
  Supervisors also provide an edit reason. Raw extended dates are rejected.
  Recording, removal and recalculation require a reference/note when the date
  changes. Omitted controls from older callers preserve the existing grant.
- Classification cannot change to No/Unknown while a grant remains. Clear a
  mistaken grant first. Docket correction recalculates a recorded grant with
  an audited note; pre-existing conflicting dates are not silently normalized.

## History and classification review

Case details show paginated extension events with recorder, timestamp, previous
and resulting **extension** dates and reference/note. An absent previous
extension date does not mean the case had no base deadline. Unavailable accounts,
malformed details and grants without historical events are identified explicitly.
The general audit viewer is not exposed; ordinary page views remain unaudited.

Cases has an **Unknown classification only** filter, scoped active/overall counts
and links to the existing editor. Active cases precede closed cases in review
mode. Staff decide from their records; the system neither infers nor bulk-assigns
classifications. The 11 November local snapshot contained 12 active / 15 overall
Unknown cases. Those records were not classified during synthetic checks; these
changing counts are not findings about CHR practice or classification prevalence.

`php artisan cases:check-torture-deadlines` reports stored base, extension and
classification conflicts and exits nonzero if conflicts exist. The last local
check found zero of each. **Zero conflicts does not certify complete
classification.** A separate staff review is necessary. The
[office deployment checklist](../deployment/README.md#torture-deadline-release-gate)
now includes the command, backup and separate classification-review requirements.
The [acceptance record](../deployment/TORTURE_DEADLINE_ACCEPTANCE.md) keeps
verified technical results separate from pending human and office checks.

## Field and interchange meanings

| Field | Meaning |
| --- | --- |
| `cases.is_torture_case` | Nullable Boolean: Unknown / Yes / No |
| `case_timelines.submission_60th_day` | Stored base 60-day deadline override |
| `case_timelines.extension_60th_days` | Stored externally granted effective deadline; server calculates +90 for new grants/recalculations |
| `case_timelines.date_submission_60th_day_rorp` | Actual RORP submission date |
| `case_timelines.extension_30_days` | Initial 30-day ROP deadline; legacy name, not approval or request |
| `case_timelines.date_extension_30_days_requested` | Actual initial-ROP extension request date; no automatic grant |
| `case_timelines.submission_120th_day` | Stored 120-day deadline override |
| `case_timelines.date_submission_120th_day_rop` | Actual 120th-day ROP submission, separate from FIR |
| `audit_logs.notes` | Extension event's previous/resulting extension dates and reference/note; ordinary Supervisor reason is a separate audit |

The eleven-column CSV contract and CHR workbook layout are unchanged. CHR Excel
is a monitoring worksheet, omits CaseTrack-only classification/progress/complexity
and approval history, and is not a complete backup. Import remains preview-first,
create-only, active-worksheet-only and rejects existing dockets. An export cannot
be imported to update an existing case or restore all CaseTrack data.

## Verification and acceptance boundaries

The latest completed application check, on 12 November 2026, recorded **462
passing tests, 2,278 assertions and zero skips** using synthetic fixtures and
in-memory SQLite. The ten unconditional torture-deadline skips were replaced;
the four empty feature-test placeholders were implemented. Changed PHP passed
Pint. Independent reviews and synthetic responsive/keyboard checks were completed
for extension history and the review queue. These are technical results, not
CHR user evaluation or office-network performance measurements.

Stage 5 verified seven controlled concurrent-save scenarios on an isolated local
MariaDB 10.4.32 / InnoDB database, with actual lock waits, stale/authorization
failures and audit rollback; the fixture was dropped and `.env` remained unchanged.
See [Stage 5 evidence and reproducible checks](../deployment/STAGE5_ACCEPTANCE.md).

Still pending: CHR confirmation of the calendar-day basis, +90 from docket and
submission/discharge behavior; actual spoken Narrator results; representative-user
acceptance and office adoption approval. The user deferred Narrator execution and
requested the prepared checklist and CHR questions for later; neither human result
is marked complete. No deployment, commit or push is claimed here.

## Implementation sources and figures

- [Deadline service](../app/Services/CaseDeadlineService.php)
- [Timeline transaction](../app/Http/Controllers/CaseTimelineController.php)
- [Case editor and review query](../app/Http/Controllers/CaseController.php)
- [Read-only preflight](../app/Console/Commands/CheckTortureDeadlines.php)
- [Extension tests](../tests/Feature/TortureExtensionTest.php),
  [history tests](../tests/Feature/CaseExtensionHistoryTest.php),
  [review tests](../tests/Feature/CaseClassificationReviewTest.php)
- [Editable workflow figures and reproduction](figures/README.md)
- [Live manuscript](https://docs.google.com/document/d/127VZN6tvbm6bAbPc_mGW2_Xy0xFU8nY6uB_eBwXxXoE/edit)
