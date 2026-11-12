# Stage 5 technical and guided acceptance record

12 November 2026. The user authorized Stage 5, then chose **prepare the Narrator
checklist for later** and reported **no CHR response yet**. Neither human check
has passed. This record makes no office-adoption or deployment claim.

## MariaDB concurrent-save results

Executed on local **MariaDB 10.4.32 / InnoDB** with separate PHP processes and
synthetic users/cases in a newly created disposable database. Each pair was held
until `INNODB_LOCK_WAITS` identified the contender waiting on the first request's
connection. The existing routes, middleware, request validation, policies and
controller transactions handled the writes; application code was unchanged.

| Controlled first request / waiting request | HTTP results | Persisted result |
| --- | --- | --- |
| Grant / timeline correction | 302 / 422 (`lock_version`) | Version 1; grant retained; rejected actual-date change absent; exactly one timeline and one grant audit. |
| Grant / ordinary case edit | 302 / 422 (`lock_version`) | Version 1; grant retained; rejected title change absent. |
| Ordinary case edit / grant | 302 / 422 (`lock_version`) | Version 1; corrected title retained; no grant or successful timeline audit. |
| Supervisor reassignment / former Investigator's grant | 302 / 403 | Version 1; new assignee retained; no grant. Reassignment and the existing separate access-denied audit only. |
| Grant / Supervisor reassignment | 302 / 302 | Version 2; grant and new assignee both retained. One grant transaction and one reassignment audit. Reassignment has no stale-form version requirement in the current application. |
| Supervisor grant / second Supervisor grant | 302 / 422 (`lock_version`) | Version 1; exactly one timeline, external-grant and on-behalf audit, with the supplied Supervisor reason. |
| Grant whose extension audit throws / waiting grant | 500 / 302 | Failed transaction's changed docket, extension, version and earlier timeline audit all roll back; waiting request saves from version 0 to 1 with only its own reference and two successful audits. |

All **seven** scenarios observed an actual InnoDB lock wait and passed. In the
failure-injection scenario, 500 is the expected synthetic failure, not a claimed
production defect. Successful grants retained 1 April 2026 for a 1 January 2026
docket; the failed attempt's changed docket and reference did not survive.

The fixture database was dropped after workers stopped. `.env`'s before/after
SHA-256 matched. Test application connections selected only the generated fixture;
the normal database was not selected by the harness. Normal data was not copied
or classified. This verifies controlled local lock behavior, not office load,
deadlock recovery under arbitrary traffic, browser sessions or network timing.

Reproduction, permissions and cleanup limits:
[acceptance harness instructions](../tests/Acceptance/README.md),
[PHP source](../tests/Acceptance/MariaDbConcurrentSaves.php).
The [sanitized final run](STAGE5_MARIADB_RESULT.json) records the outcomes,
PHP version and harness SHA-256 without credentials or real case data.
These seven scenarios are separate from the SQLite regression-suite count.

## Stage 5 verification checks

- Fresh Laravel regression suite: **462 passed / 2,278 assertions / zero skips**
  using in-memory SQLite; duration 41.54 seconds, not a network-performance result.
- Final standalone MariaDB harness: seven scenarios passed; saved JSON matches
  the final PHP source SHA-256. Exact persisted state, audit counts/references and
  the Supervisor reason were asserted, including rollback after audit failure.
- Pint check passed on the new PHP harness; diff whitespace check passed.
  Twenty-two local links resolved across the four linked acceptance/status docs.
- Independent review found no confirmed defects in isolation, cleanup,
  concurrency proof, assertions or evidence claims. The reviewer inspected
  source and final-run evidence without independently rerunning database tests.
- No application behavior, schema, `.env`, Blade, Sass or JavaScript was changed
  in Stage 5. No asset build or new browser/accessibility execution was needed
  for the harness/document changes. Narrator speech remains an actual human
  acceptance item, separate from earlier automated UI checks.

## Narrator checklist — prepared, not executed

Use an approved isolated test installation with synthetic records. The MariaDB
concurrency fixture above is deliberately dropped and is not a browser test site.
When ready for guided testing, prepare a temporary isolated site; do not change
real case classifications or grants to create these scenarios.

Prepare an assigned Investigator, another Investigator and a Supervisor, with
synthetic confirmed-torture and Unknown cases. Keep one case available in two
tabs for a stale-save test and at least 11 extension events for pagination. Use
the existing forms to build fixtures or ask for isolated fixture setup. Record
OS/browser versions, Narrator version/voice, scan-mode setting, date and tester.

