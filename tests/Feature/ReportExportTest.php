<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\CaseModel;
use App\Models\CaseTimeline;
use App\Models\User;
use App\Services\CaseDeadlineService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The CSV export at /reports/export.
 *
 * Every case pins its status explicitly, because CaseModelFactory defaults it
 * to a random pick that includes 'Closed' — an unpinned case would drift in and
 * out of a status filter at random.
 *
 * Assertions read the streamed body rather than the response object: the CSV is
 * built inside the download closure, so nothing is asserted until that closure
 * has run.
 */
class ReportExportTest extends TestCase
{
    use RefreshDatabase;

    // ------------------------------------------------------------ access

    public function test_a_guest_is_sent_to_the_login_page(): void
    {
        $this->get('/reports/export')->assertRedirect(route('login'));
    }

    public function test_a_user_with_neither_case_handling_role_is_refused(): void
    {
        $clerk = User::factory()->withRole('Records Clerk')->create();

        $this->actingAs($clerk)->get('/reports/export')->assertForbidden();
    }

    public function test_the_file_downloads_as_a_csv_attachment(): void
    {
        $investigator = User::factory()->investigator()->create();

        $response = $this->actingAs($investigator)->get('/reports/export')->assertOk();

        $this->assertStringContainsString('text/csv', $response->headers->get('content-type'));
        $this->assertStringContainsString('attachment', $response->headers->get('content-disposition'));
        $this->assertStringContainsString('.csv', $response->headers->get('content-disposition'));
    }

    // ----------------------------------------------------------- scoping

    public function test_an_investigator_exports_only_their_own_cases(): void
    {
        $investigator = User::factory()->investigator()->create();

        $own = $this->caseFor($investigator);
        $theirs = $this->caseFor();

        $csv = $this->export($investigator);

        $this->assertStringContainsString($own->docket_no, $csv);
        $this->assertStringNotContainsString($theirs->docket_no, $csv);
    }

    /**
     * Every fixture belongs to a different investigator, so a query that had
     * accidentally scoped itself to the supervisor's own cases would fail here.
     */
    public function test_a_supervisor_exports_the_whole_office(): void
    {
        $supervisor = User::factory()->supervisor()->create();

        $first = $this->caseFor();
        $second = $this->caseFor();

        $csv = $this->export($supervisor);

        $this->assertStringContainsString($first->docket_no, $csv);
        $this->assertStringContainsString($second->docket_no, $csv);
        $this->assertStringContainsString($first->investigator->full_name, $csv);
    }

    /**
     * The filter is a convenience, not a boundary — scopeVisibleTo() runs
     * first, so asking for someone else's caseload yields nothing at all.
     */
    public function test_an_investigator_cannot_reach_another_caseload_through_the_filter(): void
    {
        $investigator = User::factory()->investigator()->create();
        $colleague = User::factory()->investigator()->create();

        $theirs = $this->caseFor($colleague);

        $csv = $this->export($investigator, ['investigator_id' => $colleague->id]);

        $this->assertStringNotContainsString($theirs->docket_no, $csv);
    }

    public function test_deleted_cases_are_never_exported(): void
    {
        $investigator = User::factory()->investigator()->create();

        $deleted = $this->caseFor($investigator);
        $deleted->delete();

        $this->assertStringNotContainsString($deleted->docket_no, $this->export($investigator));
    }

    /**
     * Unlike /alerts, a report covers closed cases: a report over a period that
     * dropped the work finished in it could not describe the period.
     */
    public function test_closed_cases_are_exported(): void
    {
        $investigator = User::factory()->investigator()->create();

        $closed = $this->caseFor($investigator, status: CaseModel::STATUS_CLOSED);

        $this->assertStringContainsString($closed->docket_no, $this->export($investigator));
    }

    // ----------------------------------------------------------- filters

    public function test_the_date_range_includes_its_own_boundaries(): void
    {
        $investigator = User::factory()->investigator()->create();

        $onFrom = $this->caseFor($investigator, docketedOn: '2026-03-01');
        $inside = $this->caseFor($investigator, docketedOn: '2026-03-15');
        $onTo = $this->caseFor($investigator, docketedOn: '2026-03-31');
        $before = $this->caseFor($investigator, docketedOn: '2026-02-28');
        $after = $this->caseFor($investigator, docketedOn: '2026-04-01');

        $csv = $this->export($investigator, ['from' => '2026-03-01', 'to' => '2026-03-31']);

        $this->assertStringContainsString($onFrom->docket_no, $csv);
        $this->assertStringContainsString($inside->docket_no, $csv);
        $this->assertStringContainsString($onTo->docket_no, $csv);
        $this->assertStringNotContainsString($before->docket_no, $csv);
        $this->assertStringNotContainsString($after->docket_no, $csv);
    }

