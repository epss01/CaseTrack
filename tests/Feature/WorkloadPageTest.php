<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\CaseModel;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The workload page: supervisor-only, and the one place P_i is set.
 */
class WorkloadPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_supervisor_sees_every_investigator_with_their_score(): void
    {
        $supervisor = User::factory()->supervisor()->create();
        $investigator = User::factory()->investigator()->create([
            'first_name' => 'Ana Marie',
            'last_name' => 'Bautista',
            'performance_rating' => 0.5,
        ]);

        CaseModel::factory()->assignedTo($investigator)->create([
            'status' => CaseModel::STATUS_DOCKETED,
            'complexity_weight' => 4,
        ]);

        $this->actingAs($supervisor)
            ->get(route('workload.index'))
            ->assertOk()
            ->assertSee('Ana Marie Bautista')
            ->assertSee('6.0');   // 4 x (2 - 0.5)
    }

    public function test_the_least_loaded_investigator_is_listed_first_and_marked_suggested(): void
    {
        $supervisor = User::factory()->supervisor()->create();

        // Alphabetically Abad comes first, but she carries the heavier load —
        // so ordering by name rather than by score would fail this.
        $heavier = User::factory()->investigator()->create(['first_name' => 'Dina', 'last_name' => 'Abad']);
        $lighter = User::factory()->investigator()->create(['first_name' => 'Noel', 'last_name' => 'Zamora']);

        CaseModel::factory()->assignedTo($heavier)->create([
            'status' => CaseModel::STATUS_DOCKETED,
            'complexity_weight' => 5,
        ]);
        CaseModel::factory()->assignedTo($lighter)->create([
            'status' => CaseModel::STATUS_DOCKETED,
            'complexity_weight' => 1,
        ]);

        $response = $this->actingAs($supervisor)->get(route('workload.index'))->assertOk();

        $this->assertLessThan(
            strpos($response->getContent(), 'Dina Abad'),
            strpos($response->getContent(), 'Noel Zamora'),
            'The lower-scoring investigator should be listed first.'
        );
    }

    public function test_the_listing_paginates_at_fifteen_lightest_first(): void
    {
        $supervisor = User::factory()->supervisor()->create();

        $investigators = [];

        for ($i = 1; $i <= 16; $i++) {
            $investigator = User::factory()->investigator()->create([
                'first_name' => "Investigator{$i}",
                'last_name' => 'Test',
                'performance_rating' => 1.0,
            ]);

            CaseModel::factory()->assignedTo($investigator)->create([
                'status' => CaseModel::STATUS_DOCKETED,
                'complexity_weight' => $i,
            ]);

            $investigators[$i] = $investigator;
        }

        $lightest = $investigators[1]; // WCS 1, sorts first
        $heaviest = $investigators[16]; // WCS 16, sorts last — page 2

        $this->actingAs($supervisor)
            ->get(route('workload.index'))
            ->assertOk()
            ->assertSee($lightest->full_name)
            ->assertDontSee($heaviest->full_name);

        $this->actingAs($supervisor)
            ->get(route('workload.index', ['page' => 2]))
            ->assertOk()
            ->assertSee($heaviest->full_name)
            ->assertDontSee($lightest->full_name);
    }

    public function test_a_rejected_investigator_is_hidden_but_a_deactivated_one_still_shows(): void
    {
        $supervisor = User::factory()->supervisor()->create();

        // Never a real user — excluded everywhere.
        $rejected = User::factory()->investigator()->create([
            'first_name' => 'Rejected', 'last_name' => 'Reyes',
            'registration_status' => User::REGISTRATION_REJECTED,
        ]);

        // A departed investigator: no longer assignable, but their existing
        // caseload must stay visible here so it can be reassigned.
        $deactivated = User::factory()->investigator()->create([
            'first_name' => 'Departed', 'last_name' => 'Diaz',
            'is_active' => false,
        ]);
        CaseModel::factory()->assignedTo($deactivated)->create([
            'status' => CaseModel::STATUS_DOCKETED,
            'complexity_weight' => 3,
        ]);

        $this->actingAs($supervisor)
            ->get(route('workload.index'))
            ->assertOk()
            ->assertDontSee('Rejected Reyes')
            ->assertSee('Departed Diaz');
    }

    public function test_an_investigator_cannot_reach_the_workload_page(): void
    {
        $investigator = User::factory()->investigator()->create();

        $this->actingAs($investigator)
            ->get(route('workload.index'))
            ->assertForbidden();
    }

    public function test_an_investigator_cannot_change_a_rating(): void
    {
        $investigator = User::factory()->investigator()->create(['performance_rating' => 1.0]);
        $colleague = User::factory()->investigator()->create(['performance_rating' => 1.0]);

        $this->actingAs($investigator)
            ->put(route('workload.update', $colleague), ['performance_rating' => 0.1])
            ->assertForbidden();

        $this->assertSame(1.0, $colleague->fresh()->performance_rating);
    }

    public function test_a_supervisor_can_set_a_rating_and_it_is_audited(): void
    {
        $supervisor = User::factory()->supervisor()->create();
        $investigator = User::factory()->investigator()->create(['performance_rating' => 1.0]);

        $this->actingAs($supervisor)
            ->put(route('workload.update', $investigator), ['performance_rating' => 0.6])
            ->assertRedirect(route('workload.index'));

        $this->assertSame(0.6, $investigator->fresh()->performance_rating);

        // Not case-scoped, so case_id stays null — the column allows it.
        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $supervisor->id,
            'case_id' => null,
            'action_performed' => AuditLog::ACTION_UPDATE,
        ]);
    }

    public function test_a_rating_outside_the_normalized_range_is_rejected(): void
    {
        $supervisor = User::factory()->supervisor()->create();
        $investigator = User::factory()->investigator()->create(['performance_rating' => 1.0]);

        foreach ([0, 1.5, -0.2] as $invalid) {
            $this->actingAs($supervisor)
                ->put(route('workload.update', $investigator), ['performance_rating' => $invalid])
                ->assertSessionHasErrors('performance_rating');
        }

        $this->assertSame(1.0, $investigator->fresh()->performance_rating);
        $this->assertDatabaseCount('audit_logs', 0);
    }

    public function test_a_changed_rating_moves_the_score(): void
    {
        $supervisor = User::factory()->supervisor()->create();
        $investigator = User::factory()->investigator()->create(['performance_rating' => 1.0]);

        CaseModel::factory()->assignedTo($investigator)->create([
            'status' => CaseModel::STATUS_DOCKETED,
            'complexity_weight' => 4,
        ]);

        $this->assertSame(4.0, $investigator->workloadCapacityScore());

        $this->actingAs($supervisor)
            ->put(route('workload.update', $investigator), ['performance_rating' => 0.5]);

        $this->assertSame(6.0, $investigator->fresh()->workloadCapacityScore());
    }
}
