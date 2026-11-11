<?php

namespace Tests\Feature;

use App\Models\CaseModel;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\MessageBag;
use Illuminate\Support\ViewErrorBag;
use Tests\TestCase;

class DailyWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private function caseFor(User $owner, string $deadline, array $attributes = []): CaseModel
    {
        $case = CaseModel::factory()->assignedTo($owner)->create($attributes + ['status' => CaseModel::STATUS_DOCKETED]);
        $case->timeline()->create(['date_of_docket' => '2026-01-01', 'extension_30_days' => $deadline, 'submission_120th_day' => '2027-01-01']);

        return $case;
    }

    public function test_attention_is_limited_sorted_and_scoped_to_active_visible_cases(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-11'));
        $owner = User::factory()->investigator()->create();
        $other = User::factory()->investigator()->create();
        $cases = collect();
        foreach (['2026-11-25', '2026-11-11', '2026-11-10', '2026-11-09', '2026-11-09', '2026-11-08'] as $deadline) {
            $cases->push($this->caseFor($owner, $deadline));
        }
        $foreign = $this->caseFor($other, '2026-01-02');
        $closed = $this->caseFor($owner, '2026-01-02', ['status' => CaseModel::STATUS_CLOSED]);
        $deleted = $this->caseFor($owner, '2026-01-02');
        $deleted->delete();
        $this->actingAs($owner)->get('/home')->assertOk()
            ->assertViewHas('attentionRows', fn ($rows) => array_column(array_column($rows, 'case'), 'id') === [
                $cases[5]->id, $cases[3]->id, $cases[4]->id, $cases[2]->id, $cases[1]->id,
            ])
            ->assertDontSee($foreign->docket_no)
            ->assertDontSee($closed->docket_no)
            ->assertDontSee($deleted->docket_no)
            ->assertSee(route('cases.show', $cases[5]).'#case-timeline', false);

        $supervisor = User::factory()->supervisor()->create();
        $this->actingAs($supervisor)->get('/home')->assertOk()
            ->assertViewHas('attentionRows', fn ($rows) => $rows[0]['case']->id === $foreign->id)
            ->assertSee($other->full_name);
        $this->actingAs(User::factory()->admin()->create())->get('/home')->assertOk()
            ->assertViewMissing('attentionRows')->assertDontSee($foreign->docket_no);
    }

    public function test_due_soon_boundaries_and_untracked_and_completed_cases_remain_distinct(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-11-11'));
        $owner = User::factory()->investigator()->create();
        $today = $this->caseFor($owner, '2026-11-11');
        $lastDay = $this->caseFor($owner, '2026-11-25');
        $this->caseFor($owner, '2026-11-26');
        CaseModel::factory()->assignedTo($owner)->create(['status' => CaseModel::STATUS_DOCKETED]);
        $done = $this->caseFor($owner, '2026-01-02');
        $done->timeline->update(['date_fir_submitted' => '2026-11-01']);
        $this->actingAs($owner)->get('/home')->assertOk()
            ->assertViewHas('untrackedCount', 1)
            ->assertViewHas('attentionRows', fn ($rows) => array_column(array_column($rows, 'case'), 'id') === [$today->id, $lastDay->id]);
        $this->get('/alerts')->assertOk()->assertViewHas('untrackedCount', 1)
            ->assertViewHas('onTrackCount', 2)->assertViewHas('dueSoonCount', 2)
            ->assertViewHas('rows', fn ($rows) => $rows->total() === 2);
    }

    public function test_case_priorities_distinguish_outstanding_missing_completed_and_closed_timelines(): void
    {
        $owner = User::factory()->investigator()->create();
        $case = $this->caseFor($owner, '2026-11-10');
        $this->actingAs($owner)->get(route('cases.show', $case))->assertOk()
            ->assertSeeInOrder(['Current status', 'Assigned investigator', $owner->full_name, 'Next outstanding obligation'])
            ->assertViewHas('nextObligation', fn ($next) => $next['days'] === 30)
            ->assertSee('View full timeline');
        $case->timeline->update(['date_fir_submitted' => '2026-11-01']);
        $this->get(route('cases.show', $case))->assertSee('No outstanding tracked obligations.');
        $case->update(['status' => CaseModel::STATUS_CLOSED]);
        $this->get(route('cases.show', $case))->assertSee('Case closed. See the recorded timeline.');
        $case->timeline()->delete();
        $case->update(['status' => CaseModel::STATUS_DOCKETED]);
        $this->get(route('cases.show', $case))->assertSee('Deadline unavailable: no docket date or timeline recorded.');
        $this->actingAs(User::factory()->investigator()->create())->get(route('cases.show', $case))->assertForbidden();
        $this->actingAs(User::factory()->admin()->create())->get(route('cases.show', $case))->assertForbidden();
    }

    public function test_analytics_preserves_failed_filter_input_and_keeps_the_applied_period_outside_filters(): void
    {
        $owner = User::factory()->investigator()->create();
        $this->actingAs($owner)->withSession([
            '_old_input' => ['from' => '2026-06-30', 'to' => '2026-01-01', 'status' => 'For review'],
            'errors' => (new ViewErrorBag)->put('default', new MessageBag(['to' => 'End must follow start.'])),
        ])->get('/reports/analytics?from=2026-01-01&to=2026-06-30')->assertOk()
            ->assertSeeInOrder(['01 Jan 2026', '30 Jun 2026', 'data-analytics-filters'], false)
            ->assertSee('data-has-errors="true"', false)
            ->assertSee('value="2026-06-30"', false)
            ->assertSee('value="For review"', false)
            ->assertSee('End must follow start.')
            ->assertSee('Total: 0 cases = 0 resolved during period + 0 pending at period end.');
    }
}
