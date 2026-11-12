<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\CaseModel;
use App\Models\User;
use App\Models\Victim;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CaseClassificationReviewTest extends TestCase
{
    use RefreshDatabase;

    private function caseFor(User $actor, ?bool $classification = null, array $extra = []): CaseModel
    {
        return CaseModel::factory()->assignedTo($actor)->create(array_merge([
            'status' => CaseModel::STATUS_DOCKETED, 'is_torture_case' => $classification,
        ], $extra));
    }

    public function test_unknown_filter_and_counts_are_role_scoped_and_read_only(): void
    {
        $actor = User::factory()->investigator()->create();
        $own = $this->caseFor($actor);
        $closed = $this->caseFor($actor, extra: ['status' => CaseModel::STATUS_CLOSED]);
        $known = $this->caseFor($actor, true);
        $knownNo = $this->caseFor($actor, false);
        $other = $this->caseFor(User::factory()->investigator()->create());
        $deleted = $this->caseFor($actor);
        $deleted->delete();
        $before = AuditLog::count();
        $this->actingAs($actor)->get(route('cases.index', ['classification' => 'unknown']))->assertOk()
            ->assertSee($own->docket_no)->assertSee($closed->docket_no)
            ->assertDontSee($known->docket_no)->assertDontSee($knownNo->docket_no)->assertDontSee($other->docket_no)->assertDontSee($deleted->docket_no)
            ->assertViewHas('unknownTotal', 2)->assertViewHas('unknownActive', 1)
            ->assertSee('does not certify that every case has been classified');
        $this->actingAs(User::factory()->supervisor()->create())->get(route('cases.index', ['classification' => 'unknown']))->assertOk()
            ->assertSee($other->docket_no)->assertViewHas('unknownTotal', 3)->assertViewHas('unknownActive', 2);
        $this->assertSame($before, AuditLog::count());
        $this->assertNull($own->fresh()->is_torture_case);
        $this->actingAs(User::factory()->admin()->create())->get(route('cases.index', ['classification' => 'unknown']))->assertForbidden();
        $this->app['auth']->forgetGuards();
        $this->get(route('cases.index', ['classification' => 'unknown']))->assertRedirect(route('login'));
    }

    public function test_active_cases_precede_closed_cases_only_in_review_mode(): void
    {
        $actor = User::factory()->investigator()->create();
        $closed = $this->caseFor($actor, extra: ['docket_no' => 'ZZZ-CLOSED', 'status' => CaseModel::STATUS_CLOSED]);
        $active = $this->caseFor($actor, extra: ['docket_no' => 'AAA-ACTIVE']);
        $this->actingAs($actor)->get(route('cases.index', ['classification' => 'unknown']))->assertOk()->assertSeeInOrder([$active->docket_no, $closed->docket_no]);
        $this->get(route('cases.index'))->assertOk()->assertSeeInOrder([$closed->docket_no, $active->docket_no]);
    }

    public function test_search_views_and_pagination_preserve_the_review_filter(): void
    {
        $actor = User::factory()->investigator()->create();
        CaseModel::factory()->assignedTo($actor)->count(16)->create(['status' => CaseModel::STATUS_DOCKETED, 'is_torture_case' => null, 'case_title' => 'Synthetic matching review']);
        $excluded = $this->caseFor($actor, true, ['case_title' => 'Synthetic matching known']);
        foreach (['folders', 'list'] as $mode) {
            $response = $this->actingAs($actor)->get(route('cases.index', ['classification' => 'unknown', 'search' => 'Synthetic', 'view' => $mode]))->assertOk()
                ->assertViewHas('view', $mode)->assertViewHas('classification', 'unknown')
                ->assertDontSee($excluded->docket_no)->assertSee('classification=unknown&amp;search=Synthetic&amp;view='.$mode.'&amp;page=2', false)
                ->assertSee('name="classification" value="unknown"', false);
            $this->assertSame(16, $response->viewData('cases')->total());
            $this->assertSame(16, $response->viewData('unknownTotal'));
            $this->get(route('cases.index', ['classification' => 'unknown', 'search' => 'Synthetic', 'view' => $mode, 'page' => 2]))->assertOk()->assertViewHas('view', $mode);
        }
    }

    public function test_party_search_empty_states_and_invalid_filters(): void
    {
        $actor = User::factory()->investigator()->create();
        $this->actingAs($actor)->get(route('cases.index', ['classification' => 'unknown']))->assertOk()->assertSee('No Unknown cases to review.');
        $case = $this->caseFor($actor);
        Victim::factory()->create(['case_id' => $case->id, 'name' => 'Synthetic searchable person']);
        $this->get(route('cases.index', ['classification' => 'unknown', 'search' => 'searchable person']))->assertOk()->assertSee($case->docket_no);
        $this->get(route('cases.index', ['classification' => 'unknown', 'search' => 'No matching record']))->assertOk()
            ->assertSee('No Unknown cases match this search.')->assertViewHas('unknownTotal', 1)->assertViewHas('unknownActive', 1);
        $this->get(route('cases.index', ['classification' => 'unknown', 'page' => 99]))->assertOk()
            ->assertSee('No cases on this page.')->assertSee('Return to the first page')->assertDontSee('No Unknown cases to review.');
        foreach (['yes', 'false', ['unknown']] as $invalid) {
            $this->get(route('cases.index', ['classification' => $invalid]))->assertSessionHasErrors('classification');
        }
    }

    public function test_existing_editor_links_return_to_review_and_saved_classification_refreshes_counts(): void
    {
        $actor = User::factory()->investigator()->create();
        $case = $this->caseFor($actor);
        $remaining = $this->caseFor($actor);
        $reviewUrl = route('cases.edit', [$case, 'classification' => 'unknown']);
        foreach (['folders', 'list'] as $mode) {
            $this->actingAs($actor)->get(route('cases.index', ['classification' => 'unknown', 'view' => $mode]))->assertOk()->assertSee($reviewUrl, false)->assertSee('Review classification');
        }
        $this->get($reviewUrl)->assertOk()->assertSee('Save and return to review')->assertSee('Return to classification review without saving');
        $fields = $case->only(['case_title', 'incident_details', 'source_info', 'status', 'complexity_weight', 'lock_version']);
        $this->put(route('cases.update', [$case, 'classification' => 'unknown']), $fields + ['is_torture_case' => '0'])
            ->assertSessionHasNoErrors()->assertRedirect(route('cases.index', ['classification' => 'unknown']));
        $this->assertFalse($case->fresh()->is_torture_case);
        $this->assertNull($remaining->fresh()->is_torture_case);
        $this->assertDatabaseHas('audit_logs', ['case_id' => $case->id, 'user_id' => $actor->id, 'action_performed' => AuditLog::ACTION_CLASSIFICATION_CHANGED]);
        $this->get(route('cases.index', ['classification' => 'unknown']))->assertOk()->assertViewHas('view', 'list')
            ->assertViewHas('unknownTotal', 1)->assertViewHas('unknownActive', 1)->assertDontSee($case->docket_no)->assertSee($remaining->docket_no);
    }
}
