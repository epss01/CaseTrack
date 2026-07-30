<?php

namespace Tests\Feature;

use App\Models\CaseModel;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The Workload Capacity Score: WCS_i = sum over active cases of C_j x (2 - P_i).
 *
 * Source: raw/wcs/Workload-Capacity-Score.docx in the project vault.
 */
class WorkloadCapacityScoreTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_investigator_with_no_cases_scores_zero(): void
    {
        $investigator = User::factory()->investigator()->create(['performance_rating' => 0.5]);

        $this->assertSame(0.0, $investigator->workloadCapacityScore());
    }

    public function test_an_unrated_investigator_scores_the_plain_sum_of_weights(): void
    {
        // P_i defaults to 1.0, so (2 - P_i) = 1.0 and the factor drops out.
        $investigator = User::factory()->investigator()->create();

        $this->assignActive($investigator, [3, 4, 1]);

        $this->assertSame(1.0, $investigator->performance_rating);
        $this->assertSame(8.0, $investigator->workloadCapacityScore());
    }

    public function test_the_rating_scales_the_score(): void
    {
        $investigator = User::factory()->investigator()->create(['performance_rating' => 0.4]);

        $this->assignActive($investigator, [3, 2]);

        // 5 x (2 - 0.4) = 8.0
        $this->assertSame(8.0, $investigator->workloadCapacityScore());
    }

    public function test_closed_cases_leave_the_active_set(): void
    {
        $investigator = User::factory()->investigator()->create();

        $this->assignActive($investigator, [5]);
        CaseModel::factory()->assignedTo($investigator)->create([
            'status' => CaseModel::STATUS_CLOSED,
            'complexity_weight' => 5,
        ]);

        $this->assertSame(5.0, $investigator->fresh()->workloadCapacityScore());
    }

    public function test_deleted_cases_leave_the_active_set(): void
    {
        $investigator = User::factory()->investigator()->create();

        $this->assignActive($investigator, [2]);
        $deleted = CaseModel::factory()->assignedTo($investigator)->create([
            'status' => CaseModel::STATUS_DOCKETED,
            'complexity_weight' => 4,
        ]);
        $deleted->delete();

        $this->assertSame(2.0, $investigator->fresh()->workloadCapacityScore());
    }

    public function test_a_lower_rated_investigator_can_outscore_a_busier_one(): void
    {
        // This is the case that proves the rating is actually applied: without
        // it, the investigator holding more weight would always score higher.
        $busy = User::factory()->investigator()->create(['performance_rating' => 1.0]);
        $slower = User::factory()->investigator()->create(['performance_rating' => 0.4]);

        $this->assignActive($busy, [4, 3]);      // 7 x 1.0 = 7.0
        $this->assignActive($slower, [3, 2]);    // 5 x 1.6 = 8.0

        $this->assertSame(7.0, $busy->workloadCapacityScore());
        $this->assertSame(8.0, $slower->workloadCapacityScore());
        $this->assertGreaterThan($busy->workloadCapacityScore(), $slower->workloadCapacityScore());
    }

    public function test_another_investigators_cases_do_not_count(): void
    {
        $investigator = User::factory()->investigator()->create();
        $colleague = User::factory()->investigator()->create();

        $this->assignActive($investigator, [2]);
        $this->assignActive($colleague, [5, 5]);

        $this->assertSame(2.0, $investigator->workloadCapacityScore());
    }

    /**
     * Give the investigator one active case per complexity weight given.
     *
     * @param  list<int>  $weights
     */
    private function assignActive(User $investigator, array $weights): void
    {
        foreach ($weights as $weight) {
            CaseModel::factory()->assignedTo($investigator)->create([
                'status' => CaseModel::STATUS_DOCKETED,
                'complexity_weight' => $weight,
            ]);
        }
    }
}