Start/stop Narrator with **Windows + Ctrl + Enter**. Use Tab/Shift+Tab to move
between controls, Space for the checkbox and Enter for links/buttons. Narrator
scan mode can be toggled with **Narrator + Space**; Caps Lock or Insert is the
default Narrator key. Note any mode changes rather than silently treating a
keystroke intercepted by scan mode as an application failure. See Microsoft's
[keyboard command reference](https://support.microsoft.com/en-us/accessibility/windows/narrator/appendix-b-narrator-keyboard-commands-and-touch-gestures)
and [scan-mode guidance](https://support.microsoft.com/en-us/accessibility/windows/narrator/chapter-3-using-scan-mode).

For every row record the **actual spoken words**, focus destination, task outcome
and any defect. Expected information below is an acceptance criterion, not a
transcript or a claim that Narrator already says it.

| Check | Action and expected information | Actual speech / outcome |
| --- | --- | --- |
| Timeline labels | Reach docket and actual-date fields; identify each label and current value without relying on its visual position. | Not run |
| Grant state | Reach Externally granted 30-day extension; hear its label and checked/unchecked state, toggle with Space and verify the changed state. | Not run |
| Required grant note | Submit a new grant with no note; find and understand the reference/note error, reach the field and correct it without losing the other values. | Not run |
| Supervisor reason | Repeat as Supervisor; missing edit reason must be discoverable separately from the grant note and clearly associated with its field. | Not run |
| Successful grant | Save synthetic reference, then locate effective date and extension indication. Distinguish recording from approving. | Not run |
| Extension history | Reach history and read action, actor, timestamp, previous/resulting extension date and reference in a meaningful order. Read missing-history notices without invented approval details. | Not run |
| History pagination | Navigate to the next history page and back using keyboard; identify the current page and recover the history position. | Not run |
| Removal | Uncheck grant, provide explanatory note and save; hear/locate the restored base deadline and retained removal event. | Not run |
| Unknown review | Identify the Unknown-only filter, its checked state, Apply button and active/overall counts. Open Review classification and identify Unknown/Yes/No. | Not run |
| Save and return | Choose a classification from synthetic supporting facts, save and return to review; locate the refreshed counts and any empty state. Unknown remains unevaluated until a decision is recorded. | Not run |
| Stale save | Open timeline in tabs A/B. Save a valid change in A; submit B's older version. Find the stale warning, understand reload/reconcile instructions and confirm B did not overwrite A. This is a browser warning check, separate from the simultaneous MariaDB test. | Not run |
| Keyboard recovery | Check visible focus, logical return navigation and no keyboard trap across the above tasks. Record any assistance required. | Not run |

Response template for later guided testing:

```text
Check ID / date / environment:
Narrator mode and actual words heard:
Focus location and task result:
Problem, assistance needed, and retest result:
```

Automated DOM/accessibility checks and the existing synthetic keyboard reviews
remain separate evidence. They cannot fill the actual-speech column or establish
screen-reader acceptance. No Narrator app was started for this deferred check.

## Questions for CHR — prepared, not sent or answered

Please confirm or correct the current prototype behavior:

1. Is the torture-case RORP deadline **60 calendar days after docketing**, using
   the docket date as day zero? For example, docketing on 1 January 2026 gives
   2 March 2026. Are any exclusions or authorized base-date overrides required?
2. If the external authority grants an additional 30 days, does the effective
   deadline always become **90 calendar days after docketing** (1 April 2026
   in that example), regardless of the request, grant or recording date? What
   happens when the decision is received after the original deadline?
3. Which authority grants the extension outside CaseTrack, and what reference
   or supporting record should the assigned Investigator/Supervisor retain when
   recording or correcting it? We currently record the external decision; the
   system never approves it, and a request alone does not change the deadline.
4. Does actual 60th-day RORP submission stop the torture-case milestone, and
   does actual FIR submission stop all tracked milestones? The current 120-day
   obligation is discharged by FIR; recording the separate 120th-day ROP actual
   alone does not discharge it. Please identify any required correction.
5. Is the 14-day warning window appropriate, with a deadline due today still
   due and overdue starting the following day? How should staff handle cases
   whose torture classification remains Unknown while records are reviewed?

Obtain the reply through the user's normal CHR contact. Keep case-identifying
examples out of it. Record source and qualifications rather than converting an
informal workflow answer into formal adoption approval.

| Response item | Actual result |
| --- | --- |
| Respondent's role, date and source/reference | Pending — no response supplied |
| Calendar-day basis / docket anchor / base overrides | Pending |
| 90-day ending / late decision behavior | Pending |
| External authority and required supporting reference | Pending |
| RORP / FIR / 120-day discharge | Pending |
| Warning boundary / unresolved classification procedure | Pending |
| Contradictions, consequential change and approval | Pending; stop before rule changes if contradicted |
| Formal office-adoption decision and approving owner | Pending; distinct from workflow confirmation |

**Remaining blockers:** actual Narrator results, CHR workflow validation of the
provisional rule, representative-user acceptance and institutional/IT adoption.
No external message, service change, deployment, commit or push is performed by
this record. See the [overall acceptance record](TORTURE_DEADLINE_ACCEPTANCE.md).
