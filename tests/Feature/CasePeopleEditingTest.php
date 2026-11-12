<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\CaseModel;
use App\Models\Complainant;
use App\Models\Respondent;
use App\Models\User;
use App\Models\Victim;
use App\Services\CaseDeadlineService;
use App\Services\ChrMonitoringExportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CasePeopleEditingTest extends TestCase
{
    use RefreshDatabase;

    private function payload(CaseModel $case, array $extra = []): array
    {
        return array_merge($case->only(['case_title', 'incident_details', 'source_info', 'status', 'complexity_weight', 'lock_version']), $extra);
    }

    private function fixture(): array
    {
        $actor = User::factory()->investigator()->create();
        $case = CaseModel::factory()->assignedTo($actor)->create(['status' => CaseModel::STATUS_DOCKETED, 'is_torture_case' => null]);
        $victim = Victim::factory()->create(['case_id' => $case->id]);
        $respondent = Respondent::factory()->create(['case_id' => $case->id]);
        $complainant = Complainant::create(['case_id' => $case->id, 'name' => 'Original complainant']);

        return [$actor, $case, $victim, $respondent, $complainant];
    }

    public function test_people_are_prefilled_and_updated_without_replacing_ids(): void
    {
        [$actor, $case, $victim, $respondent, $complainant] = $this->fixture();
        $this->actingAs($actor)->get(route('cases.edit', $case))->assertOk()
            ->assertSee($victim->name)->assertSee('name="victims[0][id]"', false)
            ->assertSee('Unknown')->assertSee(route('cases.timeline.edit', $case), false);
        $this->put(route('cases.update', $case), $this->payload($case, [
            'victims' => [array_merge($victim->only(['id', 'name', 'age', 'status', 'sector']), ['name' => 'Corrected synthetic victim'])],
            'respondents' => [['id' => $respondent->id, '_remove' => '1']],
            'complainants' => [['name' => 'Added complainant']],
            'is_torture_case' => '1',
        ]))->assertSessionHasNoErrors()->assertRedirect(route('cases.show', $case));
        $this->assertSame('Corrected synthetic victim', $victim->fresh()->name);
        $this->assertNull($respondent->fresh());
        $this->assertNotNull($complainant->fresh());
        $this->assertTrue($case->fresh()->is_torture_case);
        $this->assertSame(1, $case->fresh()->lock_version);
        foreach ([AuditLog::ACTION_PEOPLE_UPDATED, AuditLog::ACTION_PEOPLE_ADDED, AuditLog::ACTION_PEOPLE_REMOVED, AuditLog::ACTION_CLASSIFICATION_CHANGED, AuditLog::ACTION_EDIT] as $action) {
            $this->assertDatabaseHas('audit_logs', ['case_id' => $case->id, 'action_performed' => $action]);
            $this->assertContains($action, AuditLog::ACTIONS);
        }
        $this->get(route('cases.show', $case))->assertSee('Corrected synthetic victim')->assertSee('Added complainant');
        $this->get(route('cases.index', ['search' => 'Corrected synthetic victim']))->assertSee($case->docket_no);
        $this->get(route('reports.index'))->assertSee('Corrected synthetic victim');
        $sheet = (new ChrMonitoringExportService)->workbook(collect([$case->fresh()->load(['timeline', 'investigator', 'victims', 'respondents', 'complainants'])]))->getActiveSheet();
        $this->assertSame('Corrected synthetic victim', $sheet->getCell('E12')->getValue());
    }

    public function test_all_people_can_be_explicitly_removed_but_omission_never_removes_them(): void
    {
        [$actor, $case, $victim, $respondent, $complainant] = $this->fixture();
        $this->actingAs($actor)->put(route('cases.update', $case), $this->payload($case, ['victims' => [], 'respondents' => []]))->assertSessionHasNoErrors();
        $this->assertNotNull($victim->fresh());
        $this->assertNotNull($respondent->fresh());
        $case->refresh();
        $this->put(route('cases.update', $case), $this->payload($case, [
            'victims' => [['id' => $victim->id, '_remove' => true]],
            'respondents' => [['id' => $respondent->id, '_remove' => true]],
            'complainants' => [['id' => $complainant->id, '_remove' => true]],
        ]))->assertSessionHasNoErrors();
        $this->assertSame(0, $case->victims()->count());
        $this->assertSame(0, $case->respondents()->count());
        $this->assertSame(0, $case->complainants()->count());
        $this->get(route('cases.edit', $case))->assertOk()->assertSee('No victims recorded.')->assertSee('No respondents recorded.');
    }

    public function test_foreign_duplicate_wrong_group_and_malformed_rows_are_rejected(): void
    {
        [$actor, $case, $victim] = $this->fixture();
        $other = Victim::factory()->create();
        $this->actingAs($actor);
        $valid = $victim->only(['id', 'name', 'age', 'status', 'sector']);
        foreach ([
            [['id' => $other->id, '_remove' => 1]],
            [$valid, $valid],
            ['malformed'],
            [['name' => ['malformed']]],
            [['_remove' => true]],
            [array_merge($valid, ['case_id' => $other->case_id])],
            [array_merge($valid, ['name' => '', '_remove' => false])],
        ] as $rows) {
            $response = $this->put(route('cases.update', $case), $this->payload($case, ['victims' => $rows]));
            $response->assertSessionHasErrors();
            $this->assertSame(0, $case->fresh()->lock_version);
        }
        $foreignComplainant = Complainant::create(['case_id' => $other->case_id, 'name' => 'Other case']);
        $this->put(route('cases.update', $case), $this->payload($case, ['complainants' => [['id' => $foreignComplainant->id, '_remove' => 1]]]))->assertSessionHasErrors('complainants.0.id');
        $this->assertNotNull($other->fresh());
    }

    public function test_blank_new_rows_are_ignored_and_input_and_removals_survive_validation(): void
    {
        [$actor, $case, $victim] = $this->fixture();
        $this->actingAs($actor)->put(route('cases.update', $case), $this->payload($case, ['victims' => [['name' => '', 'age' => '', 'status' => '', 'sector' => '']]]))->assertSessionHasNoErrors();
        $case->refresh();
        $this->from(route('cases.edit', $case))->put(route('cases.update', $case), $this->payload($case, [
            'case_title' => '', 'victims' => [3 => ['id' => $victim->id, '_remove' => 1, 'name' => 'Retain entered name']],
        ]))->assertSessionHasErrors('case_title');
        $this->get(route('cases.edit', $case))->assertOk()->assertSee('Retain entered name')->assertSee('victims_3_remove')->assertSee('checked');
        $this->assertNotNull($victim->fresh());
    }

    public function test_classification_can_move_between_all_three_states_and_omission_preserves_it(): void
    {
        [$actor, $case] = $this->fixture();
        foreach (['1' => true, '0' => false, '' => null] as $input => $expected) {
            $this->actingAs($actor)->put(route('cases.update', $case), $this->payload($case, ['is_torture_case' => $input]))->assertSessionHasNoErrors();
            $case->refresh();
            $this->assertSame($expected, $case->is_torture_case);
        }
        $this->put(route('cases.update', $case), $this->payload($case))->assertSessionHasNoErrors();
        $this->assertNull($case->fresh()->is_torture_case);
        $this->assertTrue(CaseDeadlineService::SIXTY_DAY_ENABLED);
    }

    public function test_permissions_supervisor_reasons_and_stale_people_saves(): void
    {
        [$actor, $case, $victim] = $this->fixture();
        $remove = $this->payload($case, ['victims' => [['id' => $victim->id, '_remove' => 1]]]);
        $this->actingAs(User::factory()->investigator()->create())->put(route('cases.update', $case), $remove)->assertForbidden();
        $this->actingAs(User::factory()->admin()->create())->put(route('cases.update', $case), $remove)->assertForbidden();
        $supervisor = User::factory()->supervisor()->create();
        $this->actingAs($supervisor)->put(route('cases.update', $case), $remove)->assertSessionHasErrors('reason');
        $this->put(route('cases.update', $case), $remove + ['reason' => 'Correcting duplicate entry'])->assertSessionHasNoErrors();
        $this->assertDatabaseHas('audit_logs', ['action_performed' => AuditLog::ACTION_EDIT_ON_BEHALF, 'notes' => 'Correcting duplicate entry']);
        $this->actingAs($actor)->put(route('cases.update', $case), $this->payload($case, ['is_torture_case' => 1]))->assertSessionHasErrors('lock_version');
        $this->assertNull($case->fresh()->is_torture_case);
    }

    public function test_audit_failure_rolls_back_case_people_classification_and_version(): void
    {
        [$actor, $case, $victim, $respondent] = $this->fixture();
        $this->actingAs($actor);
        AuditLog::creating(function ($audit) {
            if ($audit->action_performed === AuditLog::ACTION_CLASSIFICATION_CHANGED) {
                throw new \RuntimeException('Synthetic audit failure');
            }
        });
        $this->put(route('cases.update', $case), $this->payload($case, [
            'case_title' => 'Must roll back', 'is_torture_case' => true,
            'victims' => [['id' => $victim->id, '_remove' => 1]],
            'respondents' => [array_merge($respondent->only(['id', 'name', 'age', 'status', 'sector']), ['name' => 'Must roll back'])],
            'complainants' => [['name' => 'Must not persist']],
        ]))->assertStatus(500);
        $this->assertNotSame('Must roll back', $case->fresh()->case_title);
        $this->assertSame(0, $case->fresh()->lock_version);
        $this->assertNull($case->fresh()->is_torture_case);
        $this->assertNotNull($victim->fresh());
        $this->assertNotSame('Must roll back', $respondent->fresh()->name);
        $this->assertDatabaseMissing('complainants', ['name' => 'Must not persist']);
        $this->assertDatabaseMissing('audit_logs', ['case_id' => $case->id, 'action_performed' => AuditLog::ACTION_PEOPLE_REMOVED]);
    }
}
