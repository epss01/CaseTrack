<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\CaseModel;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CaseIntakeTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    protected function intakePayload(array $overrides = []): array
    {
        return array_merge([
            'docket_no' => 'CHR-VIII-2026-0100',
            'case_title' => 'Alleged unlawful arrest in Tacloban',
            'incident_details' => 'Complainant reports a warrantless arrest on 12 June.',
            'source_info' => 'Walk-in',
            'complexity_weight' => 3,
            'date_of_docket' => '2026-06-12',
            'complainants' => [
                ['name' => 'Josefa Ramos'],
            ],
            'victims' => [
                ['name' => 'Maria Santos', 'age' => '34', 'status' => 'Injured', 'sector' => 'Farmer'],
                ['name' => 'Pedro Reyes', 'age' => '', 'status' => 'Detained', 'sector' => 'Urban poor'],
            ],
            'respondents' => [
                ['name' => 'PO1 Cruz', 'age' => '29', 'status' => 'Active duty', 'sector' => 'PNP'],
            ],
        ], $overrides);
    }

    public function test_an_investigator_can_docket_a_case_with_victims_and_respondents(): void
    {
        $investigator = User::factory()->investigator()->create();

        $response = $this->actingAs($investigator)
            ->post(route('cases.store'), $this->intakePayload());

        $case = CaseModel::where('docket_no', 'CHR-VIII-2026-0100')->firstOrFail();

        $response->assertRedirect(route('cases.show', $case));

        $this->assertSame('Alleged unlawful arrest in Tacloban', $case->case_title);
        $this->assertSame('Walk-in', $case->source_info);
        $this->assertSame(CaseModel::STATUS_DOCKETED, $case->status);
        $this->assertSame(3, $case->complexity_weight);
        $this->assertSame($investigator->id, $case->investigator_id);

        $this->assertCount(2, $case->victims);
        $this->assertCount(1, $case->respondents);
        $this->assertSame('Maria Santos', $case->victims[0]->name);
        $this->assertSame(34, $case->victims[0]->age);
        $this->assertNull($case->victims[1]->age);
        $this->assertSame('PO1 Cruz', $case->respondents[0]->name);
    }

    public function test_the_intake_form_renders_for_a_case_handler(): void
    {
        $investigator = User::factory()->investigator()->create();

        $this->actingAs($investigator)
            ->get(route('cases.create'))
            ->assertOk()
            ->assertSee('Case Intake')
            ->assertSee('Date of Docket')
            ->assertSee('Add Complainant')
            ->assertSee('Add Victim')
            ->assertSee('Add Respondent')
            ->assertSee('This case will be assigned to you.');
    }

    public function test_intake_opens_the_case_timeline(): void
    {
        $investigator = User::factory()->investigator()->create();

        $this->actingAs($investigator)
            ->post(route('cases.store'), $this->intakePayload())
            ->assertSessionHasNoErrors();

        $case = CaseModel::where('docket_no', 'CHR-VIII-2026-0100')->firstOrFail();

        $this->assertNotNull($case->timeline);
        $this->assertSame('2026-06-12', $case->timeline->date_of_docket->toDateString());

        // The milestones are not known at intake.
        $this->assertNull($case->timeline->submission_60th_day);
        $this->assertNull($case->timeline->date_submitted_to);
    }

    public function test_complainants_are_recorded_at_intake(): void
    {
        $investigator = User::factory()->investigator()->create();

        $this->actingAs($investigator)
            ->post(route('cases.store'), $this->intakePayload([
                'complainants' => [
                    ['name' => 'Josefa Ramos'],
                    ['name' => 'Elena Bautista'],
                ],
            ]))
            ->assertSessionHasNoErrors();

        $case = CaseModel::where('docket_no', 'CHR-VIII-2026-0100')->firstOrFail();

        $this->assertCount(2, $case->complainants);
        $this->assertSame('Josefa Ramos', $case->complainants[0]->name);
        $this->assertSame('Elena Bautista', $case->complainants[1]->name);
    }

    public function test_a_case_can_be_docketed_with_no_complainant(): void
    {
        $investigator = User::factory()->investigator()->create();

        $this->actingAs($investigator)
            ->post(route('cases.store'), $this->intakePayload(['complainants' => []]))
            ->assertSessionHasNoErrors();

        $case = CaseModel::where('docket_no', 'CHR-VIII-2026-0100')->firstOrFail();

        $this->assertCount(0, $case->complainants);
    }

    public function test_blank_complainant_rows_are_ignored(): void
    {
        $investigator = User::factory()->investigator()->create();

        $this->actingAs($investigator)
            ->post(route('cases.store'), $this->intakePayload([
                'complainants' => [
                    ['name' => 'Josefa Ramos'],
                    ['name' => ''],
                ],
            ]))
            ->assertSessionHasNoErrors();

        $case = CaseModel::where('docket_no', 'CHR-VIII-2026-0100')->firstOrFail();

        $this->assertCount(1, $case->complainants);
    }

    public function test_the_date_of_docket_is_required_at_intake(): void
    {
        $investigator = User::factory()->investigator()->create();

        $this->actingAs($investigator)
            ->post(route('cases.store'), $this->intakePayload(['date_of_docket' => '']))
            ->assertSessionHasErrors('date_of_docket');

        $this->assertDatabaseCount('cases', 0);
        $this->assertDatabaseCount('case_timelines', 0);
    }

    public function test_the_complexity_weight_is_required_and_bounded(): void
    {
        // C_j feeds the Workload Capacity Score, so a case docketed without a
        // weight would sit in a caseload without adding to it.
        $investigator = User::factory()->investigator()->create();

        foreach (['', 0, 6] as $invalid) {
            $this->actingAs($investigator)
                ->post(route('cases.store'), $this->intakePayload(['complexity_weight' => $invalid]))
                ->assertSessionHasErrors('complexity_weight');
        }

        $this->assertDatabaseCount('cases', 0);
    }

    public function test_a_supervisor_sees_the_picker_ordered_by_workload_with_the_lowest_suggested(): void
    {
        $supervisor = User::factory()->supervisor()->create();

        $heavier = User::factory()->investigator()->create(['first_name' => 'Dina', 'last_name' => 'Abad']);
        $lighter = User::factory()->investigator()->create(['first_name' => 'Noel', 'last_name' => 'Zamora']);

        CaseModel::factory()->assignedTo($heavier)->create([
            'status' => CaseModel::STATUS_DOCKETED,
            'complexity_weight' => 5,
        ]);

        $content = $this->actingAs($supervisor)
            ->get(route('cases.create'))
            ->assertOk()
            ->assertSee('WCS')
            ->assertSee('(suggested)')
            ->getContent();

        // Zamora holds nothing, so he outranks Abad despite the name order.
        $this->assertLessThan(
            strpos($content, 'Dina Abad'),
            strpos($content, 'Noel Zamora'),
        );
    }

    public function test_an_investigator_cannot_file_a_case_under_another_investigator(): void
    {
        $investigator = User::factory()->investigator()->create();
        $colleague = User::factory()->investigator()->create();

        $this->actingAs($investigator)
            ->post(route('cases.store'), $this->intakePayload([
                'investigator_id' => $colleague->id,
            ]));

        $case = CaseModel::where('docket_no', 'CHR-VIII-2026-0100')->firstOrFail();

        $this->assertSame($investigator->id, $case->investigator_id);
    }

    public function test_a_supervisor_must_choose_an_investigator_to_assign_the_case_to(): void
    {
        $supervisor = User::factory()->supervisor()->create();

        $this->actingAs($supervisor)
            ->post(route('cases.store'), $this->intakePayload())
            ->assertSessionHasErrors('investigator_id');

        $this->assertDatabaseCount('cases', 0);

        $investigator = User::factory()->investigator()->create();

        $this->actingAs($supervisor)
            ->post(route('cases.store'), $this->intakePayload([
                'investigator_id' => $investigator->id,
            ]))
            ->assertSessionHasNoErrors();

        $this->assertSame(
            $investigator->id,
            CaseModel::where('docket_no', 'CHR-VIII-2026-0100')->value('investigator_id')
        );
    }

    public function test_a_case_cannot_be_assigned_to_a_supervisor(): void
    {
        $supervisor = User::factory()->supervisor()->create();
        $anotherSupervisor = User::factory()->supervisor()->create();

        $this->actingAs($supervisor)
            ->post(route('cases.store'), $this->intakePayload([
                'investigator_id' => $anotherSupervisor->id,
            ]))
            ->assertSessionHasErrors('investigator_id');

        $this->assertDatabaseCount('cases', 0);
    }

    public function test_the_picker_excludes_rejected_pending_and_deactivated_investigators(): void
    {
        $supervisor = User::factory()->supervisor()->create();

        User::factory()->investigator()->create(['first_name' => 'Ana', 'last_name' => 'Cruz']);
        User::factory()->investigator()->create([
            'first_name' => 'Rejected', 'last_name' => 'Reyes',
            'registration_status' => User::REGISTRATION_REJECTED,
        ]);
        User::factory()->investigator()->create([
            'first_name' => 'Pending', 'last_name' => 'Ramos',
            'registration_status' => User::REGISTRATION_PENDING,
        ]);
        User::factory()->investigator()->create([
            'first_name' => 'Departed', 'last_name' => 'Diaz',
            'is_active' => false,
        ]);

        $this->actingAs($supervisor)
            ->get(route('cases.create'))
            ->assertOk()
            ->assertSee('Ana Cruz')
            ->assertDontSee('Rejected Reyes')
            ->assertDontSee('Pending Ramos')
            ->assertDontSee('Departed Diaz');
    }

    public function test_a_case_cannot_be_assigned_to_a_rejected_pending_or_deactivated_investigator(): void
    {
        $supervisor = User::factory()->supervisor()->create();

        $rejected = User::factory()->investigator()->create(['registration_status' => User::REGISTRATION_REJECTED]);
        $pending = User::factory()->investigator()->create(['registration_status' => User::REGISTRATION_PENDING]);
        $deactivated = User::factory()->investigator()->create(['is_active' => false]);

        foreach ([$rejected, $pending, $deactivated] as $ineligible) {
            $this->actingAs($supervisor)
                ->post(route('cases.store'), $this->intakePayload([
                    'investigator_id' => $ineligible->id,
                ]))
                ->assertSessionHasErrors('investigator_id');
        }

        $this->assertDatabaseCount('cases', 0);
    }

    public function test_at_least_one_victim_and_one_respondent_are_required(): void
    {
        $investigator = User::factory()->investigator()->create();

        $this->actingAs($investigator)
            ->post(route('cases.store'), $this->intakePayload([
                'victims' => [],
                'respondents' => [],
            ]))
            ->assertSessionHasErrors(['victims', 'respondents']);

        $this->assertDatabaseCount('cases', 0);
    }

    public function test_untouched_blank_repeater_rows_are_ignored(): void
    {
        $investigator = User::factory()->investigator()->create();

        $this->actingAs($investigator)
            ->post(route('cases.store'), $this->intakePayload([
                'victims' => [
                    ['name' => 'Maria Santos', 'age' => '34', 'status' => 'Injured', 'sector' => 'Farmer'],
                    ['name' => '', 'age' => '', 'status' => '', 'sector' => ''],
                ],
                'respondents' => [
                    ['name' => 'PO1 Cruz', 'age' => '', 'status' => '', 'sector' => ''],
                    ['name' => '', 'age' => '', 'status' => '', 'sector' => ''],
                ],
            ]))
            ->assertSessionHasNoErrors();

        $case = CaseModel::where('docket_no', 'CHR-VIII-2026-0100')->firstOrFail();

        $this->assertCount(1, $case->victims);
        $this->assertCount(1, $case->respondents);
    }

    public function test_a_partially_filled_victim_row_is_still_validated(): void
    {
        $investigator = User::factory()->investigator()->create();

        $this->actingAs($investigator)
            ->post(route('cases.store'), $this->intakePayload([
                'victims' => [
                    ['name' => 'Maria Santos', 'age' => '', 'status' => '', 'sector' => ''],
                ],
            ]))
            ->assertSessionHasErrors(['victims.0.status', 'victims.0.sector']);

        $this->assertDatabaseCount('cases', 0);
    }

    public function test_the_docket_number_must_be_unique(): void
    {
        $investigator = User::factory()->investigator()->create();
        CaseModel::factory()->assignedTo($investigator)->create(['docket_no' => 'CHR-VIII-2026-0100']);

        $this->actingAs($investigator)
            ->post(route('cases.store'), $this->intakePayload())
            ->assertSessionHasErrors('docket_no');

        $this->assertDatabaseCount('cases', 1);
    }

    public function test_nothing_is_persisted_when_the_nested_records_are_invalid(): void
    {
        $investigator = User::factory()->investigator()->create();

        $this->actingAs($investigator)
            ->post(route('cases.store'), $this->intakePayload([
                'respondents' => [['name' => '', 'age' => 'not-a-number', 'status' => '', 'sector' => 'PNP']],
            ]))
            ->assertSessionHasErrors();

        $this->assertDatabaseCount('cases', 0);
        $this->assertDatabaseCount('victims', 0);
        $this->assertDatabaseCount('respondents', 0);
    }

    // ----------------------------------------------------------------- audit

    public function test_docketing_a_case_is_audited(): void
    {
        $investigator = User::factory()->investigator()->create();

        $this->actingAs($investigator)
            ->post(route('cases.store'), $this->intakePayload())
            ->assertSessionHasNoErrors();

        $case = CaseModel::where('docket_no', 'CHR-VIII-2026-0100')->firstOrFail();

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $investigator->id,
            'case_id' => $case->id,
            'action_performed' => AuditLog::ACTION_CREATE,
        ]);
    }

    /**
     * The entry lives in the same transaction as the case it describes, so a
     * rejected intake leaves no trace of a case that was never docketed.
     */
    public function test_a_rejected_intake_writes_no_audit_entry(): void
    {
        $investigator = User::factory()->investigator()->create();

        $this->actingAs($investigator)
            ->post(route('cases.store'), $this->intakePayload([
                'respondents' => [['name' => '', 'age' => 'not-a-number', 'status' => '', 'sector' => 'PNP']],
            ]))
            ->assertSessionHasErrors();

        $this->assertDatabaseCount('cases', 0);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_a_user_without_a_case_handling_role_cannot_reach_intake(): void
    {
        $outsider = User::factory()->withRole('Records Clerk')->create();

        $this->actingAs($outsider)->get(route('cases.create'))->assertForbidden();
        $this->actingAs($outsider)->post(route('cases.store'), $this->intakePayload())->assertForbidden();

        $this->assertDatabaseCount('cases', 0);
    }

    public function test_guests_cannot_reach_intake(): void
    {
        $this->get(route('cases.create'))->assertRedirect('/login');
        $this->post(route('cases.store'), $this->intakePayload())->assertRedirect('/login');

        $this->assertDatabaseCount('cases', 0);
    }
}
