<?php

namespace Tests\Feature;

use App\Models\CaseModel;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CaseViewModeTest extends TestCase
{
    use RefreshDatabase;

    public function test_folders_and_list_share_search_scope_and_pagination(): void
    {
        $actor = User::factory()->investigator()->create();
        $this->actingAs($actor)->get(route('cases.index'))->assertOk()->assertViewHas('view', 'folders');
        CaseModel::factory()->assignedTo($actor)->count(16)->create(['case_title' => 'Synthetic matched case']);
        $private = CaseModel::factory()->create(['case_title' => 'Private case']);
        foreach (['folders', 'list'] as $mode) {
            $response = $this->actingAs($actor)->get(route('cases.index', ['view' => $mode, 'search' => 'Synthetic']))
                ->assertOk()->assertViewHas('view', $mode)->assertDontSee($private->docket_no)
                ->assertSee('view='.$mode.'&amp;search=Synthetic&amp;page=2', false);
            $this->assertSame(16, $response->viewData('cases')->total());
            $this->assertCount(15, $response->viewData('cases'));
            $this->get(route('cases.index', ['view' => $mode, 'search' => 'Synthetic', 'page' => 2]))
                ->assertOk()->assertViewHas('view', $mode);
        }
        $this->get(route('cases.index'))->assertOk()->assertViewHas('view', 'list');
        $this->get(route('cases.index', ['view' => 'folders']))->assertSee('case-folder__icon');
        $this->get(route('cases.index', ['view' => 'invalid']))->assertSessionHasErrors('view');
    }

    public function test_empty_states_and_supervisor_folder_metadata(): void
    {
        $actor = User::factory()->investigator()->create();
        $this->actingAs($actor)->get(route('cases.index'))->assertSee('No cases to show.');
        $case = CaseModel::factory()->assignedTo($actor)->create(['case_title' => str_repeat('Long title ', 20)]);
        $this->actingAs(User::factory()->supervisor()->create())->get(route('cases.index'))
            ->assertOk()->assertSee($actor->full_name)->assertSee($case->case_title)->assertSee('Year grouping');
        $this->get(route('cases.index', ['search' => 'No match']))->assertSee('No cases match this search.');
        $this->actingAs(User::factory()->admin()->create())->get(route('cases.index'))->assertForbidden();
    }
}
