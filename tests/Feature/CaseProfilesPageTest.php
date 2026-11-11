<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\CaseModel;
use App\Models\CaseTimeline;
use App\Models\Complainant;
use App\Models\Respondent;
use App\Models\User;
use App\Models\Victim;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CaseProfilesPageTest extends TestCase
{
    use RefreshDatabase;

    private const URL = '/reports/analytics/case-profiles';

    private const PERIOD = '?from=2026-03-01&to=2026-03-31';

    private function submission(User $owner, int $weight, string $docket, ?string $fir): CaseModel
    {
        $case = CaseModel::factory()->assignedTo($owner)->create(['complexity_weight' => $weight]);
        CaseTimeline::create(['case_id' => $case->id, 'date_of_docket' => $docket, 'date_fir_submitted' => $fir]);

        return $case;
    }

    public function test_only_authorized_cases_affect_fit_or_exclusions_and_drill_down(): void
    {
        $owner = User::factory()->investigator()->create();
        $other = User::factory()->investigator()->create();
        $this->actingAs($owner);
        $empty = $this->get(self::URL.self::PERIOD)->assertOk()->viewData('report');
        $hidden = $this->submission($other, 1, '2026-02-01', '2026-03-01');
        $this->assertSame($empty, $this->get(self::URL.self::PERIOD)->viewData('report'));
        for ($i = 0; $i < 5; $i++) {
            $sameDay = $this->submission($owner, 1, '2026-03-01', '2026-03-01');
            $this->submission($owner, 3, '2026-02-28', '2026-03-01');
            $many = $this->submission($owner, 5, '2026-02-01', '2026-03-31');
            foreach ([Victim::class, Respondent::class, Complainant::class] as $model) {
                $model::factory()->create(['case_id' => $many->id, 'name' => 'Private party']);
            }
        }
        $this->submission($owner, 0, '2026-02-01', '2026-03-01');
        $this->submission($owner, 2, '2026-04-01', '2026-03-31');
        $this->submission($owner, 2, '2026-01-01', '2026-04-01');
        $this->submission($owner, 2, '2026-01-01', null);
        $this->submission($owner, 2, '2026-01-01', '2026-03-01')->delete();
        CaseModel::factory()->assignedTo($owner)->create();
        $page = $this->get(self::URL.self::PERIOD)->assertOk()
            ->assertSee(route('reports.show', $sameDay))->assertDontSee($hidden->docket_no)->assertDontSee('Private party');
        $report = $page->viewData('report');
        $this->assertSame(17, $report['submitted']);
        $this->assertSame(15, $report['eligible']);
        $this->assertEquals([5, 3, 58], $report['profiles'][2]['means']);
        $this->assertEquals([1, 0, 0], $report['profiles'][0]['means']);
        $hidden->update(['complexity_weight' => 4]);
        $this->assertSame($report, $this->get(self::URL.self::PERIOD)->viewData('report'));
        $this->assertSame(0, AuditLog::count());
        $this->get(route('reports.show', $hidden))->assertForbidden();
        $this->get(route('reports.show', $sameDay))->assertOk();

        $this->actingAs(User::factory()->supervisor()->create());
        $supervisor = $this->get(self::URL.self::PERIOD)->assertOk()->viewData('report');
        $this->assertSame(18, $supervisor['submitted']);
        $this->get(route('reports.show', $hidden))->assertOk();
    }

    public function test_role_middleware_and_date_validation(): void
    {
        $this->get(self::URL)->assertRedirect(route('login'));
        foreach ([User::factory()->admin(), User::factory()->withRole('Records Clerk')] as $factory) {
            $this->actingAs($factory->create())->get(self::URL)->assertForbidden();
        }
        $this->actingAs(User::factory()->investigator()->create());
        $this->get(self::URL.'?from=2026-03-01&to=2026-02-01')->assertSessionHasErrors('to');
        $this->get(self::URL.'?from=2026-02-30')->assertSessionHasErrors('from');
        $this->get(self::URL)->assertOk()->assertViewHas('from', today()->startOfYear()->toDateString())
            ->assertViewHas('to', today()->toDateString());
    }
}
