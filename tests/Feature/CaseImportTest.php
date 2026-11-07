<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\CaseModel;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

/**
 * The upload -> preview -> commit flow at /cases/import*.
 *
 * CaseImportServiceTest already covers parsing/validation in isolation;
 * this covers the HTTP surface: role gating, that a preview writes nothing,
 * that a commit actually creates cases with their people and timeline, that
 * a bad row doesn't block the rest of the file, and the audit trail a
 * role-scoped bulk-write action needs.
 */
class CaseImportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake();
    }

    private function csv(array $rows, string $name = 'import.csv'): UploadedFile
    {
        $content = '';
        foreach ($rows as $row) {
            $handle = fopen('php://temp', 'r+');
            fputcsv($handle, $row);
            rewind($handle);
            $content .= stream_get_contents($handle);
            fclose($handle);
        }

        return UploadedFile::fake()->createWithContent($name, $content);
    }

    // ------------------------------------------------------------ access

    public function test_a_guest_is_sent_to_the_login_page(): void
    {
        $this->get(route('cases.import.create'))->assertRedirect(route('login'));
    }

    public function test_an_investigator_can_open_import_but_cannot_commit_without_an_upload(): void
    {
        $investigator = User::factory()->investigator()->create();

        $this->actingAs($investigator)->get(route('cases.import.create'))->assertOk()->assertSee('Import into your own caseload.');
        $this->post(route('cases.import.store'))->assertRedirect(route('cases.import.create'))->assertSessionHasErrors('file');
    }

    public function test_an_admin_is_refused(): void
    {
        // Import writes case data and assigns investigators — deliberately
        // outside Admin's scope even though Admin can reach everything else
        // under /admin (CLAUDE.md, Roles / access rules).
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->get(route('cases.import.create'))->assertForbidden();
        $this->post(route('cases.import.preview'), ['file' => $this->csv([['Docket No.']])])->assertForbidden();
        $this->post(route('cases.import.store'))->assertForbidden();
    }

    // ------------------------------------------------------------- happy path

    private function ownCaseRows(string $assignee = ''): array
    {
        return [
            ['Docket No.', 'Title', 'Date of Docket', 'Investigator', 'Incident Details', 'Victim Name', 'Victim Status', 'Victim Sector', 'Respondent Name'],
            ['OWN-1', 'Own imported case', '2026-01-05', $assignee, 'Import test details.', 'Test Victim', 'Injured', 'Farmer', 'Test Respondent'],
        ];
    }

    public function test_investigator_csv_defaults_to_self_and_audits_the_import(): void
    {
        $actor = User::factory()->investigator()->create();
        $this->actingAs($actor)->post(route('cases.import.preview'), ['file' => $this->csv($this->ownCaseRows())])
            ->assertOk()->assertViewHas('result', fn ($result) => count($result['ready']) === 1 && $result['errors'] === []);
        $this->assertDatabaseCount('cases', 0);
        $this->post(route('cases.import.store'), ['investigator_id' => 999])->assertRedirect(route('cases.index'));
        $case = CaseModel::sole();
        $this->assertSame($actor->id, $case->investigator_id);
        $this->assertNotNull($case->timeline);
        $this->assertDatabaseHas('audit_logs', ['user_id' => $actor->id, 'case_id' => $case->id, 'action_performed' => AuditLog::ACTION_CREATE]);
        $this->assertDatabaseHas('audit_logs', ['user_id' => $actor->id, 'action_performed' => AuditLog::ACTION_IMPORTED]);
        Storage::assertMissing("imports/user-{$actor->id}.csv");
    }

    public function test_investigator_xlsx_accepts_their_username(): void
    {
        $actor = User::factory()->investigator()->create();
        $sheet = new Spreadsheet;
        $sheet->getActiveSheet()->fromArray($this->ownCaseRows($actor->username));
        $path = tempnam(sys_get_temp_dir(), 'import-test-');
        try {
            (new Xlsx($sheet))->save($path);
            $upload = UploadedFile::fake()->createWithContent('own.xlsx', file_get_contents($path));
            $this->actingAs($actor)->post(route('cases.import.preview'), ['file' => $upload])->assertOk();
            $this->post(route('cases.import.store'))->assertRedirect(route('cases.index'));
            $this->assertSame($actor->id, CaseModel::sole()->investigator_id);
        } finally {
            unlink($path);
            $sheet->disconnectWorksheets();
        }
    }

    public function test_investigator_accepts_their_unambiguous_full_name(): void
    {
        $actor = User::factory()->investigator()->create();
        $this->actingAs($actor)->post(route('cases.import.preview'), ['file' => $this->csv($this->ownCaseRows($actor->full_name))])
            ->assertOk()->assertViewHas('result', fn ($result) => count($result['ready']) === 1 && $result['errors'] === []);
    }

    public function test_investigator_rejects_other_unknown_and_ambiguous_assignees_even_on_direct_commit(): void
    {
        $actor = User::factory()->investigator()->create(['first_name' => 'Same', 'last_name' => 'Name']);
        $other = User::factory()->investigator()->create(['first_name' => 'Same', 'last_name' => 'Name']);
        foreach ([$other->username, 'No such account', $actor->full_name] as $assignee) {
            $this->actingAs($actor)->post(route('cases.import.preview'), ['file' => $this->csv($this->ownCaseRows($assignee))])
                ->assertOk()->assertViewHas('result', fn ($result) => $result['ready'] === [] && count($result['errors']) === 1);
            $this->post(route('cases.import.store'))->assertRedirect(route('cases.index'));
            $this->assertDatabaseCount('cases', 0);
        }
        $this->assertFalse(AuditLog::where('action_performed', AuditLog::ACTION_IMPORTED)->exists());
    }

    public function test_a_staged_upload_cannot_be_committed_by_another_user_or_with_a_swapped_path(): void
    {
        $owner = User::factory()->investigator()->create();
        $other = User::factory()->investigator()->create();
        $this->actingAs($owner)->post(route('cases.import.preview'), ['file' => $this->csv($this->ownCaseRows())])->assertOk();
        $this->actingAs($other)->post(route('cases.import.store'))->assertForbidden();
        $this->withSession(['case_import.user_id' => $other->id])->post(route('cases.import.store'))->assertForbidden();
        $this->assertDatabaseCount('cases', 0);
        Storage::assertExists("imports/user-{$owner->id}.csv");
    }

    public function test_confirmation_revalidates_assignment_after_a_supervisor_becomes_an_investigator(): void
    {
        $actor = User::factory()->supervisor()->create();
        $other = User::factory()->investigator()->create();
        $this->actingAs($actor)->post(route('cases.import.preview'), ['file' => $this->csv($this->ownCaseRows($other->username))])->assertOk();
        $actor->update(['role_id' => $other->role_id]);
        $this->actingAs($actor->fresh())->post(route('cases.import.store'))->assertRedirect(route('cases.index'));
        $this->assertDatabaseCount('cases', 0);
    }

    public function test_confirmation_refuses_a_user_who_has_become_an_admin(): void
    {
        $actor = User::factory()->investigator()->create();
        $this->actingAs($actor)->post(route('cases.import.preview'), ['file' => $this->csv($this->ownCaseRows())])->assertOk();
        $actor->update(['role_id' => Role::firstOrCreate(['role_name' => Role::ADMIN])->id]);
        $this->actingAs($actor->fresh())->post(route('cases.import.store'))->assertForbidden();
        $this->assertDatabaseCount('cases', 0);
    }

    public function test_case_and_audit_writes_roll_back_together(): void
    {
        $actor = User::factory()->investigator()->create();
        $this->actingAs($actor)->post(route('cases.import.preview'), ['file' => $this->csv($this->ownCaseRows())])->assertOk();
        AuditLog::creating(function (AuditLog $log) {
            if ($log->action_performed === AuditLog::ACTION_IMPORTED) {
                throw new \RuntimeException('Simulated audit failure');
            }
        });
        try {
            $this->post(route('cases.import.store'))->assertStatus(500);
            $this->assertDatabaseCount('cases', 0);
            $this->assertDatabaseCount('case_timelines', 0);
            $this->assertFalse(AuditLog::where('action_performed', AuditLog::ACTION_CREATE)->exists());
        } finally {
            AuditLog::flushEventListeners();
        }
    }

    public function test_a_supervisor_can_import_a_multi_row_file_with_two_victims_on_one_case(): void
    {
        $supervisor = User::factory()->supervisor()->create();
        $investigator = User::factory()->investigator()->create(['username' => 'jdc']);

        $file = $this->csv([
            ['Docket No.', 'Title', 'Date of Docket', 'Investigator', 'Incident Details', 'Complexity Weight', 'Victim Name', 'Victim Status', 'Victim Sector', 'Respondent Name'],
            ['CHR-2026-0001', 'Case One', '2026-01-05', 'jdc', 'Details of case one.', '4', 'Victim A', 'Injured', 'Farmer', 'Respondent A'],
            ['CHR-2026-0001', 'Case One', '2026-01-05', 'jdc', '', '', 'Victim B', 'Injured', 'Minor', ''],
        ]);

        $preview = $this->actingAs($supervisor)
            ->post(route('cases.import.preview'), ['file' => $file]);

        $preview->assertOk();
        $this->assertSame(0, CaseModel::count(), 'preview must not write anything');

        $commit = $this->actingAs($supervisor)->post(route('cases.import.store'));
        $commit->assertRedirect(route('cases.index'));

        $case = CaseModel::where('docket_no', 'CHR-2026-0001')->firstOrFail();
        $this->assertSame('Case One', $case->case_title);
        $this->assertSame(4, $case->complexity_weight);
        $this->assertSame($investigator->id, $case->investigator_id);
        $this->assertCount(2, $case->victims);
        $this->assertCount(1, $case->respondents);
        $this->assertSame('2026-01-05', $case->timeline->date_of_docket->toDateString());

        $this->assertTrue(AuditLog::where('action_performed', AuditLog::ACTION_CREATE)
            ->where('case_id', $case->id)->where('user_id', $supervisor->id)->exists());
        $this->assertTrue(AuditLog::where('action_performed', AuditLog::ACTION_IMPORTED)
            ->whereNull('case_id')->where('user_id', $supervisor->id)->exists());
    }

    public function test_a_bad_row_is_rejected_without_blocking_the_rest_of_the_file(): void
    {
        $supervisor = User::factory()->supervisor()->create();
        User::factory()->investigator()->create(['username' => 'jdc']);

        $file = $this->csv([
            ['Docket No.', 'Title', 'Date of Docket', 'Investigator', 'Incident Details', 'Victim Name', 'Victim Status', 'Victim Sector', 'Respondent Name'],
            ['CHR-2026-0001', 'Good Case', '2026-01-05', 'jdc', 'Details.', 'Victim A', 'Injured', 'Farmer', 'Respondent A'],
            ['CHR-2026-0002', 'Bad Case', 'not-a-date', 'jdc', 'Details.', 'Victim B', 'Injured', 'Farmer', 'Respondent B'],
        ]);

        $preview = $this->actingAs($supervisor)->post(route('cases.import.preview'), ['file' => $file]);
        $preview->assertOk();
        $preview->assertViewHas('result', function (array $result) {
            return count($result['ready']) === 1 && count($result['errors']) === 1;
        });

        $this->actingAs($supervisor)->post(route('cases.import.store'));

        $this->assertSame(1, CaseModel::count());
        $this->assertTrue(CaseModel::where('docket_no', 'CHR-2026-0001')->exists());
        $this->assertFalse(CaseModel::where('docket_no', 'CHR-2026-0002')->exists());
    }

    public function test_a_docket_no_already_in_the_database_is_rejected(): void
    {
        $supervisor = User::factory()->supervisor()->create();
        $investigator = User::factory()->investigator()->create(['username' => 'jdc']);
        $existing = CaseModel::factory()->assignedTo($investigator)->create(['docket_no' => 'CHR-2026-0001']);
        $existing->timeline()->create(['date_of_docket' => '2026-01-01']);

        $file = $this->csv([
            ['Docket No.', 'Title', 'Date of Docket', 'Investigator', 'Incident Details', 'Victim Name', 'Victim Status', 'Victim Sector', 'Respondent Name'],
            ['CHR-2026-0001', 'Duplicate', '2026-01-05', 'jdc', 'Details.', 'Victim A', 'Injured', 'Farmer', 'Respondent A'],
        ]);

        $preview = $this->actingAs($supervisor)->post(route('cases.import.preview'), ['file' => $file]);
        $preview->assertViewHas('result', function (array $result) {
            return count($result['ready']) === 0 && count($result['errors']) === 1;
        });
    }

    public function test_an_unresolvable_investigator_name_is_rejected(): void
    {
        $supervisor = User::factory()->supervisor()->create();

        $file = $this->csv([
            ['Docket No.', 'Title', 'Date of Docket', 'Investigator', 'Incident Details', 'Victim Name', 'Victim Status', 'Victim Sector', 'Respondent Name'],
            ['CHR-2026-0001', 'Case One', '2026-01-05', 'Nobody Here', 'Details.', 'Victim A', 'Injured', 'Farmer', 'Respondent A'],
        ]);

        $preview = $this->actingAs($supervisor)->post(route('cases.import.preview'), ['file' => $file]);
        $preview->assertViewHas('result', function (array $result) {
            return count($result['ready']) === 0
                && str_contains($result['errors'][0]['messages'][0], 'Nobody Here');
        });
    }

    public function test_a_malformed_file_is_reported_and_nothing_is_created(): void
    {
        $supervisor = User::factory()->supervisor()->create();

        $file = $this->csv([
            ['Title', 'Investigator'], // missing Docket No. / Date of Docket
            ['Case One', 'jdc'],
        ]);

        $response = $this->actingAs($supervisor)->post(route('cases.import.preview'), ['file' => $file]);

        $response->assertRedirect();
        $response->assertSessionHasErrors('file');
        $this->assertSame(0, CaseModel::count());
    }
}
