<?php

namespace App\Http\Controllers;

use App\Http\Requests\ReportFilterRequest;
use App\Models\AuditLog;
use App\Models\CaseModel;
use App\Models\Role;
use App\Models\User;
use App\Services\CaseDeadlineService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;

/**
 * The case listing, as a page and as a file.
 *
 * Role scoping is CaseModel::scopeVisibleTo(), the same scope /cases and
 * /alerts use — an investigator gets their own caseload, a supervisor gets the
 * office. That scope is also what makes the manuscript's investigator-level and
 * office-wide audiences one route rather than two: the audience is decided by
 * who is asking, not by which URL they visit.
 *
 * There is no per-case decision to make on a list page, so no policy class —
 * the same call /alerts and /workload make.
 */
class ReportController extends Controller
{
    /**
     * The filtered listing, on screen.
     *
     * The same rows the CSV carries, so the page is a preview of the download
     * rather than a second report that could disagree with it.
     *
     * @return \Illuminate\Contracts\Support\Renderable
     */
    public function index(ReportFilterRequest $request, CaseDeadlineService $deadlines)
    {
        $filters = $request->filters();

        $cases = $this->casesQuery($request)
            ->with(['timeline', 'investigator'])
            ->latest('docket_no')
            ->paginate(15)
            ->withQueryString();

        return view('reports.index', [
            'rows' => $cases->through(fn (CaseModel $case) => [
                'case' => $case,
                'fields' => $this->row($case, $deadlines),
            ]),
            'filters' => $filters,

            // Aggregates of their own now that the listing is paginated — they
            // describe the whole filtered set, not just the rows on screen.
            // caseCount is free: paginate() already ran that count.
            'caseCount' => $cases->total(),
            'weightTotal' => $this->casesQuery($request)->sum('complexity_weight'),
            'byStatus' => $this->casesQuery($request)
                ->toBase()
                ->selectRaw('status, count(*) as total')
                ->groupBy('status')
                ->orderBy('status')
                ->pluck('total', 'status'),

            'excludedCount' => $this->excludedByDateFilter($request),

            // Only a supervisor has anyone to choose between: an investigator
            // sees one caseload whatever the filter says.
            'investigators' => $request->user()->isSupervisor()
                ? User::query()
                    ->whereRelation('role', 'role_name', Role::INVESTIGATOR)
                    ->orderBy('last_name')
                    ->orderBy('first_name')
                    ->get()
                : collect(),
        ]);
    }

    /**
     * One case's report.
     *
     * The exception to this controller's no-policy rule, and deliberately so:
     * the two actions above are list pages that decide nothing per case, but
     * this one resolves a case from the URL and so has exactly the decision
     * CaseModelPolicy::view() exists to make — the check that stops an
     * investigator reading a colleague's case by typing its id.
     *
     * Not authorizeResource(): that would put a policy in front of the listing
     * and the export too, which is the reasoning /alerts and /workload were
     * built on.
     *
     * @return \Illuminate\Contracts\Support\Renderable
     */
    public function show(CaseModel $case, CaseDeadlineService $deadlines)
    {
        $this->authorize('view', $case);

        $case->load(['timeline', 'investigator', 'victims', 'respondents', 'complainants']);

        return view('reports.show', [
            'case' => $case,

            // Every column the CSV carries, labelled. The listing shows a subset
            // for width; read on its own a case has room for all of it.
            'columns' => array_map(fn (array $column) => $column['label'], $this->columns()),
            'fields' => $this->row($case, $deadlines),

            // What the listing has no room for: where each statutory deadline
            // actually stands. Empty when the case has no timeline to measure.
            'milestones' => $deadlines->statusesFor($case->timeline),
        ]);
    }

    /**
     * How many visible cases the date range shut out for having no timeline.
     *
     * A date filter reads through to case_timelines, so a case that never got
     * a timeline row cannot satisfy one and silently leaves the report. Saying
     * how many is the point — a total that quietly shrank is worse than a
     * total with a caveat on it. /alerts makes the same call with its
     * untracked tile.
     *
     * One count query, and only when a date range was actually asked for.
     */
    private function excludedByDateFilter(ReportFilterRequest $request): int
    {
        if (! $request->hasDateFilter()) {
            return 0;
        }

        return CaseModel::query()
            ->visibleTo($request->user())
            ->filteredBy(Arr::except($request->filters(), ['from', 'to']))
            ->doesntHave('timeline')
            ->count();
    }

    /**
     * The filtered listing, downloaded as CSV.
     *
     * Streamed rather than assembled in memory, and hand-rolled rather than
     * packaged: the project has no CSV dependency and this shape of data does
     * not justify adding one.
     */
    public function export(ReportFilterRequest $request, CaseDeadlineService $deadlines)
    {
        // The whole filtered set, not the listing's page — a download has to
        // cover what was filtered for, not what happened to be on screen.
        $cases = $this->casesQuery($request)
            ->with(['timeline', 'investigator'])
            ->latest('docket_no')
            ->get();

        // Written before the stream opens, not inside the closure: the closure
        // runs after the response has been handed to the client, so a failure
        // in it would leave no trace at all. A single insert with nothing to
        // roll back alongside it, so no transaction.
        AuditLog::record($request->user(), null, AuditLog::ACTION_EXPORTED);

        return response()->streamDownload(function () use ($cases, $deadlines) {
            $handle = fopen('php://output', 'w');

            // escape: '' is the standard CSV dialect. PHP's proprietary
            // backslash escaping is deprecated in 8.4 and would mangle a
            // backslash in an incident detail on the way out.
            fputcsv($handle, array_column($this->columns(), 'label'), escape: '');

            foreach ($cases as $case) {
                fputcsv(
                    $handle,
                    array_map($this->neutralize(...), array_values($this->row($case, $deadlines))),
                    escape: ''
                );
            }

            fclose($handle);
        }, 'casetrack-cases-'.now()->format('Y-m-d').'.csv', [
            'Content-Type' => 'text/csv',
        ]);
    }

