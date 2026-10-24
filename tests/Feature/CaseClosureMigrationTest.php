<?php

namespace Tests\Feature;

use App\Models\CaseModel;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Tests\TestCase;

class CaseClosureMigrationTest extends TestCase
{
    use RefreshDatabase;

    private function migration(): Migration
    {
        return require database_path('migrations/2026_10_24_000000_drop_status_before_closure_from_cases_table.php');
    }

    public function test_up_and_down_work_on_clean_data(): void
    {
        $this->assertFalse(Schema::hasColumn('cases', 'status_before_closure'));

        $this->migration()->down();
        $this->assertTrue(Schema::hasColumn('cases', 'status_before_closure'));

        $this->migration()->up();
        $this->assertFalse(Schema::hasColumn('cases', 'status_before_closure'));
    }

    public function test_up_refuses_a_pending_closure(): void
    {
        $migration = $this->migration();
        $migration->down();
        $case = CaseModel::factory()->create(['status' => CaseModel::STATUS_DOCKETED]);
        DB::table('cases')->where('id', $case->id)->update(['status' => 'Pending Closure']);

        $this->assertGuardStopsMigration($migration);
    }

    public function test_up_refuses_a_stored_prior_status(): void
    {
        $migration = $this->migration();
        $migration->down();
        $case = CaseModel::factory()->create(['status' => CaseModel::STATUS_DOCKETED]);
        DB::table('cases')->where('id', $case->id)->update(['status_before_closure' => CaseModel::STATUS_FOR_REVIEW]);

        $this->assertGuardStopsMigration($migration);
    }

    private function assertGuardStopsMigration(Migration $migration): void
    {
        try {
            $migration->up();
            $this->fail('The migration guard did not stop the destructive schema change.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('Cannot drop cases.status_before_closure', $exception->getMessage());
        }

        $this->assertTrue(Schema::hasColumn('cases', 'status_before_closure'));
    }
}
