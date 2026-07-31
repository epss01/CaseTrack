<?php

namespace Tests\Feature;

use App\Models\CaseModel;
use App\Models\CaseTimeline;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The paginated case listing at /cases: the page built for volume, so it
 * should not show less than the dashboards do.
 */
class CaseListingPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_listing_shows_date_of_docket_and_the_complexity_meter(): void
    {
        $investigator = User::factory()->investigator()->create();

        $case = CaseModel::factory()->assignedTo($investigator)->create([
            'status' => CaseModel::STATUS_DOCKETED,
            'complexity_weight' => 4,
        ]);

        $docketedOn = CarbonImmutable::parse('2026-03-02');

        CaseTimeline::factory()
            ->docketedOn($docketedOn)
            ->create(['case_id' => $case->id]);

        $this->actingAs($investigator)
            ->get(route('cases.index'))
            ->assertOk()
            ->assertSee($docketedOn->format('d M Y'))
            ->assertSee('Complexity 4 of 5');
    }

    public function test_a_case_without_a_timeline_still_renders_in_the_listing(): void
    {
        $investigator = User::factory()->investigator()->create();

        // Intake always writes a timeline row, but nothing in the schema
        // forces one — the listing must not fall over on a case that has none.
        $case = CaseModel::factory()->assignedTo($investigator)->create([
            'status' => CaseModel::STATUS_DOCKETED,
        ]);

        $this->actingAs($investigator)
            ->get(route('cases.index'))
            ->assertOk()
            ->assertSee($case->docket_no);
    }

    public function test_a_supervisor_sees_the_new_columns_alongside_investigator(): void
    {
        $supervisor = User::factory()->supervisor()->create();
        CaseModel::factory()->create(['status' => CaseModel::STATUS_DOCKETED]);

        $this->actingAs($supervisor)
            ->get(route('cases.index'))
            ->assertOk()
            ->assertSeeInOrder(['Complexity', 'Date of Docket', 'Investigator']);
    }

    public function test_the_listing_paginates_at_fifteen(): void
    {
        $investigator = User::factory()->investigator()->create();

        $cases = CaseModel::factory()
            ->assignedTo($investigator)
            ->count(16)
            ->sequence(fn ($sequence) => ['docket_no' => 'CHR-VIII-PAGE-'.str_pad($sequence->index, 4, '0', STR_PAD_LEFT)])
            ->create();

        $first = $cases->sortBy('docket_no')->last(); // latest('docket_no') puts the highest first.
        $sixteenth = $cases->sortBy('docket_no')->first();

        $this->actingAs($investigator)
            ->get(route('cases.index'))
            ->assertOk()
            ->assertSee($first->docket_no)
            ->assertDontSee($sixteenth->docket_no);

        $this->actingAs($investigator)
            ->get(route('cases.index', ['page' => 2]))
            ->assertOk()
            ->assertSee($sixteenth->docket_no)
            ->assertDontSee($first->docket_no);
    }
}
