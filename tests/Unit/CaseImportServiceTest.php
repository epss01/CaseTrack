<?php

namespace Tests\Unit;

use App\Http\Controllers\ReportController;
use App\Models\Role;
use App\Models\User;
use App\Services\CaseImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use ReflectionClass;
use Tests\TestCase;

/**
 * CaseImportService, in isolation from the upload/preview/commit HTTP flow
 * (that's CaseImportTest). Covers the parts most likely to silently drift or
 * misparse real-world data: the header-to-column mapping staying in step
 * with the export contract, the export's formula-guard escape being undone
 * on the way back in, Excel-serial vs. free-text dates both normalizing the
 * same way, and Option B's row-collapsing (several rows sharing one Docket
 * No. becoming one case with several people, or a genuine disagreement
 * between rows being rejected instead of silently picking one).
 *
 * Uses RefreshDatabase because validate() resolves the Investigator column
 * against real users.* rows.
 */
class CaseImportServiceTest extends TestCase
{
    use RefreshDatabase;

    /** @var list<string> */
    private array $tempFiles = [];

    protected function tearDown(): void
    {
        foreach ($this->tempFiles as $path) {
            @unlink($path);
        }

        parent::tearDown();
    }

    // ------------------------------------------------------- column contract

    /**
     * CaseImportService::CASE_COLUMNS is a hand-kept mirror of
     * ReportController::columns()'s labels (minus Office Region, which has
     * nowhere to land on import — see the class docblock). This is the
     * guard against the two drifting apart silently.
     */
    public function test_case_column_labels_match_the_export_contract_minus_office_region(): void
    {
        $reportController = (new ReflectionClass(ReportController::class))->newInstanceWithoutConstructor();
        $columns = (new ReflectionClass(ReportController::class))->getMethod('columns');
        $exportLabels = array_map(fn (array $column) => $column['label'], $columns->invoke($reportController));

        $this->assertEqualsCanonicalizing(
            array_diff($exportLabels, ['Office Region']),
            CaseImportService::caseColumnLabels()
        );
    }

    // ------------------------------------------------------------------ parse()

    public function test_parse_rejects_a_file_missing_a_required_column(): void
    {
        $path = $this->writeCsv([
            ['Title', 'Date of Docket'],
            ['A Case', '2026-01-01'],
        ]);

        $this->expectException(\RuntimeException::class);

        (new CaseImportService)->parse($path);
    }

    public function test_parse_strips_the_export_leading_apostrophe_escape(): void
    {
        $path = $this->writeCsv([
            ['Docket No.', 'Title', 'Date of Docket'],
            ['DK-1', "'=SUM(A1)", '2026-01-01'],
        ]);

        $rows = (new CaseImportService)->parse($path);

        $this->assertSame('=SUM(A1)', $rows[0]['case_title']);
    }

    public function test_parse_leaves_an_ordinary_leading_apostrophe_alone(): void
    {
        // Only "'" followed by = + - @ is the export's escape; any other
        // leading apostrophe (e.g. a name) is real data.
        $path = $this->writeCsv([
            ['Docket No.', 'Title', 'Date of Docket'],
            ['DK-1', "'Twas a case", '2026-01-01'],
        ]);

        $rows = (new CaseImportService)->parse($path);

        $this->assertSame("'Twas a case", $rows[0]['case_title']);
    }

    public function test_parse_normalizes_a_free_text_date(): void
    {
        $path = $this->writeCsv([
            ['Docket No.', 'Title', 'Date of Docket'],
            ['DK-1', 'A Case', 'March 5, 2026'],
        ]);

        $rows = (new CaseImportService)->parse($path);

        $this->assertSame('2026-03-05', $rows[0]['date_of_docket']);
    }

    public function test_parse_normalizes_an_excel_serial_date(): void
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->fromArray(['Docket No.', 'Title', 'Date of Docket'], null, 'A1');
        $sheet->setCellValue('A2', 'DK-1');
        $sheet->setCellValue('B2', 'A Case');
        $sheet->setCellValue('C2', ExcelDate::dateTimeToExcel(new \DateTime('2026-03-05')));

        $path = $this->writeXlsx($spreadsheet);

        $rows = (new CaseImportService)->parse($path);