    /**
     * The date range reads through to case_timelines, so a case that never got
     * a timeline row cannot satisfy it. Documented here because it is the one
     * way a filtered report differs from the caseload it reports on.
     */
    public function test_a_case_with_no_timeline_falls_out_of_a_date_filtered_export(): void
    {
        $investigator = User::factory()->investigator()->create();

        $untracked = CaseModel::factory()->assignedTo($investigator)->create([
            'status' => CaseModel::STATUS_DOCKETED,
        ]);

        $this->assertStringContainsString($untracked->docket_no, $this->export($investigator));

        $this->assertStringNotContainsString(
            $untracked->docket_no,
            $this->export($investigator, ['from' => '2026-01-01'])
        );
    }

    public function test_the_status_filter_matches_exactly(): void
    {
        $investigator = User::factory()->investigator()->create();

        $docketed = $this->caseFor($investigator, status: CaseModel::STATUS_DOCKETED);
        $closed = $this->caseFor($investigator, status: CaseModel::STATUS_CLOSED);

        $csv = $this->export($investigator, ['status' => CaseModel::STATUS_DOCKETED]);

        $this->assertStringContainsString($docketed->docket_no, $csv);
        $this->assertStringNotContainsString($closed->docket_no, $csv);
    }

    public function test_a_supervisor_can_filter_to_one_investigator(): void
    {
        $supervisor = User::factory()->supervisor()->create();
        $investigator = User::factory()->investigator()->create();

        $wanted = $this->caseFor($investigator);
        $other = $this->caseFor();

        $csv = $this->export($supervisor, ['investigator_id' => $investigator->id]);

        $this->assertStringContainsString($wanted->docket_no, $csv);
        $this->assertStringNotContainsString($other->docket_no, $csv);
    }

    public function test_a_range_that_ends_before_it_begins_is_rejected(): void
    {
        $investigator = User::factory()->investigator()->create();

        $this->actingAs($investigator)
            ->get(route('reports.export', ['from' => '2026-03-31', 'to' => '2026-03-01']))
            ->assertSessionHasErrors('to');
    }

    public function test_an_investigator_id_that_belongs_to_no_investigator_is_rejected(): void
    {
        $investigator = User::factory()->investigator()->create();
        $supervisor = User::factory()->supervisor()->create();

        $this->actingAs($investigator)
            ->get(route('reports.export', ['investigator_id' => $supervisor->id]))
            ->assertSessionHasErrors('investigator_id');
    }

    // -------------------------------------------------------- pagination

    /**
     * The listing paginates at 15; the export must not. A later refactor
     * that let casesQuery() slip a page limit into the CSV path would fail
     * here even though the listing above still looked correct.
     */
    public function test_the_export_covers_every_matching_case_not_just_a_page(): void
    {
        $investigator = User::factory()->investigator()->create();

        $dockets = [];
        for ($i = 0; $i < 16; $i++) {
            $docket = 'CHR-VIII-EXP-'.str_pad((string) $i, 4, '0', STR_PAD_LEFT);
            $this->caseFor($investigator, attributes: ['docket_no' => $docket]);
            $dockets[] = $docket;
        }

        $csv = $this->export($investigator);

        foreach ($dockets as $docket) {
            $this->assertStringContainsString($docket, $csv);
        }
    }

    // ------------------------------------------------------------ content

    public function test_the_header_row_names_every_column(): void
    {
        $investigator = User::factory()->investigator()->create();

        $header = strtok($this->export($investigator), "\n");

        foreach ([
            __('Docket No.'), __('Title'), __('Status'), __('Investigator'),
            __('Office Region'), __('Complexity Weight'), __('Date of Docket'),
            __('30-day Deadline'), __('ROP Submitted'), __('120-day Deadline'),
            __('FIR Submitted'),
        ] as $column) {
            $this->assertStringContainsString($column, $header);
        }
    }

    public function test_a_case_exports_its_fields_and_its_computed_deadlines(): void
    {
        $investigator = User::factory()->investigator()->create([
            'office_region' => 'CHR Region VIII',
        ]);

        $case = $this->caseFor(
            $investigator,
            docketedOn: '2026-03-01',
            status: CaseModel::STATUS_DOCKETED,
            attributes: ['case_title' => 'Alleged unlawful arrest', 'complexity_weight' => 4],
        );

        $csv = $this->export($investigator);

        $this->assertStringContainsString($case->docket_no, $csv);
        $this->assertStringContainsString('Alleged unlawful arrest', $csv);
        $this->assertStringContainsString(CaseModel::STATUS_DOCKETED, $csv);
        $this->assertStringContainsString($investigator->full_name, $csv);
        $this->assertStringContainsString('CHR Region VIII', $csv);
        $this->assertStringContainsString('2026-03-01', $csv);

        // Docketed 1 March, so day 30 is 31 March and day 120 is 29 June. The
        // timeline was created with only a date of docket, so both come from
        // CaseDeadlineService's fallback rather than a stored column.
        $this->assertStringContainsString('2026-03-31', $csv);
        $this->assertStringContainsString('2026-06-29', $csv);
    }

