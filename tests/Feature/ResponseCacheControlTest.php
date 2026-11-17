<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\CaseModel;
use App\Models\CaseTimeline;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ResponseCacheControlTest extends TestCase
{
    use RefreshDatabase;

    private function caseFor(User $actor): CaseModel
    {
        $case = CaseModel::factory()->assignedTo($actor)->create(['status' => CaseModel::STATUS_DOCKETED]);
        CaseTimeline::create(['case_id' => $case->id, 'date_of_docket' => '2026-01-05']);

        return $case;
    }

    public function test_case_report_and_account_pages_prevent_response_storage(): void
    {
        $investigator = User::factory()->investigator()->create();
        $case = $this->caseFor($investigator);
        $supervisor = User::factory()->supervisor()->create();
        $admin = User::factory()->admin()->create();

        $pages = [
            [$investigator, [
                '/home', '/cases', '/cases/create', "/cases/{$case->id}", "/cases/{$case->id}/edit",
                "/cases/{$case->id}/timeline/edit", '/cases/import', '/alerts', '/reports',
                "/reports/cases/{$case->id}", '/reports/analytics', '/reports/analytics/case-profiles',
            ]],
            [$supervisor, ['/home', '/workload', "/cases/{$case->id}/reassign", "/cases/{$case->id}/delete"]],
            [$admin, ['/home', '/admin/registrations', '/admin/users', '/admin/audit-logs']],
        ];

        foreach ($pages as [$actor, $urls]) {
            foreach ($urls as $url) {
                $this->actingAs($actor)->get($url)->assertOk()->assertHeader('Cache-Control', 'no-store, private');
            }
        }

        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_import_preview_and_confirmation_keep_their_workflow_and_cache_header(): void
    {
        Storage::fake();
        $actor = User::factory()->investigator()->create();
        $file = UploadedFile::fake()->createWithContent('synthetic.csv',
            "Docket No.,Title,Date of Docket,Incident Details,Victim Name,Victim Status,Victim Sector,Respondent Name\n".
            'SYNTHETIC-CACHE-1,Synthetic case,2026-01-05,Synthetic details,Synthetic victim,Test,Test,Synthetic respondent'
        );

        $this->actingAs($actor)->post(route('cases.import.preview'), ['file' => $file])
            ->assertOk()->assertHeader('Cache-Control', 'no-store, private');
        $this->assertDatabaseCount('cases', 0);

        $this->post(route('cases.import.store'))
            ->assertRedirect(route('cases.index'))->assertHeader('Cache-Control', 'no-store, private');
        $this->assertDatabaseCount('cases', 1);
        $this->assertDatabaseHas('audit_logs', ['action_performed' => AuditLog::ACTION_IMPORTED]);
    }

    public function test_streamed_csv_and_xlsx_keep_their_contents_headers_and_export_audits(): void
    {
        $actor = User::factory()->investigator()->create();
        $case = $this->caseFor($actor);
        $this->actingAs($actor);

        $csv = $this->get(route('reports.export'))->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private')->assertHeader('Content-Type', 'text/csv; charset=utf-8');
        $this->assertStringContainsString('.csv', $csv->headers->get('Content-Disposition'));
        $this->assertStringContainsString($case->docket_no, $csv->streamedContent());

        $xlsx = $this->get(route('reports.export-excel'))->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $this->assertStringContainsString('.xlsx', $xlsx->headers->get('Content-Disposition'));
        $this->assertStringStartsWith('PK', $xlsx->streamedContent());
        $this->assertSame(2, AuditLog::where('action_performed', AuditLog::ACTION_EXPORTED)->count());
    }

    public function test_redirects_denials_validation_errors_and_logout_keep_their_responses(): void
    {
        $this->get('/cases')->assertRedirect(route('login'))->assertHeader('Cache-Control', 'no-store, private');
        $this->get('/login')->assertOk()->assertHeader('Cache-Control', 'no-store, private');

        $owner = User::factory()->investigator()->create();
        $case = $this->caseFor($owner);
        $prober = User::factory()->investigator()->create();
        $this->actingAs($prober)->get(route('cases.show', $case))
            ->assertForbidden()->assertHeader('Cache-Control', 'no-store, private')->assertDontSee($case->docket_no);
        $this->assertDatabaseHas('audit_logs', ['user_id' => $prober->id, 'action_performed' => AuditLog::ACTION_ACCESS_DENIED]);

        $this->post(route('cases.store'), [])
            ->assertRedirect()->assertSessionHasErrors('docket_no')->assertHeader('Cache-Control', 'no-store, private');
        $this->postJson(route('cases.store'), [])
            ->assertUnprocessable()->assertJsonValidationErrors('docket_no')->assertHeader('Cache-Control', 'no-store, private');

        $this->post('/logout')->assertRedirect('/')->assertHeader('Cache-Control', 'no-store, private');
        $this->assertGuest();
        $this->get(route('cases.show', $case))
            ->assertRedirect(route('login'))->assertHeader('Cache-Control', 'no-store, private');
    }

    public function test_role_denials_account_deactivation_and_global_errors_are_not_cacheable(): void
    {
        $admin = User::factory()->admin()->create();
        $this->actingAs($admin)->get('/cases')->assertForbidden()->assertHeader('Cache-Control', 'no-store, private');
        $admin->update(['is_active' => false]);
        $this->get('/home')->assertRedirect(route('login'))->assertHeader('Cache-Control', 'no-store, private');
        $this->assertGuest();

        $this->get('/synthetic-missing-page')->assertNotFound()->assertHeader('Cache-Control', 'no-store, private');
        Route::get('/synthetic-cache-error', fn () => abort(500));
        $this->get('/synthetic-cache-error')->assertStatus(500)->assertHeader('Cache-Control', 'no-store, private');
    }
}