    /**
     * The cases a report covers: who may see them, then what was asked for.
     *
     * Unfiltered/unordered on purpose — the single query definition every
     * report caller builds on top of (the paginated listing, the whole-set
     * CSV, and the caseCount/weightTotal/byStatus aggregates), so scoping and
     * filtering can never drift between them. Closed cases are included —
     * unlike /alerts this does not call active(), because a report over a date
     * range that omitted completed work could not describe the range. Deleted
     * cases stay out via SoftDeletes.
     */
    private function casesQuery(ReportFilterRequest $request): Builder
    {
        return CaseModel::query()
            ->visibleTo($request->user())
            ->filteredBy($request->filters());
    }

    /**
     * What a report says about a case, in order.
     *
     * Defined once so the CSV header, the CSV row, the listing and the per-case
     * page all read one definition. Keyed by a stable machine name rather than
     * by the label, because the listing needs to reach a particular value —
     * keying by display text would mean a reworded header silently emptied a
     * column. The labels are the field mapping a future CSV import would read
     * back, so renaming one is a breaking change; renaming a key is a rename.
     *
     * The two deadlines come from CaseDeadlineService and not from the
     * extension_30_days / submission_120th_day columns directly. The service
     * applies the office's own stored date where one was entered and falls back
     * to the offset from the date of docket where none was — so a case that
     * only ever got a docket date still reports a deadline here, and reports
     * the same one /alerts shows it. Keying by the service's own constants also
     * means the gated 60th-day milestone cannot appear in a report even if
     * SIXTY_DAY_ENABLED is turned on.
     *
     * @return array<string, array{label: string, value: callable(CaseModel, array<int, array<string, mixed>>): (string|int|null)}>
     */
    private function columns(): array
    {
        return [
            'docket_no' => [
                'label' => __('Docket No.'),
                'value' => fn (CaseModel $case) => $case->docket_no,
            ],
            'case_title' => [
                'label' => __('Title'),
                'value' => fn (CaseModel $case) => $case->case_title,
            ],
            'status' => [
                'label' => __('Status'),
                'value' => fn (CaseModel $case) => $case->status,
            ],
            'investigator' => [
                'label' => __('Investigator'),
                'value' => fn (CaseModel $case) => $case->investigator?->full_name,
            ],
            'office_region' => [
                'label' => __('Office Region'),
                'value' => fn (CaseModel $case) => $case->investigator?->office_region,
            ],
            'complexity_weight' => [
                'label' => __('Complexity Weight'),
                'value' => fn (CaseModel $case) => $case->complexity_weight,
            ],
            'date_of_docket' => [
                'label' => __('Date of Docket'),
                'value' => fn (CaseModel $case) => $this->asDate($case->timeline?->date_of_docket),
            ],
            'thirty_day_deadline' => [
                'label' => __('30-day Deadline'),
                'value' => fn (CaseModel $case, array $milestones) => $this->asDate(
                    $milestones[CaseDeadlineService::EXTENSION_DAYS]['deadline'] ?? null
                ),
            ],
            'rop_submitted' => [
                'label' => __('ROP Submitted'),
                'value' => fn (CaseModel $case) => $this->asDate($case->timeline?->date_submission_rop),
            ],
            'hundred_twentieth_day_deadline' => [
                'label' => __('120-day Deadline'),
                'value' => fn (CaseModel $case, array $milestones) => $this->asDate(
                    $milestones[CaseDeadlineService::HUNDRED_TWENTIETH_DAY]['deadline'] ?? null
                ),
            ],
            'fir_submitted' => [
                'label' => __('FIR Submitted'),
                'value' => fn (CaseModel $case) => $this->asDate($case->timeline?->date_fir_submitted),
            ],
        ];
    }

    /**
     * One case's report row, keyed by column name.
     *
     * The milestones are worked out once and passed to every accessor rather
     * than recomputed per column.
     *
     * @return array<string, string|int|null>
     */
    private function row(CaseModel $case, CaseDeadlineService $deadlines): array
    {
        $milestones = $deadlines->statusesFor($case->timeline);

        return array_map(
            fn (array $column) => ($column['value'])($case, $milestones),
            $this->columns()
        );
    }

    /**
     * ISO dates throughout, and an empty cell where there is no date.
     *
     * Formatting only — the arithmetic that produced these belongs to
     * CaseDeadlineService and stays there (CLAUDE.md, Conventions).
     */
    private function asDate(mixed $value): ?string
    {
        return $value?->format('Y-m-d');
    }

    /**
     * Stop a spreadsheet treating a case field as a formula.
     *
     * Case titles and docket numbers are free text typed by users, and a cell
     * opening with =, +, - or @ is executed on open by Excel and Sheets. The
     * leading apostrophe is the standard mitigation.
     *
     * ponytail: this makes the exported cell one character longer than the
     * stored value. The planned CSV import has to strip it back off — the
     * alternative was shipping a file that runs whatever an intake form was
     * told to store.
     */
    private function neutralize(mixed $value): mixed
    {
        return is_string($value) && $value !== '' && str_contains('=+-@', $value[0])
            ? "'".$value
            : $value;
    }
}
