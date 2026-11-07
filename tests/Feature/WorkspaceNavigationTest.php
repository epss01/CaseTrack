<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkspaceNavigationTest extends TestCase
{
    use RefreshDatabase;

    public function test_both_case_handling_roles_have_contextual_import_access(): void
    {
        $supervisor = User::factory()->supervisor()->create();
        $html = $this->actingAs($supervisor)->get(route('cases.index'))->assertOk()->getContent();
        [$navigation, $content] = explode('<main', $html, 2);
        $this->assertStringContainsString(route('workload.index'), $navigation);
        $this->assertStringNotContainsString(route('cases.import.create'), $navigation);
        $this->assertStringContainsString(route('cases.import.create'), $content);

        $this->actingAs(User::factory()->investigator()->create())->get(route('cases.index'))
            ->assertOk()->assertSee(route('cases.import.create'), false)
            ->assertDontSee(route('workload.index'), false);
    }

    public function test_admin_navigation_exposes_administration_without_case_destinations(): void
    {
        $this->actingAs(User::factory()->admin()->create())->get(route('admin.registrations.index'))
            ->assertOk()
            ->assertSee(route('admin.registrations.index'), false)
            ->assertSee(route('admin.users.index'), false)
            ->assertSee(route('admin.audit-logs.index'), false)
            ->assertDontSee(route('cases.index'), false)
            ->assertDontSee(route('reports.index'), false)
            ->assertDontSee(route('alerts.index'), false);
    }
}