        $this->assertSame('2026-03-05', $rows[0]['date_of_docket']);
    }

    public function test_parse_skips_a_fully_blank_row(): void
    {
        $path = $this->writeCsv([
            ['Docket No.', 'Title', 'Date of Docket'],
            ['DK-1', 'A Case', '2026-01-01'],
            ['', '', ''],
            ['DK-2', 'Another Case', '2026-01-02'],
        ]);

        $rows = (new CaseImportService)->parse($path);

        $this->assertCount(2, $rows);
    }

    // --------------------------------------------------------------- validate()

    public function test_two_rows_sharing_a_docket_no_collapse_into_one_case_with_two_victims(): void
    {
        $investigator = User::factory()->investigator()->create(['username' => 'jdc']);

        $path = $this->writeCsv([
            ['Docket No.', 'Title', 'Date of Docket', 'Investigator', 'Incident Details', 'Victim Name', 'Victim Status', 'Victim Sector', 'Respondent Name'],
            ['DK-1', 'A Case', '2026-01-01', 'jdc', 'Details.', 'Victim One', 'Alive', 'Adult', 'Respondent One'],
            ['DK-1', 'A Case', '2026-01-01', 'jdc', '', 'Victim Two', 'Alive', 'Minor', ''],
        ]);

        $importer = new CaseImportService;
        $result = $importer->validate($importer->parse($path));

        $this->assertCount(1, $result['ready']);
        $this->assertCount(2, $result['ready'][0]['victims']);
        $this->assertCount(1, $result['ready'][0]['respondents']);
        $this->assertSame([], $result['errors']);
    }

    public function test_rows_sharing_a_docket_no_but_disagreeing_on_a_case_field_are_rejected(): void
    {
        $investigator = User::factory()->investigator()->create(['username' => 'jdc']);

        $path = $this->writeCsv([
            ['Docket No.', 'Title', 'Date of Docket', 'Investigator', 'Victim Name', 'Respondent Name'],
            ['DK-1', 'A Case', '2026-01-01', 'jdc', 'Victim One', 'Respondent One'],
            ['DK-1', 'A Different Title', '2026-01-01', 'jdc', 'Victim Two', ''],
        ]);

        $importer = new CaseImportService;
        $result = $importer->validate($importer->parse($path));

        $this->assertSame([], $result['ready']);
        $this->assertCount(1, $result['errors']);
        $this->assertStringContainsString('Title', $result['errors'][0]['messages'][0]);
    }

    public function test_an_unresolvable_investigator_is_rejected_with_a_reason(): void
    {
        $path = $this->writeCsv([
            ['Docket No.', 'Title', 'Date of Docket', 'Investigator', 'Victim Name', 'Respondent Name'],
            ['DK-1', 'A Case', '2026-01-01', 'nobody', 'Victim One', 'Respondent One'],
        ]);

        $importer = new CaseImportService;
        $result = $importer->validate($importer->parse($path));

        $this->assertSame([], $result['ready']);
        $this->assertStringContainsString('nobody', $result['errors'][0]['messages'][0]);
    }

    public function test_an_ambiguous_full_name_is_rejected_rather_than_guessed(): void
    {
        User::factory()->investigator()->create(['username' => 'jdc1', 'first_name' => 'Juan', 'last_name' => 'Cruz']);
        User::factory()->investigator()->create(['username' => 'jdc2', 'first_name' => 'Juan', 'last_name' => 'Cruz']);

        $path = $this->writeCsv([
            ['Docket No.', 'Title', 'Date of Docket', 'Investigator', 'Victim Name', 'Respondent Name'],
            ['DK-1', 'A Case', '2026-01-01', 'Juan Cruz', 'Victim One', 'Respondent One'],
        ]);

        $importer = new CaseImportService;
        $result = $importer->validate($importer->parse($path));

        $this->assertSame([], $result['ready']);
        $this->assertStringContainsString('more than one account', $result['errors'][0]['messages'][0]);
    }

    public function test_complexity_weight_defaults_to_three_when_missing(): void
    {
        User::factory()->investigator()->create(['username' => 'jdc']);

        $path = $this->writeCsv([
            ['Docket No.', 'Title', 'Date of Docket', 'Investigator', 'Incident Details', 'Victim Name', 'Victim Status', 'Victim Sector', 'Respondent Name'],
            ['DK-1', 'A Case', '2026-01-01', 'jdc', 'Details.', 'Victim One', 'Alive', 'Adult', 'Respondent One'],
        ]);

        $importer = new CaseImportService;
        $result = $importer->validate($importer->parse($path));

        $this->assertSame(3, $result['ready'][0]['complexity_weight']);
        $this->assertSame(1, $result['defaulted_weight']);
    }

    public function test_a_120th_day_before_the_date_of_docket_is_rejected(): void
    {
        User::factory()->investigator()->create(['username' => 'jdc']);

        $path = $this->writeCsv([
            ['Docket No.', 'Title', 'Date of Docket', 'Investigator', 'Victim Name', 'Respondent Name', '120-day Deadline'],
            ['DK-1', 'A Case', '2026-06-01', 'jdc', 'Victim One', 'Respondent One', '2026-01-01'],
        ]);

        $importer = new CaseImportService;
        $result = $importer->validate($importer->parse($path));

        $this->assertSame([], $result['ready']);
        $this->assertNotEmpty($result['errors']);
    }

    public function test_a_row_with_no_docket_no_is_reported_by_line_and_not_grouped(): void
    {
        User::factory()->investigator()->create(['username' => 'jdc']);

        $path = $this->writeCsv([
            ['Docket No.', 'Title', 'Date of Docket', 'Investigator', 'Victim Name', 'Respondent Name'],
            ['', 'A Case', '2026-01-01', 'jdc', 'Victim One', 'Respondent One'],
        ]);

        $importer = new CaseImportService;
        $result = $importer->validate($importer->parse($path));

        $this->assertSame([], $result['ready']);
        $this->assertSame([2], $result['errors'][0]['lines']);
    }

    // --------------------------------------------------------------------- helpers

    private function writeCsv(array $rows): string
    {
        $path = sys_get_temp_dir().'/'.uniqid('import_test_').'.csv';
        $handle = fopen($path, 'w');
        foreach ($rows as $row) {
            fputcsv($handle, $row);
        }
        fclose($handle);

        $this->tempFiles[] = $path;

        return $path;
    }

    private function writeXlsx(Spreadsheet $spreadsheet): string
    {
        $path = sys_get_temp_dir().'/'.uniqid('import_test_').'.xlsx';
        (new Xlsx($spreadsheet))->save($path);

        $this->tempFiles[] = $path;

        return $path;
    }
}