    public function test_a_stored_deadline_is_exported_ahead_of_the_computed_one(): void
    {
        $investigator = User::factory()->investigator()->create();

        $case = $this->caseFor($investigator, docketedOn: '2026-03-01');
        $case->timeline->update(['extension_30_days' => '2026-04-15']);

        $csv = $this->export($investigator);

        $this->assertStringContainsString('2026-04-15', $csv);
        $this->assertStringNotContainsString('2026-03-31', $csv);
    }

    public function test_a_case_with_no_timeline_exports_with_empty_date_cells(): void
    {
        $investigator = User::factory()->investigator()->create();

        $case = CaseModel::factory()->assignedTo($investigator)->create([
            'status' => CaseModel::STATUS_DOCKETED,
        ]);

        $row = $this->rowFor($this->export($investigator), $case->docket_no);

        // Docket no., title, status, investigator, region, weight, then four
        // date columns that have nothing to report.
        $this->assertSame(['', '', '', '', ''], array_slice($row, 6));
    }

    /**
     * The 60th day binds only torture cases and no case-type column exists, so
     * CaseDeadlineService gates it out of everything a user can see. A report
     * is no exception.
     */
    public function test_the_sixtieth_day_appears_nowhere_in_the_export(): void
    {
        if (CaseDeadlineService::SIXTY_DAY_ENABLED) {
            $this->markTestSkipped('The 60-day milestone has been enabled; this gate no longer applies.');
        }

        $investigator = User::factory()->investigator()->create();

        $this->caseFor($investigator, docketedOn: '2026-01-01');

        $csv = $this->export($investigator);

        $this->assertStringNotContainsString('60th day', $csv);
        $this->assertStringNotContainsString('RORP', $csv);
        $this->assertStringNotContainsString('submission_60th_day', $csv);

        // Docketed 1 January, so the 60th day would be 2 March if it leaked.
        $this->assertStringNotContainsString('2026-03-02', $csv);
    }

    // --------------------------------------------------------- spreadsheet

    /**
     * A case title is free text an intake form accepted. Excel executes a cell
     * that opens with =, +, - or @ the moment the file is opened.
     */
    public function test_a_title_that_looks_like_a_formula_is_neutralized(): void
    {
        $investigator = User::factory()->investigator()->create();

        $this->caseFor($investigator, attributes: [
            'case_title' => '=HYPERLINK("http://evil.test","click")',
        ]);

        $csv = $this->export($investigator);

        $this->assertStringContainsString('\'=HYPERLINK', $csv);
        $this->assertStringNotContainsString(',=HYPERLINK', $csv);
    }

    // --------------------------------------------------------------- audit

    public function test_the_download_is_audited_against_no_single_case(): void
    {
        $investigator = User::factory()->investigator()->create();

        $this->export($investigator);

        $entry = AuditLog::where('action_performed', AuditLog::ACTION_EXPORTED)->sole();

        $this->assertSame($investigator->id, $entry->user_id);
        $this->assertNull($entry->case_id);
    }

    public function test_a_rejected_filter_is_not_audited(): void
    {
        $investigator = User::factory()->investigator()->create();

        $this->actingAs($investigator)
            ->get(route('reports.export', ['from' => '2026-03-31', 'to' => '2026-03-01']))
            ->assertSessionHasErrors('to');

        $this->assertSame(0, AuditLog::where('action_performed', AuditLog::ACTION_EXPORTED)->count());
    }

    // ----------------------------------------------------------------- helpers

    /**
     * The exported file as a string, with the download closure run.
     *
     * @param  array<string, mixed>  $filters
     */
    private function export(User $user, array $filters = []): string
    {
        return $this->actingAs($user)
            ->get(route('reports.export', $filters))
            ->assertOk()
            ->streamedContent();
    }

    /**
     * A case with a timeline carrying only its date of docket.
     *
     * Deliberately not CaseTimelineFactory: that fills every derived column,
     * and these fixtures are about what the service computes when they are
     * empty.
     *
     * @param  array<string, mixed>  $attributes
     */
    private function caseFor(
        ?User $investigator = null,
        string $docketedOn = '2026-03-01',
        string $status = CaseModel::STATUS_DOCKETED,
        array $attributes = [],
    ): CaseModel {
        $case = CaseModel::factory()
            ->assignedTo($investigator ?? User::factory()->investigator()->create())
            ->create(array_merge(['status' => $status], $attributes));

        CaseTimeline::create([
            'case_id' => $case->id,
            'date_of_docket' => CarbonImmutable::parse($docketedOn),
        ]);

        return $case->load(['timeline', 'investigator']);
    }

    /**
     * One parsed row out of the exported file, found by its docket number.
     *
     * @return list<string>
     */
    private function rowFor(string $csv, string $docketNo): array
    {
        foreach (explode("\n", trim($csv)) as $line) {
            $fields = str_getcsv(trim($line, "\r"), escape: '');

            if (($fields[0] ?? null) === $docketNo) {
                return $fields;
            }
        }

        $this->fail("No row for docket {$docketNo} in the export.");
    }
}
