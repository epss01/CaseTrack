<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\CaseModel;
use App\Models\CaseTimeline;
use App\Models\Complainant;
use App\Models\Respondent;
use App\Models\User;
use App\Models\Victim;
use App\Services\CaseImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use Tests\TestCase;

class ChrMonitoringExportTest extends TestCase
{
    use RefreshDatabase;

    private array $files = [];

    protected function tearDown(): void
    {
        foreach ($this->files as $file) {
            @unlink($file);
        }
        parent::tearDown();
    }

    private function download(User $actor, array $filters = []): string
    {
        $response = $this->actingAs($actor)->get(route('reports.export-excel', $filters))->assertOk();
        $this->assertStringContainsString('.xlsx', $response->headers->get('content-disposition'));
        $path = tempnam(sys_get_temp_dir(), 'chr-');
        $this->files[] = $path;
        file_put_contents($path, $response->streamedContent());

        return $path;
    }

    private function caseFor(User $investigator, array $attributes = []): CaseModel
    {
        $case = CaseModel::factory()->assignedTo($investigator)->create(array_merge([
            'status' => CaseModel::STATUS_DOCKETED, 'incident_details' => 'Synthetic incident',
        ], $attributes));
        CaseTimeline::create(['case_id' => $case->id, 'date_of_docket' => '2026-01-05']);
        Victim::factory()->create(['case_id' => $case->id, 'name' => 'Synthetic victim']);
        Respondent::factory()->create(['case_id' => $case->id, 'name' => 'Synthetic respondent']);

        return $case;
    }

    public function test_roles_and_own_case_scope_apply_to_excel(): void
    {
        $this->get(route('reports.export-excel'))->assertRedirect(route('login'));
        $this->actingAs(User::factory()->admin()->create())->get(route('reports.export-excel'))->assertForbidden();
        $actor = User::factory()->investigator()->create();
        $own = $this->caseFor($actor, ['docket_no' => '000123']);
        $other = $this->caseFor(User::factory()->investigator()->create(), ['docket_no' => 'PRIVATE-OTHER']);
        $sheet = IOFactory::load($this->download($actor))->getActiveSheet();
        $this->assertSame($own->docket_no, $sheet->getCell('B12')->getValue());
        $this->assertStringNotContainsString($other->docket_no, json_encode($sheet->toArray()));
        $this->assertDatabaseHas('audit_logs', ['user_id' => $actor->id, 'action_performed' => AuditLog::ACTION_EXPORTED]);
    }

    public function test_template_people_actual_dates_and_import_compatibility(): void
    {
        $actor = User::factory()->investigator()->create();
        $case = $this->caseFor($actor, ['docket_no' => '000123', 'case_title' => '=1+1']);
        Victim::factory()->count(2)->create(['case_id' => $case->id]);
        Complainant::create(['case_id' => $case->id, 'name' => '@first']);
        Complainant::create(['case_id' => $case->id, 'name' => 'Second complainant']);
        $case->timeline->update([
            'date_extension_30_days_requested' => '2026-01-20',
            'date_submission_120th_day_rop' => '2026-04-20',
            'date_submission_60th_day_rorp' => '2026-03-01',
            'date_fir_submitted' => '2026-05-01',
            'date_submitted_to' => '=recipient',
        ]);
        $path = $this->download($actor);
        $sheet = IOFactory::load($path)->getActiveSheet();
        $this->assertSame('DOCKET NO.', $sheet->getCell('B8')->getValue());
        $this->assertSame('TIMELINE TRACKING', $sheet->getCell('O8')->getValue());
        $this->assertSame('landscape', $sheet->getPageSetup()->getOrientation());
        $this->assertSame([1, 11], $sheet->getPageSetup()->getRowsToRepeatAtTop());
        $this->assertSame('B12:B14', $sheet->getMergeCells()['B12:B14']);
        $this->assertArrayNotHasKey('C12:C14', $sheet->getMergeCells());
        $this->assertArrayNotHasKey('M12:M14', $sheet->getMergeCells());
        $this->assertSame('V8:V11', $sheet->getMergeCells()['V8:V11']);
        $this->assertSame('000123', $sheet->getCell('B12')->getValue());
        $this->assertSame(DataType::TYPE_STRING, $sheet->getCell('C12')->getDataType());
        $this->assertSame('=1+1', $sheet->getCell('C12')->getValue());
        $this->assertSame('Second complainant', $sheet->getCell('D13')->getValue());
        $this->assertNull($sheet->getCell('I13')->getValue() ?: null);
        $this->assertSame('2026-01-20', Date::excelToDateTimeObject($sheet->getCell('Q12')->getValue())->format('Y-m-d'));
        $this->assertSame('2026-04-20', Date::excelToDateTimeObject($sheet->getCell('S12')->getValue())->format('Y-m-d'));
        $this->assertSame('2026-05-01', Date::excelToDateTimeObject($sheet->getCell('U12')->getValue())->format('Y-m-d'));
        $this->assertSame('=recipient', $sheet->getCell('V12')->getValue());
        $this->assertNull($sheet->getCell('P12')->getValue());

        $importer = new CaseImportService;
        $parsed = $importer->parse($path);
        $result = $importer->validate($parsed, $actor);
        $this->assertCount(0, $result['ready']);
        $this->assertNotEmpty($result['errors']);
        $this->assertStringContainsString('already', strtolower(json_encode($result['errors'])));
        // Free the exported docket in the isolated test DB to check fresh intake.
        $case->update(['docket_no' => 'RENAMED-FOR-TEST']);
        $result = $importer->validate($parsed, $actor);
        $this->assertSame([], $result['errors']);
        $this->assertCount(1, $result['ready']);
    }

