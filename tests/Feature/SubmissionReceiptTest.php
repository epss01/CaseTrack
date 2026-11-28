<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\CaseModel;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class SubmissionReceiptTest extends TestCase
{
    use RefreshDatabase;

    private User $investigator;

    private User $supervisor;

    private User $admin;

    private User $other;

    private CaseModel $case;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake();
        $this->investigator = User::factory()->investigator()->create();
        $this->supervisor = User::factory()->supervisor()->create();
        $this->admin = User::factory()->admin()->create();
        $this->other = User::factory()->investigator()->create();
        $this->case = CaseModel::factory()->assignedTo($this->investigator)->create(['status' => CaseModel::STATUS_DOCKETED]);
        $this->case->timeline()->create(['date_of_docket' => '2026-01-01']);
    }

    public static function actions(): array
    {
        return array_map(fn ($action) => [$action], [
            'create', 'edit', 'delete', 'close', 'timeline', 'reassign', 'import', 'rating',
            'approve', 'reject', 'role', 'active', 'password', 'register',
        ]);
    }

    #[DataProvider('actions')]
    public function test_completed_actions_replay_without_repeating_any_mutation_or_audit(string $action): void
    {
        [$actor, $method, $url, $payload] = $this->action($action);
        if ($actor) {
            $this->actingAs($actor);
        } else {
            $this->withSession([]);
            $this->withCookie(config('session.cookie'), session()->getId());
        }
        $payload['_submission_id'] = (string) Str::uuid();
        $first = $this->{strtolower($method)}($url, $payload)->assertRedirect()->assertSessionHasNoErrors();
        $snapshot = $this->snapshot();
        $receipt = DB::table('submission_receipts')->sole();
        $this->assertNotNull($receipt->completed_at);
        $this->assertSame(64, strlen($receipt->request_hash));
        $this->assertStringStartsWith('/', $receipt->redirect_path);

        $this->{strtolower($method)}($url, $payload)->assertRedirect($first->headers->get('Location'))
            ->assertSessionHas('status', 'This submission was already completed.');
        $this->assertSame($snapshot, $this->snapshot());
        $this->assertDatabaseCount('submission_receipts', 1);
        if ($action === 'register') {
            $this->assertGuest();
        }
    }

    public function test_missing_and_malformed_identifiers_cannot_mutate(): void
    {
        foreach ([null, 'invalid'] as $id) {
            $this->actingAs($this->supervisor)->putJson(route('workload.update', $this->investigator), [
                'performance_rating' => '0.5', '_submission_id' => $id,
            ])->assertUnprocessable()->assertJsonValidationErrors('_submission_id');
        }
        $this->assertDatabaseCount('submission_receipts', 0);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_a_key_cannot_be_reused_with_changed_input_or_another_target(): void
    {
        $payload = ['performance_rating' => '0.5', '_submission_id' => (string) Str::uuid()];
        $this->actingAs($this->supervisor)->put(route('workload.update', $this->investigator), $payload)->assertRedirect();
        $this->put(route('workload.update', $this->investigator), array_replace($payload, ['performance_rating' => '0.6']))->assertConflict();
        $this->put(route('workload.update', $this->other), $payload)->assertConflict();
        $this->assertSame('0.5', (string) $this->investigator->fresh()->performance_rating);
        $this->assertDatabaseCount('audit_logs', 1);
    }

    public function test_validation_does_not_consume_a_key(): void
    {
        $payload = ['performance_rating' => '2', '_submission_id' => (string) Str::uuid()];
        $this->actingAs($this->supervisor)->put(route('workload.update', $this->investigator), $payload)->assertSessionHasErrors('performance_rating');
        $this->assertDatabaseCount('submission_receipts', 0);
        $this->put(route('workload.update', $this->investigator), array_replace($payload, ['performance_rating' => '0.5']))->assertRedirect()->assertSessionHasNoErrors();
        $this->assertDatabaseCount('submission_receipts', 1);
        $this->assertDatabaseCount('audit_logs', 1);
    }

    public function test_audit_failure_rolls_back_the_receipt_and_allows_the_same_retry(): void
    {
        $fail = true;
        AuditLog::creating(function () use (&$fail) {
            if ($fail) {
                throw new \RuntimeException('Synthetic audit failure');
            }
        });
        $payload = $this->editPayload() + ['_submission_id' => (string) Str::uuid()];
        $this->actingAs($this->investigator)->put(route('cases.update', $this->case), $payload)->assertStatus(500);
        $this->assertDatabaseCount('submission_receipts', 0);
        $this->assertDatabaseCount('audit_logs', 0);
        $this->assertSame(0, $this->case->fresh()->lock_version);
        $fail = false;
        $this->put(route('cases.update', $this->case), $payload)->assertRedirect();
        $this->assertSame(1, $this->case->fresh()->lock_version);
        $this->assertDatabaseCount('audit_logs', 1);
    }

    public function test_new_identifiers_preserve_optimistic_locking_and_later_valid_edits(): void
    {
        $payload = $this->editPayload() + ['_submission_id' => (string) Str::uuid()];
        $this->actingAs($this->investigator)->put(route('cases.update', $this->case), $payload)->assertRedirect();
        $next = array_replace($payload, ['_submission_id' => (string) Str::uuid(), 'case_title' => 'Later synthetic edit']);
        $this->put(route('cases.update', $this->case), $next)->assertSessionHasErrors('lock_version');
        $this->put(route('cases.update', $this->case), array_replace($next, ['lock_version' => 1]))->assertRedirect()->assertSessionHasNoErrors();
        $this->put(route('cases.update', $this->case), $payload)->assertSessionHas('status', 'This submission was already completed.');
        $this->assertSame(2, $this->case->fresh()->lock_version);
        $this->assertSame('Later synthetic edit', $this->case->fresh()->case_title);
        $this->assertDatabaseCount('audit_logs', 2);
    }

    public function test_replay_rechecks_case_ownership(): void
    {
        $payload = $this->editPayload() + ['_submission_id' => (string) Str::uuid()];
        $this->actingAs($this->investigator)->put(route('cases.update', $this->case), $payload)->assertRedirect();
        $this->case->update(['investigator_id' => $this->other->id]);
        $this->put(route('cases.update', $this->case), $payload)->assertForbidden();
        $this->assertSame(1, AuditLog::where('action_performed', AuditLog::ACTION_EDIT)->count());
    }

    public function test_create_replay_cannot_return_a_case_no_longer_visible_to_the_creator(): void
    {
        $payload = $this->intakePayload() + ['_submission_id' => (string) Str::uuid()];
        $this->actingAs($this->investigator)->post(route('cases.store'), $payload)->assertRedirect();
        CaseModel::where('docket_no', 'SYN-RECEIPT-NEW')->firstOrFail()->update(['investigator_id' => $this->other->id]);
        $this->post(route('cases.store'), $payload)->assertForbidden();
        $this->assertSame(1, AuditLog::where('action_performed', AuditLog::ACTION_CREATE)->count());
    }

    public function test_replay_rechecks_roles_and_rejects_a_different_actor(): void
    {
        $payload = ['performance_rating' => '0.5', '_submission_id' => (string) Str::uuid()];
        $this->actingAs($this->supervisor)->put(route('workload.update', $this->investigator), $payload)->assertRedirect();
        $secondSupervisor = User::factory()->supervisor()->create();
        $this->actingAs($secondSupervisor)->put(route('workload.update', $this->investigator), $payload)->assertForbidden();
        $this->supervisor->update(['role_id' => $this->investigator->role_id]);
        $this->actingAs($this->supervisor->fresh())->put(route('workload.update', $this->investigator), $payload)->assertForbidden();
        $this->assertSame(1, AuditLog::where('action_performed', AuditLog::ACTION_PERFORMANCE_RATING_UPDATED)->count());
    }

    public function test_deactivation_prevents_a_replayed_success(): void
    {
        $payload = ['performance_rating' => '0.5', '_submission_id' => (string) Str::uuid()];
        $this->actingAs($this->supervisor)->put(route('workload.update', $this->investigator), $payload)->assertRedirect();
        $this->supervisor->update(['is_active' => false]);
        $this->actingAs($this->supervisor->fresh())->put(route('workload.update', $this->investigator), $payload)->assertRedirect(route('login'));
        $this->assertSame(1, AuditLog::where('action_performed', AuditLog::ACTION_PERFORMANCE_RATING_UPDATED)->count());
    }

    public function test_a_replaced_import_preview_cannot_be_committed_as_the_old_intention(): void
    {
        $this->actingAs($this->investigator);
        $old = $this->previewImport();
        $new = $this->previewImport('SYN-RECEIPT-SECOND');
        $this->assertNotSame($old['_import_id'], $new['_import_id']);
        $this->post(route('cases.import.store'), $old + ['_submission_id' => (string) Str::uuid()])->assertConflict();
        $this->assertDatabaseCount('submission_receipts', 0);
        $this->assertDatabaseCount('cases', 1);
    }

    public function test_receipt_failure_rolls_back_import_and_preserves_the_staged_file(): void
    {
        $this->actingAs($this->investigator);
        $payload = $this->previewImport() + ['_submission_id' => (string) Str::uuid()];
        $path = session('case_import.path');
        $fail = true;
        DB::connection()->beforeExecuting(function ($sql) use (&$fail) {
            if ($fail && str_starts_with(strtolower($sql), 'update "submission_receipts"')) {
                throw new \RuntimeException('Synthetic receipt failure');
            }
        });
        $this->post(route('cases.import.store'), $payload)->assertStatus(500);
        $this->assertDatabaseCount('submission_receipts', 0);
        $this->assertDatabaseCount('audit_logs', 0);
        $this->assertDatabaseCount('cases', 1);
        Storage::assertExists($path);
        $fail = false;
        $this->post(route('cases.import.store'), $payload)->assertRedirect()->assertSessionHasNoErrors();
        Storage::assertMissing($path);
        $this->post(route('cases.import.store'), $payload)->assertSessionHas('status', 'This submission was already completed.');
        $this->assertDatabaseCount('cases', 2);
        $this->assertDatabaseCount('audit_logs', 2);
    }

    public function test_a_fully_rejected_import_preserves_the_identifier_and_staged_file_for_retry(): void
    {
        $this->actingAs($this->supervisor);
        $payload = $this->previewImport(fallback: $this->other->id) + ['_submission_id' => (string) Str::uuid()];
        $path = session('case_import.path');
        $this->other->update(['is_active' => false]);
        $this->post(route('cases.import.store'), $payload)->assertRedirect(route('cases.import.create'))->assertSessionHasErrors('file');
        $this->assertDatabaseCount('submission_receipts', 0);
        $this->assertDatabaseCount('audit_logs', 0);
        Storage::assertExists($path);
        $this->other->update(['is_active' => true]);
        $this->post(route('cases.import.store'), $payload)->assertRedirect(route('cases.index'))->assertSessionHasNoErrors();
        $this->assertDatabaseCount('cases', 2);
        $this->assertDatabaseCount('audit_logs', 2);
        $this->assertDatabaseCount('submission_receipts', 1);
    }

    public static function assignmentSources(): array
    {
        return [['named'], ['fallback']];
    }

    #[DataProvider('assignmentSources')]
    public function test_import_replay_preserves_the_original_supervisor_requirement(string $assignment): void
    {
        $this->actingAs($this->supervisor);
        $payload = $this->previewImport(
            fallback: $assignment === 'fallback' ? $this->other->id : null,
            assignee: $assignment === 'named' ? $this->other->username : ''
        ) + ['_submission_id' => (string) Str::uuid()];
        $this->post(route('cases.import.store'), $payload)->assertRedirect()->assertSessionHasNoErrors();
        $this->supervisor->update(['role_id' => $this->investigator->role_id]);
        $this->actingAs($this->supervisor->fresh())->post(route('cases.import.store'), $payload)->assertForbidden();
        $this->assertDatabaseCount('cases', 2);
        $this->assertSame(1, AuditLog::where('action_performed', AuditLog::ACTION_IMPORTED)->count());
        $this->assertSame(1, AuditLog::where('action_performed', AuditLog::ACTION_CREATE)->count());
    }

    public function test_import_records_the_role_refreshed_after_claiming_the_receipt(): void
    {
        $this->actingAs($this->investigator);
        $payload = $this->previewImport(assignee: $this->other->username) + ['_submission_id' => (string) Str::uuid()];
        $promoted = false;
        DB::listen(function ($query) use (&$promoted) {
            if (! $promoted && str_starts_with(strtolower($query->sql), 'insert') && str_contains($query->sql, 'submission_receipts')) {
                $promoted = true;
                $this->investigator->update(['role_id' => $this->supervisor->role_id]);
            }
        });
        $this->post(route('cases.import.store'), $payload)->assertRedirect(route('cases.index'))->assertSessionHasNoErrors();
        $this->assertDatabaseHas('submission_receipts', ['id' => $payload['_submission_id'], 'requires_supervisor' => true]);
        $this->investigator->update(['role_id' => $this->other->role_id]);
        $this->actingAs($this->investigator->fresh())->post(route('cases.import.store'), $payload)->assertForbidden();
        $this->assertDatabaseCount('cases', 2);
        $this->assertSame(1, AuditLog::where('action_performed', AuditLog::ACTION_IMPORTED)->count());
    }

    public function test_changed_input_conflicts_even_if_the_previous_created_case_was_deleted(): void
    {
        $payload = $this->intakePayload() + ['investigator_id' => $this->investigator->id, '_submission_id' => (string) Str::uuid()];
        $this->actingAs($this->supervisor)->post(route('cases.store'), $payload)->assertRedirect()->assertSessionHasNoErrors();
        CaseModel::where('docket_no', 'SYN-RECEIPT-NEW')->firstOrFail()->delete();
        $this->post(route('cases.store'), array_replace($payload, ['docket_no' => 'SYN-RECEIPT-DIFFERENT']))->assertConflict();
        $this->assertSame(2, CaseModel::withTrashed()->count());
        $this->assertDatabaseCount('submission_receipts', 1);
    }

    public function test_a_controller_denial_is_audited_after_the_receipt_transaction_rolls_back(): void
    {
        $payload = array_replace($this->editPayload(), [
            'status' => CaseModel::STATUS_CLOSED, 'reason' => 'Synthetic office coverage',
        ]);
        $this->actingAs($this->supervisor)->put(route('cases.update', $this->case), $payload)->assertForbidden();
        $this->assertDatabaseCount('submission_receipts', 0);
        $this->assertDatabaseHas('cases', ['id' => $this->case->id, 'status' => CaseModel::STATUS_DOCKETED, 'lock_version' => 0]);
        $this->assertDatabaseHas('audit_logs', ['case_id' => $this->case->id, 'action_performed' => AuditLog::ACTION_ACCESS_DENIED]);
        $this->assertDatabaseCount('audit_logs', 1);
    }

    private function action(string $action): array
    {
        $actor = $this->investigator;
        $method = 'PUT';
        $url = route('cases.update', $this->case);
        $payload = $this->editPayload();
        switch ($action) {
            case 'create': $method = 'POST';
                $url = route('cases.store');
                $payload = $this->intakePayload();
                break;
            case 'delete': $actor = $this->supervisor;
                $method = 'DELETE';
                $url = route('cases.destroy', $this->case);
                $payload = [];
                break;
            case 'close': $url = route('cases.closure.update', $this->case);
                $payload = ['status' => CaseModel::STATUS_CLOSED, 'lock_version' => 0];
                break;
            case 'timeline': $url = route('cases.timeline.update', $this->case);
                $payload = ['date_of_docket' => '2026-01-01', 'date_submission_rop' => '2026-01-10', 'lock_version' => 0];
                break;
            case 'reassign': $actor = $this->supervisor;
                $url = route('cases.reassign.update', $this->case);
                $payload = ['docket_no' => $this->case->docket_no, 'investigator_id' => $this->other->id];
                break;
            case 'import': $this->actingAs($actor);
                $method = 'POST';
                $url = route('cases.import.store');
                $payload = $this->previewImport();
                break;
            case 'rating': $actor = $this->supervisor;
                $url = route('workload.update', $this->investigator);
                $payload = ['performance_rating' => '0.5'];
                break;
            case 'approve':
            case 'reject':
                $actor = $this->admin;
                $pending = User::factory()->investigator()->create(['registration_status' => User::REGISTRATION_PENDING]);
                $url = route('admin.registrations.'.$action, $pending);
                $payload = [];
                break;
            case 'role': $actor = $this->admin;
                $url = route('admin.users.role', $this->investigator);
                $payload = ['role_id' => $this->supervisor->role_id];
                break;
            case 'active': $actor = $this->admin;
                $url = route('admin.users.active', $this->investigator);
                $payload = ['is_active' => '0'];
                break;
            case 'password': $actor = $this->admin;
                $url = route('admin.users.password', $this->investigator);
                $payload = ['password' => 'Synthetic-only-password', 'password_confirmation' => 'Synthetic-only-password'];
                break;
            case 'register':
                $actor = null;
                $method = 'POST';
                $url = route('register');
                $payload = ['username' => 'synthetic-receipt-registration', 'first_name' => 'Synthetic', 'last_name' => 'Registration', 'office_region' => 'CHR Region VIII', 'role_id' => $this->investigator->role_id, 'password' => 'Synthetic-only-password', 'password_confirmation' => 'Synthetic-only-password'];
                break;
        }

        return [$actor, $method, $url, $payload];
    }

    private function editPayload(): array
    {
        return array_replace($this->case->only(['case_title', 'incident_details', 'source_info', 'status', 'complexity_weight', 'lock_version']), ['case_title' => 'Synthetic receipt edit']);
    }

    private function intakePayload(): array
    {
        return ['docket_no' => 'SYN-RECEIPT-NEW', 'case_title' => 'Synthetic intake', 'incident_details' => 'Synthetic only', 'complexity_weight' => 2, 'is_torture_case' => '0', 'date_of_docket' => '2026-01-01', 'victims' => [['name' => 'Synthetic victim', 'status' => 'Synthetic', 'sector' => 'Synthetic']], 'respondents' => [['name' => 'Synthetic respondent']]];
    }

    private function previewImport(string $docket = 'SYN-RECEIPT-IMPORT', ?int $fallback = null, string $assignee = ''): array
    {
        $csv = "Docket No.,Title,Date of Docket,Incident Details,Victim Name,Victim Status,Victim Sector,Respondent Name,Investigator\n{$docket},Synthetic import,2026-01-01,Synthetic only,Synthetic victim,Synthetic,Synthetic,Synthetic respondent,{$assignee}\n";
        $this->post(route('cases.import.preview'), ['file' => UploadedFile::fake()->createWithContent('synthetic.csv', $csv), 'investigator_id' => $fallback])->assertOk();

        return ['_import_id' => session('case_import.id')];
    }

    private function snapshot(): array
    {
        $snapshot = [];
        foreach (['cases', 'case_timelines', 'victims', 'respondents', 'complainants', 'users', 'audit_logs'] as $table) {
            $snapshot[$table] = DB::table($table)->orderBy('id')->get()->toJson();
        }

        return $snapshot;
    }
}
