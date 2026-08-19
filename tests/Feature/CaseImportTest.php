<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\CaseModel;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

/**
 * The upload -> preview -> commit flow at /cases/import*.
 *
 * CaseImportServiceTest already covers parsing/validation in isolation;
 * this covers the HTTP surface: role gating, that a preview writes nothing,
 * that a commit actually creates cases with their people and timeline, that
 * a bad row doesn't block the rest of the file, and the audit trail a
 * Supervisor-only bulk-write action needs.
 */
class CaseImportTest extends TestCase
{
    use RefreshDatabase;

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

    public function test_an_investigator_is_refused(): void
    {
        $investigator = User::factory()->investigator()->create();

        $this->actingAs($investigator)->get(route('cases.import.create'))->assertForbidden();
        $this->actingAs($investigator)->post(route('cases.import.preview'), ['file' => $this->csv([['Docket No.']])])->assertForbidden();
        $this->actingAs($investigator)->post(route('cases.import.store'))->assertForbidden();
    }

    public function test_an_admin_is_refused(): void
    {
        // Import writes case data and assigns investigators — deliberately
        // outside Admin's scope even though Admin can reach everything else
        // under /admin (CLAUDE.md, Roles / access rules).
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->get(route('cases.import.create'))->assertForbidden();
    }

    // ------------------------------------------------------------- happy path

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
        $existing = \App\Models\CaseModel::factory()->assignedTo($investigator)->create(['docket_no' => 'CHR-2026-0001']);
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