    public function test_office_workbook_has_unique_safe_sheets_and_filters(): void
    {
        $supervisor = User::factory()->supervisor()->create();
        $first = User::factory()->investigator()->create(['first_name' => 'Same/Name', 'last_name' => 'Staff']);
        $second = User::factory()->investigator()->create(['first_name' => 'Same/Name', 'last_name' => 'Staff']);
        $this->caseFor($first, ['docket_no' => 'A']);
        $this->caseFor($second, ['docket_no' => 'B']);
        $closed = $this->caseFor($first, ['docket_no' => 'C', 'status' => CaseModel::STATUS_CLOSED]);
        $deleted = $this->caseFor($first, ['docket_no' => 'D']);
        $deleted->delete();
        $book = IOFactory::load($this->download($supervisor));
        $this->assertSame(2, $book->getSheetCount());
        $this->assertCount(2, array_unique($book->getSheetNames()));
        foreach ($book->getSheetNames() as $name) {
            $this->assertLessThanOrEqual(31, mb_strlen($name));
            $this->assertStringNotContainsString('/', $name);
        }
        $this->assertSame('C', $book->getSheet(0)->getCell('B13')->getValue());
        $this->assertNull($book->getSheet(0)->getCell('B14')->getValue());
        $filtered = IOFactory::load($this->download($supervisor, ['status' => CaseModel::STATUS_CLOSED, 'from' => '2026-01-01', 'to' => '2026-01-31', 'investigator_id' => $first->id]));
        $this->assertSame(1, $filtered->getSheetCount());
        $this->assertSame($closed->docket_no, $filtered->getActiveSheet()->getCell('B12')->getValue());
    }

    public function test_empty_missing_dates_and_download_beyond_first_page(): void
    {
        $actor = User::factory()->investigator()->create();
        $empty = IOFactory::load($this->download($actor));
        $this->assertSame(1, $empty->getSheetCount());
        $this->assertNull($empty->getActiveSheet()->getCell('B12')->getValue());
        for ($i = 0; $i < 16; $i++) {
            $this->caseFor($actor, ['docket_no' => sprintf('%04d', $i)]);
        }
        $missing = CaseModel::factory()->assignedTo($actor)->create(['docket_no' => '9999']);
        $sheet = IOFactory::load($this->download($actor))->getActiveSheet();
        $this->assertSame($missing->docket_no, $sheet->getCell('B28')->getValue());
        $this->assertNull($sheet->getCell('O28')->getValue());
        $filtered = IOFactory::load($this->download($actor, ['from' => '2026-01-01']));
        $this->assertSame('0015', $filtered->getActiveSheet()->getCell('B27')->getValue());
        $this->assertNull($filtered->getActiveSheet()->getCell('B28')->getValue());
        $this->get(route('reports.index'))->assertOk()->assertSee('Download CHR Excel')->assertSee('CHR monitoring worksheet')->assertSee('Timeline tracking');
    }
}
