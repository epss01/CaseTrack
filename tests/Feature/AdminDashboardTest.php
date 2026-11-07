<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AdminDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_counts_approved_accounts_and_shows_the_five_oldest_pending_registrations(): void
    {
        $admin = User::factory()->admin()->create();
        User::factory()->count(2)->create();
        User::factory()->create(['is_active' => false]);
        User::factory()->create(['registration_status' => User::REGISTRATION_REJECTED]);
        $pending = collect(range(1, 6))->map(fn ($day) => User::factory()->create([
            'registration_status' => User::REGISTRATION_PENDING,
            'created_at' => "2026-01-0{$day} 12:00:00",
        ]));

        DB::enableQueryLog();
        $this->actingAs($admin)->get(route('home'))->assertOk()
            ->assertViewIs('admin.dashboard')
            ->assertViewHas('pendingCount', 6)
            ->assertViewHas('activeCount', 3)
            ->assertViewHas('inactiveCount', 1)
            ->assertViewHas('pending', fn ($queue) => $queue->pluck('id')->all() === $pending->take(5)->pluck('id')->all())
            ->assertSee(route('admin.registrations.index'), false)
            ->assertDontSee(route('cases.index'), false);
        $queries = collect(DB::getQueryLog())->pluck('query')->implode('\n');
        DB::disableQueryLog();
        $this->assertDoesNotMatchRegularExpression('/\b(cases|case_timelines)\b/i', $queries);
    }

    public function test_empty_queue_is_explained(): void
    {
        $this->actingAs(User::factory()->admin()->create())->get(route('home'))
            ->assertOk()->assertSee('No registrations awaiting review.');
    }

    public function test_other_roles_keep_their_own_dashboards(): void
    {
        $this->actingAs(User::factory()->investigator()->create())->get(route('home'))
            ->assertOk()->assertViewIs('dashboard')->assertDontSee('Pending registrations');
        $this->actingAs(User::factory()->supervisor()->create())->get(route('home'))
            ->assertOk()->assertViewIs('supervisor-dashboard')->assertDontSee('Pending registrations');
    }
}
