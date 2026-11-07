<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\CaseModel;
use App\Models\User;
use App\Services\CaseDeadlineService;
use App\Services\CaseImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class ChrCaseImportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake();
    }

    private function workbook(?callable $change = null): UploadedFile
    {
        $book = new Spreadsheet;
        $sheet = $book->getActiveSheet();
        $sheet->setCellValue('B1', 'MONITORING OF ASSIGNED CASES (NEW AND PENDING)');
        $sheet->setCellValue('A6', 'NAME OF INVESTIGATOR: __________________');
        $sheet->fromArray(['NO.', 'DOCKET NO.', 'CASE TITLE', 'COMPLAINANT', 'VICTIM/S', null, null, null, 'RESPONDENT/S', null, null, null, 'DATE/PLACE OF INCIDENT', 'SOURCE OF INFORMATION', 'TIMELINE TRACKING', null, null, null, null, 'FIR SUBMISSION', null, 'Date Submitted to '], null, 'A8');
        $sheet->fromArray(['DATE OF DOCKET', 'DATE OF SUBMISSION OF INITIAL REPORT (ROP)', 'REQUEST FOR 30-DAYS EXTENSION (INITIAL REPORT) (ROP)', 'DATE OF SUBMISSION 60TH DAY (RORP) (TORTURE CASES)', 'DATE OF SUBMISSION 120TH DAY (ROP)', 'TARGET DATE OF FIR', 'DATE OF FIR SUBMITTED'], null, 'O9');
        $sheet->fromArray(['NAME', 'AGE', 'STATUS', 'SECTOR', 'NAME', 'AGE', 'STATUS', 'SECTOR'], null, 'E10');
        foreach (['B1:M3', 'A8:A11', 'B8:B11', 'C8:C11', 'D8:D11', 'E8:H9', 'I8:L9', 'M8:M11', 'N8:N11', 'O8:S8', 'T8:U8', 'O9:O11', 'P9:P11', 'Q9:Q11', 'R9:R11', 'S9:S11', 'T9:T11', 'U9:U11', 'E10:E11', 'F10:F11', 'G10:G11', 'H10:H11', 'I10:I11', 'J10:J11', 'K10:K11', 'L10:L11'] as $range) {
            $sheet->mergeCells($range);
        }
        $sheet->fromArray([1, 8888, 'Synthetic case', 'Complainant', 'Victim One', 25, 'Injured', 'Worker', 'Respondent One', 30, 'Officer', 'Public', '2026-01-01, Test place', 'Referral', Date::PHPToExcel(new \DateTime('2026-01-05')), '2026-01-10', '2026-01-15', '2026-02-01', '2026-04-01', '2026-04-05', null, 'Regional office'], null, 'A12');
        $sheet->setCellValue('A14', 3);
        $change?->__invoke($sheet, $book);
        $path = tempnam(sys_get_temp_dir(), 'chr-test-');
        try {
            (new Xlsx($book))->save($path);

            return UploadedFile::fake()->createWithContent('chr.xlsx', file_get_contents($path));
        } finally {
            $book->disconnectWorksheets();
            unlink($path);
        }
    }

    public function test_chr_workbook_imports_people_and_actuals_without_overwriting_deadlines(): void
    {
        $supervisor = User::factory()->supervisor()->create();
        $investigator = User::factory()->investigator()->create();
        $upload = $this->workbook(function ($sheet) {
            $sheet->mergeCells('B12:B13');
            $sheet->fromArray(['Victim Two', 40, 'Injured', 'Worker'], null, 'E13');
        });
        $this->actingAs($supervisor)->post(route('cases.import.preview'), ['file' => $upload, 'investigator_id' => $investigator->id])
            ->assertOk()->assertViewHas('result', fn ($r) => count($r['ready']) === 1 && $r['errors'] === []);
        $this->assertDatabaseCount('cases', 0);
        $this->post(route('cases.import.store'))->assertRedirect(route('cases.index'));
        $case = CaseModel::sole();
        $this->assertSame('8888', $case->docket_no);
        $this->assertSame($investigator->id, $case->investigator_id);
        $this->assertCount(2, $case->victims);
        $this->assertCount(1, $case->respondents);
        $this->assertCount(1, $case->complainants);
        $this->assertSame('2026-01-15', $case->timeline->date_extension_30_days_requested->format('Y-m-d'));
        $this->assertSame('2026-04-01', $case->timeline->date_submission_120th_day_rop->format('Y-m-d'));
        $this->assertSame('2026-02-01', $case->timeline->date_submission_60th_day_rorp->format('Y-m-d'));
        $this->assertSame('2026-04-05', $case->timeline->target_date_fir->format('Y-m-d'));
        $this->assertNull($case->timeline->extension_30_days);
        $this->assertNull($case->timeline->submission_120th_day);
        $this->assertNull($case->timeline->date_fir_submitted);
        $this->assertNull($case->is_torture_case);
        $this->assertDatabaseHas('audit_logs', ['case_id' => $case->id, 'action_performed' => AuditLog::ACTION_CREATE]);
        $this->assertDatabaseHas('audit_logs', ['action_performed' => AuditLog::ACTION_IMPORTED]);
    }

    public function test_unmerged_blank_docket_is_rejected_with_original_row_number(): void
    {
        $actor = User::factory()->investigator()->create();
        $upload = $this->workbook(fn ($sheet) => $sheet->setCellValue('E13', 'Orphan victim'));
        $this->actingAs($actor)->post(route('cases.import.preview'), ['file' => $upload])
            ->assertOk()->assertViewHas('result', fn ($r) => count($r['ready']) === 1 && $r['errors'][0]['lines'] === [13]);
    }

    public function test_workbook_investigator_is_not_overridden_and_unknown_names_reject(): void
    {
        $actor = User::factory()->supervisor()->create();
        $named = User::factory()->investigator()->create();
        $fallback = User::factory()->investigator()->create();
        foreach ([$named->username, 'No matching investigator'] as $name) {
            $upload = $this->workbook(fn ($sheet) => $sheet->setCellValue('A6', 'NAME OF INVESTIGATOR: '.$name));
            $this->actingAs($actor)->post(route('cases.import.preview'), ['file' => $upload, 'investigator_id' => $fallback->id])
                ->assertOk()->assertViewHas('result', fn ($r) => $name === $named->username
                    ? $r['ready'][0]['investigator_id'] === $named->id
                    : count($r['errors']) === 1 && $r['ready'] === []);
        }
    }

    public function test_fallback_is_revalidated_and_cannot_be_posted_by_investigator(): void
    {
        $actor = User::factory()->supervisor()->create();
        $fallback = User::factory()->investigator()->create();
        $this->actingAs($actor)->post(route('cases.import.preview'), ['file' => $this->workbook(), 'investigator_id' => $fallback->id])->assertOk();
        $fallback->update(['is_active' => false]);
        $this->post(route('cases.import.store'))->assertRedirect(route('cases.index'));
        $this->assertDatabaseCount('cases', 0);
        $actor = User::factory()->investigator()->create();
        $this->actingAs($actor)->post(route('cases.import.preview'), ['file' => $this->workbook(), 'investigator_id' => $fallback->id])
            ->assertSessionHasErrors('investigator_id');
    }

    public function test_incomplete_case_reports_data_errors_instead_of_missing_headers(): void
    {
        $upload = $this->workbook(function ($sheet) {
            foreach (range('D', 'V') as $column) {
                $sheet->setCellValue($column.'12', null);
            }
        });
        $this->actingAs(User::factory()->investigator()->create())->post(route('cases.import.preview'), ['file' => $upload])
            ->assertOk()->assertViewHas('result', fn ($r) => $r['ready'] === [] && $r['errors'][0]['lines'] === [12]
                && str_contains(implode(' ', $r['errors'][0]['messages']), 'Date of Docket'));
    }

    public function test_malformed_chr_header_is_reported(): void
    {
        $upload = $this->workbook(fn ($sheet) => $sheet->setCellValue('O9', 'Unrecognized docket date'));
        $this->actingAs(User::factory()->investigator()->create())->post(route('cases.import.preview'), ['file' => $upload])
            ->assertSessionHasErrors('file');
    }

    public function test_only_active_worksheet_is_parsed(): void
    {
        $upload = $this->workbook(function ($sheet, $book) {
            $book->createSheet()->setCellValue('A1', 'Not the active worksheet');
        });
        $rows = (new CaseImportService)->parse($upload->getPathname());
        $this->assertCount(1, $rows);
        $this->assertSame('8888', $rows[0]['docket_no']);
    }

    public function test_upload_size_boundary_and_help_agree(): void
    {
        $actor = User::factory()->investigator()->create();
        foreach ([5121, 20480, 20481] as $size) {
            $upload = UploadedFile::fake()->createWithContent('chr.csv', "Docket No.,Title,Date of Docket\n")->size($size);
            $response = $this->actingAs($actor)->post(route('cases.import.preview'), ['file' => $upload]);
            $size <= 20480 ? $response->assertOk() : $response->assertSessionHasErrors('file');
        }
        $this->get(route('cases.import.create'))->assertSee('up to 20 MB');
    }

    public function test_new_timeline_dates_validate_edit_display_and_do_not_change_deadlines(): void
    {
        $actor = User::factory()->investigator()->create();
        $this->actingAs($actor)->post(route('cases.import.preview'), ['file' => $this->workbook()])->assertOk();
        $this->post(route('cases.import.store'));
        $case = CaseModel::sole();
        $before = (new CaseDeadlineService)->statusesFor($case->timeline);
        $this->put(route('cases.timeline.update', $case), [
            'date_of_docket' => '2026-01-05',
            'date_extension_30_days_requested' => '2026-01-04',
            'date_submission_120th_day_rop' => 'not-a-date',
        ])->assertSessionHasErrors(['date_extension_30_days_requested', 'date_submission_120th_day_rop']);
        $this->put(route('cases.timeline.update', $case), [
            'date_of_docket' => '2026-01-05',
            'date_extension_30_days_requested' => '2026-02-10',
            'date_submission_120th_day_rop' => '2026-03-10',
        ])->assertSessionHasNoErrors()->assertRedirect(route('cases.show', $case));
        $this->assertEquals($before, (new CaseDeadlineService)->statusesFor($case->fresh()->timeline));
        $this->get(route('cases.show', $case))->assertSee('10 Feb 2026')->assertSee('10 Mar 2026');
        $this->get(route('cases.timeline.edit', $case))->assertSee('2026-02-10')->assertSee('2026-03-10');
        $this->assertDatabaseHas('audit_logs', ['case_id' => $case->id, 'action_performed' => AuditLog::ACTION_TIMELINE_UPDATE]);
    }

    public function test_fallback_cannot_survive_loss_of_supervisor_role(): void
    {
        $actor = User::factory()->supervisor()->create();
        $other = User::factory()->investigator()->create();
        $this->actingAs($actor)->post(route('cases.import.preview'), ['file' => $this->workbook(), 'investigator_id' => $other->id])->assertOk();
        $actor->forceFill(['role_id' => $other->role_id])->save();
        $this->actingAs($actor->fresh())->post(route('cases.import.store'))->assertForbidden();
        $this->assertDatabaseCount('cases', 0);
    }

    public function test_php_post_limit_has_a_recoverable_error_page(): void
    {
        $this->call('POST', route('cases.import.preview'), [], [], [], ['CONTENT_LENGTH' => 30 * 1024 * 1024])
            ->assertStatus(413)->assertSee('Upload too large')->assertSee('20 MB');
    }
}
