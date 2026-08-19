<?php

namespace Tests\Feature;

use App\Models\CaseModel;
use App\Models\CaseTimeline;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * CHR-Answers-2026-08-01: the ratified five-value status vocabulary (item 2)
 * and the derived New/Pending docket phase (item 8).
 */
class CaseStatusVocabularyTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_edit_form_offers_all_five_statuses_for_an_ordinary_case(): void
    {
        $investigator = User::factory()->investigator()->create();
        $case = CaseModel::factory()->assignedTo($investigator)->create(['status' => CaseModel::STATUS_DOCKETED]);

        $this->actingAs($investigator)
            ->get(route('cases.edit', $case))
            ->assertOk()
            ->assertSee(CaseModel::STATUS_DOCKETED)
            ->assertSee(CaseModel::STATUS_UNDER_INVESTIGATION)
            ->assertSee(CaseModel::STATUS_FOR_REVIEW);
    }

    public function test_the_two_new_statuses_are_selectable_and_persist(): void
    {
        $investigator = User::factory()->investigator()->create();
        $case = CaseModel::factory()->assignedTo($investigator)->create(['status' => CaseModel::STATUS_DOCKETED]);

        $this->actingAs($investigator)
            ->put(route('cases.update', $case), [
                'case_title' => $case->case_title,
                'incident_details' => $case->incident_details,
                'status' => CaseModel::STATUS_UNDER_INVESTIGATION,
                'complexity_weight' => $case->complexity_weight,
                'updated_at' => $case->updated_at->format('Y-m-d H:i:s'),
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame(CaseModel::STATUS_UNDER_INVESTIGATION, $case->fresh()->status);
    }

    /**
     * The bug this closes: the badge used to collapse Docketed, Under
     * investigation, and For review into one identical blue, while the
     * dashboard's bar rendered them as three distinct colours one click
     * away. Both now read off CaseModel::STATUS_COLOURS.
     */
    public function test_the_case_list_badge_renders_a_distinct_colour_per_status(): void
    {
        $investigator = User::factory()->investigator()->create();
        CaseModel::factory()->assignedTo($investigator)->create(['status' => CaseModel::STATUS_DOCKETED]);
        CaseModel::factory()->assignedTo($investigator)->create(['status' => CaseModel::STATUS_UNDER_INVESTIGATION]);
        CaseModel::factory()->assignedTo($investigator)->create(['status' => CaseModel::STATUS_FOR_REVIEW]);

        $content = $this->actingAs($investigator)
            ->get(route('cases.index'))
            ->assertOk()
            ->getContent();

        foreach (CaseModel::STATUS_COLOURS as $status => $colour) {
            if (in_array($status, [CaseModel::STATUS_DOCKETED, CaseModel::STATUS_UNDER_INVESTIGATION, CaseModel::STATUS_FOR_REVIEW], true)) {
                $this->assertStringContainsString($colour, $content);
            }
        }

        // The three statuses no longer share one colour.
        $this->assertNotSame(
            CaseModel::STATUS_COLOURS[CaseModel::STATUS_DOCKETED],
            CaseModel::STATUS_COLOURS[CaseModel::STATUS_UNDER_INVESTIGATION]
        );
        $this->assertNotSame(
            CaseModel::STATUS_COLOURS[CaseModel::STATUS_UNDER_INVESTIGATION],
            CaseModel::STATUS_COLOURS[CaseModel::STATUS_FOR_REVIEW]
        );
    }

    // ------------------------------------------------------- docket phase

    public function test_a_case_docketed_this_year_is_new(): void
    {
        $investigator = User::factory()->investigator()->create();
        $case = CaseModel::factory()->assignedTo($investigator)->create();
        $case->timeline()->create(['date_of_docket' => Carbon::now()->startOfYear()]);

        $this->assertSame('New', $case->fresh()->load('timeline')->docket_phase);
    }

    public function test_a_case_docketed_last_year_is_pending(): void
    {
        $investigator = User::factory()->investigator()->create();
        $case = CaseModel::factory()->assignedTo($investigator)->create();
        $case->timeline()->create(['date_of_docket' => Carbon::now()->subYear()->endOfYear()]);

        $this->assertSame('Pending', $case->fresh()->load('timeline')->docket_phase);
    }

    public function test_the_year_boundary_flips_new_to_pending(): void
    {
        $investigator = User::factory()->investigator()->create();

        $newCase = CaseModel::factory()->assignedTo($investigator)->create();
        $newCase->timeline()->create(['date_of_docket' => Carbon::now()->startOfYear()]); // Jan 1 this year

        $pendingCase = CaseModel::factory()->assignedTo($investigator)->create();
        $pendingCase->timeline()->create(['date_of_docket' => Carbon::now()->subYear()->endOfYear()]); // Dec 31 last year

        $this->assertSame('New', $newCase->fresh()->load('timeline')->docket_phase);
        $this->assertSame('Pending', $pendingCase->fresh()->load('timeline')->docket_phase);
    }

    public function test_a_case_with_no_timeline_has_no_docket_phase(): void
    {
        $investigator = User::factory()->investigator()->create();
        $case = CaseModel::factory()->assignedTo($investigator)->create();

        $this->assertNull($case->fresh()->docket_phase);
    }

    public function test_the_docket_phase_is_visible_on_the_case_list(): void
    {
        $investigator = User::factory()->investigator()->create();
        $case = CaseModel::factory()->assignedTo($investigator)->create();
        $case->timeline()->create(['date_of_docket' => Carbon::now()->startOfYear()]);

        $this->actingAs($investigator)
            ->get(route('cases.index'))
            ->assertOk()
            ->assertSee('New');
    }
}
