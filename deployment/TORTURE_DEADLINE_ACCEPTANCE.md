# Torture-deadline deployment and acceptance record

Prepared 12 November 2026 during Stage 4. **No office deployment or acceptance
has been performed by creating this record.** Unchecked items remain pending;
silence, a prototype rule or a technical test does not constitute approval.
Use this with the [office release gate and operator steps](README.md#torture-deadline-release-gate).

## Existing technical evidence

These are dated development results, not office acceptance. Stage 4 changes
documentation only and does not rerun or re-date earlier technical tests.

| Evidence | Result and limits |
| --- | --- |
| 26 October 2026 HTTPS-package verification | 50 disposable harness checks; 364 tests / 1,427 assertions / 10 skips. Preserved verbatim in [VERIFICATION.md](VERIFICATION.md). No office services or persistent database were verified by that harness. |
| 12 November 2026 current Laravel suite | 462 passed / 2,278 assertions / zero skips, synthetic fixtures and in-memory SQLite. Recorded in the [Stage 3 revision](../docs/STAGE3_MANUSCRIPT_REVISION.md). This does not demonstrate simultaneous MariaDB saves or CHR user acceptance. |
| Extension history and Unknown review | Implemented with authorized scope; prior independent reviews and synthetic responsive, keyboard and no-JavaScript checks completed. Screen-reader spoken results remain pending. See [current status](../docs/TORTURE_DEADLINE_STATUS.md). |
| Earlier local preflight | Zero base, extension and classification conflicts were reported before Stage 4; it made no corrections. This is not a result for the intended office database/release and must be rerun there. |
| Classification snapshot, 11 November 2026 | 12 active / 15 overall local Unknown cases; no real classifications were inferred or changed by synthetic checks. These are historical development counts, not current office totals. |

## Candidate installation record — not yet completed

Keep case-specific references, supporting approvals, backups and credentials in
the protected office register. Enter only non-sensitive evidence references here.
Do not paste real case names, dockets, personal notes, credentials or APP_KEY.

| Item | Actual value / evidence reference |
| --- | --- |
| Release/commit or artifact checksum | Pending |
| Candidate environment / database reference | Pending; no credentials |
| Operator and maintenance window | Pending |
| CHR workflow reviewer / source | Pending |
| CHR IT release owner / decision date | Pending |
| Consistent backup reference and capture time | Pending |
| Isolated restoration test: environment, date and result | Pending |
| Preflight timestamp, exit code and three conflict totals | Pending; use the candidate installation |
| Successful rerun after authorized corrections/import | Pending or not applicable with reason |
| Office-wide Unknown counts: active / overall, date | Pending; Supervisor view with search cleared |
| Completed staff reviews / remaining unresolved reviews | Pending; protected reference and responsible staff |
| Candidate-release synthetic smoke evidence | Pending; expected/actual values and tester |
| Office-client read-only/access smoke evidence | Pending |
| Open issues, owner and planned resolution | Pending |

Any nonzero preflight conflicts or command failure block rollout until explicit
authorized corrections and a successful rerun. Unknown counts are a separate
review: record unresolved cases honestly and leave them unevaluated for the
60-day rule. Do not convert them to No to obtain a clean checklist.

## Human confirmation and remaining technical acceptance

The prototype uses 60 calendar days from docketing, or a stored base override,
for confirmed torture cases. A recorded external grant takes precedence with a
new/recalculated date of 90 calendar days from docketing. **CHR has not yet
validated that 90-day formula for adoption.** Approval stays outside CaseTrack.

- [ ] CHR confirms the calendar-day basis and docket-date reference. Record the
  respondent's role, response date, source/reference and exact qualifications.
- [ ] CHR confirms whether an externally granted additional 30 days always ends
  90 days from docket, regardless of when it is granted or recorded, and what
  supporting reference staff must retain. A request alone is not a grant.
- [ ] CHR confirms RORP/FIR submission and discharge behavior. A contradictory
  answer requires a separately approved rule change before adoption.
- [ ] CHR supplies the workflow response; the team records it without inventing
  answers. Workflow confirmation and formal institutional adoption approval are
  recorded as separate decisions.
- [x] Stage 5: reproducible, isolated MariaDB simultaneous-save results for
  timeline/timeline, case/timeline and reassignment/grant updates, including
  authorization/version failures and atomic audits. Normal database and `.env`
  remain unchanged by that test. Seven controlled overlaps passed on local
  MariaDB 10.4.32 / InnoDB; see [Stage 5 evidence](STAGE5_ACCEPTANCE.md).
- [ ] Stage 5: guided Narrator results for labels, checkbox state, required-note
  errors, history, Unknown review and stale-save warnings. Record what was
  actually spoken, environment/date, user result and defects; automated checks
  do not satisfy this item.
- [ ] Representative-user acceptance: actual Investigator/Supervisor tasks and
  observed results, with outstanding problems and resolutions. Do not invent
  participant counts, ratings or office timings.
- [ ] CHR IT verifies the intended office installation, runtime, client trust,
  authorization, backups/restoration, renewal and operational ownership using
  the existing deployment checklist.
- [ ] The authorized institutional/IT owner records the office-adoption and
  release decision, scope, date and any conditions after reviewing the above.

**Current decision: office adoption/rollout is not signed off.** The user
authorized Stage 5 on 12 November 2026 and deferred Narrator execution, requesting
the checklist and CHR questions for later. Both human results remain pending in
the [Stage 5 record](STAGE5_ACCEPTANCE.md). This record sends no CHR message,
changes no office service and grants no external extension.

## Stage 4 documentation checks

Completed 12 November 2026:

- Command name, output labels and operator instructions checked against the
  existing command, controller/request, deadline service and UI.
- 23 local links and four section anchors resolved across the four Stage 4
  documents; diff whitespace check passed.
- The PowerShell preflight block parsed successfully. With the Artisan line
  replaced by simulated exit codes, 0 proceeded and 1/2 stopped. This did not
  execute Artisan or connect to a database.
- The October 26 verification body was compared with the repository version
  and is unchanged, apart from line-ending/trailing-newline representation.
  Only its new historical notice was added.
- Independent review of the stable Stage 4 documents found no confirmed
  defects against the implementation and supplied evidence.

No application tests, Pint, asset build, HTTPS harness or database preflight were
rerun for this documentation-only stage. No office service or `.env` was changed.
These document checks do not complete the operational or human items above.
