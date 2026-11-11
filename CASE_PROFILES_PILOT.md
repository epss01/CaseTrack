# Case profiles — manuscript insert and CHR pilot worksheet

## Chapter III — Methods

CaseTrack groups FIR submissions within an inclusive reporting period. Supervisors examine the office caseload. Investigators examine their currently assigned cases. Each case contributes its recorded complexity (1–5), number of victim, respondent and complainant records, and calendar days from docket to FIR. The same person recorded in two roles contributes two party records. A case submitted on its docket date contributes zero elapsed days. Cases without an FIR date fall outside the period count. Among submissions in the period, the report counts exclusions for missing docket dates, reversed dates and invalid complexity, in that order.

For each eligible cohort, the system centers each measurement on its mean and divides by its population standard deviation. Constant measurements contribute zero distance. K-means fits three groups in this standardized space using squared Euclidean distance. It uses case-ID order and ten K-means++ initializations with seeds 1 through 10. A run must retain three nonempty groups and reach unchanged memberships within 100 iterations. The fit with the smallest sum of squared distances to the group centers is selected, with the earlier seed breaking a tie. The report requires at least 15 eligible cases, three distinct measurement combinations and three members per fitted group. These provisional thresholds govern presentation. Means and individual values are displayed in their original units, with profiles ordered by mean time to FIR.

## Chapter IV — Technical results

A synthetic cohort of 15 cases produced three groups of five. Their mean docket-to-FIR times were 0.2, 10.2 and 20.2 days. Reversing the input order reproduced the memberships and summaries. Separate access tests exercised office-wide and investigator-only results, including attempts to access another investigator’s case report.

On local PHP 8.4.27 with in-memory SQLite, a supervisor request covering 1,000 synthetic cases and 3,200 party records took 598.5 ms on the first request and a median of 472.5 ms over nine warm requests (471.0–474.9 ms). The measurement includes middleware, database reads, clustering and rendering the HTML with 1,000 case links. Application startup, network transfer and browser rendering were outside the measurement. Repeat the timing with the largest authorized representative office period and the office MariaDB runtime before rollout.

## CHR pilot worksheet

Use the same FIR period, eligible cases and role-specific access scope for both views. For the comparison, group the same cases by each recorded complexity score, 1 through 5. Provide counts, means and the same authorized docket details. Alternate the order of the two views across participants within each role.

Ask each participant to identify the group with the longest average time to FIR, inspect two dockets from that group and state one follow-up question supported by those records. Record whether the participant finds the correct group, opens two appropriate records and bases the question on the displayed facts. Measure time from presentation to answer. Ask “How useful was this report for deciding what to follow up?” on a 1–5 scale from “not useful” to “very useful.” Record the reason for the rating. If profiles are unavailable, record the reason and the number of eligible cases.

| Participant code | Role | View and order | Tasks completed (0–3) | Time (seconds) | Usefulness (1–5) | Follow-up question or difficulty |
|---|---|---|---|---|---|---|

Report participation, completions, median task times, rating distributions and recurring observations separately for supervisors and investigators. Keep case identifiers out of manuscript results.

For the same authorized pilot cohort, repeat the standardized fit offline with two and four groups alongside the three-group result. Record group sizes and means and which cases move together across fits. Repeat after omitting the longest duration, including all ties, to see whether an extreme value defines a profile. Where import sources can be reconciled, count cases whose missing or invalid source complexity defaulted to 3, record the denominator inspected, and repeat the analysis without those cases. A stored 3 alone cannot identify a default. If sources are unavailable, report default prevalence as unknown. Report the observed differences and CHR responses in Chapter IV after collection.

