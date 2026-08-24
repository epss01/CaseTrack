<?php

namespace Tests\Feature;

use App\Models\CaseModel;
use App\Models\Complainant;
use App\Models\Respondent;
use App\Models\User;
use App\Models\Victim;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The free-text search on /cases: docket no., title, or the name of any
 * linked victim, respondent, or complainant. No standalone people-directory
 * page exists, so this is the one place "search the case and people tables"
 * (roadmap/sprint-checklist.md) resolves to.
 */
class CaseSearchTest extends TestCase
{
    use RefreshDatabase;

    public function test_search_matches_the_docket_number(): void
    {
        $investigator = User::factory()->investigator()->create();

        $matching = CaseModel::factory()->assignedTo($investigator)->create(['docket_no' => 'CHR-VIII-2026-0099']);
        $control = CaseModel::factory()->assignedTo($investigator)->create(['docket_no' => 'CHR-VIII-2026-0001']);

        $this->actingAs($investigator)
            ->get(route('cases.index', ['search' => '0099']))
            ->assertOk()
            ->assertSee($matching->docket_no)
            ->assertDontSee($control->docket_no);
    }

    public function test_search_matches_the_case_title(): void
    {
        $investigator = User::factory()->investigator()->create();

        $matching = CaseModel::factory()->assignedTo($investigator)->create(['case_title' => 'Illegal Arrest Incident']);
        $control = CaseModel::factory()->assignedTo($investigator)->create(['case_title' => 'Property Dispute']);

        $this->actingAs($investigator)
            ->get(route('cases.index', ['search' => 'Arrest']))
            ->assertOk()
            ->assertSee($matching->docket_no)
            ->assertDontSee($control->docket_no);
    }

    public function test_search_matches_a_linked_victims_name(): void
    {
        $investigator = User::factory()->investigator()->create();

        $matching = CaseModel::factory()->assignedTo($investigator)->create();
        Victim::factory()->create(['case_id' => $matching->id, 'name' => 'Juan Dela Cruz']);

        $control = CaseModel::factory()->assignedTo($investigator)->create();
        Victim::factory()->create(['case_id' => $control->id, 'name' => 'Pedro Santos']);

        $this->actingAs($investigator)
            ->get(route('cases.index', ['search' => 'Dela Cruz']))
            ->assertOk()
            ->assertSee($matching->docket_no)
            ->assertDontSee($control->docket_no);
    }

    public function test_search_matches_a_linked_respondents_name(): void
    {
        $investigator = User::factory()->investigator()->create();

        $matching = CaseModel::factory()->assignedTo($investigator)->create();
        Respondent::factory()->create(['case_id' => $matching->id, 'name' => 'PO2 Mark Reyes']);

        $control = CaseModel::factory()->assignedTo($investigator)->create();
        Respondent::factory()->create(['case_id' => $control->id, 'name' => 'SPO1 Ana Villar']);

        $this->actingAs($investigator)
            ->get(route('cases.index', ['search' => 'Mark Reyes']))
            ->assertOk()
            ->assertSee($matching->docket_no)
            ->assertDontSee($control->docket_no);
    }

    public function test_search_matches_a_linked_complainants_name(): void
    {
        $investigator = User::factory()->investigator()->create();

        $matching = CaseModel::factory()->assignedTo($investigator)->create();
        Complainant::factory()->create(['case_id' => $matching->id, 'name' => 'Maria Clara Gomez']);

        $control = CaseModel::factory()->assignedTo($investigator)->create();
        Complainant::factory()->create(['case_id' => $control->id, 'name' => 'Jose Rizal']);

        $this->actingAs($investigator)
            ->get(route('cases.index', ['search' => 'Maria Clara']))
            ->assertOk()
            ->assertSee($matching->docket_no)
            ->assertDontSee($control->docket_no);
    }

    /**
     * The security test: search must run inside scopeVisibleTo(), not around
     * it. A colleague's case matching the term must stay invisible to an
     * investigator, exactly as it would with no search term at all.
     */
    public function test_search_cannot_reach_a_case_outside_the_requesters_visibility(): void
    {
        $investigator = User::factory()->investigator()->create();
        $colleague = User::factory()->investigator()->create();

        $theirs = CaseModel::factory()->assignedTo($colleague)->create(['case_title' => 'Unique Colleague Case Title']);

        $this->actingAs($investigator)
            ->get(route('cases.index', ['search' => 'Unique Colleague']))
            ->assertOk()
            ->assertDontSee($theirs->docket_no);
    }

    public function test_a_supervisors_search_spans_the_whole_office(): void
    {
        $supervisor = User::factory()->supervisor()->create();
        $first = CaseModel::factory()->create(['case_title' => 'Shared Search Term Alpha']);
        $second = CaseModel::factory()->create(['case_title' => 'Shared Search Term Beta']);

        $this->actingAs($supervisor)
            ->get(route('cases.index', ['search' => 'Shared Search Term']))
            ->assertOk()
            ->assertSee($first->docket_no)
            ->assertSee($second->docket_no);
    }

    public function test_the_search_term_round_trips_into_the_input(): void
    {
        $investigator = User::factory()->investigator()->create();

        $this->actingAs($investigator)
            ->get(route('cases.index', ['search' => 'Dela Cruz']))
            ->assertOk()
            ->assertSee('value="Dela Cruz"', escape: false);
    }

    public function test_the_search_term_survives_onto_the_pagination_links(): void
    {
        $investigator = User::factory()->investigator()->create();

        CaseModel::factory()->assignedTo($investigator)->count(16)->create([
            'case_title' => 'Recurring Arrest Pattern',
        ]);

        $this->actingAs($investigator)
            ->get(route('cases.index', ['search' => 'Recurring']))
            ->assertOk()
            ->assertSee('search=Recurring', escape: false);
    }

    public function test_an_empty_search_behaves_like_no_search_at_all(): void
    {
        $investigator = User::factory()->investigator()->create();
        $case = CaseModel::factory()->assignedTo($investigator)->create();

        $this->actingAs($investigator)
            ->get(route('cases.index', ['search' => '']))
            ->assertOk()
            ->assertSee($case->docket_no)
            ->assertDontSee(__('No cases match this search.'));
    }
}
