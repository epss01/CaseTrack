<?php

namespace Tests\Feature;

use App\Models\CaseModel;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class CaseLockVersionMigrationTest extends TestCase
{
    use RefreshDatabase;

    private function migration(): Migration
    {
        return require database_path('migrations/2026_10_25_000000_add_lock_version_to_cases_table.php');
    }

    public function test_rollback_removes_only_the_lock_version_column(): void
    {
        $this->assertTrue(Schema::hasColumn('cases', 'lock_version'));

        $this->migration()->down();

        $this->assertFalse(Schema::hasColumn('cases', 'lock_version'));
        $this->assertTrue(Schema::hasColumn('cases', 'docket_no'));
        $this->assertTrue(Schema::hasColumn('cases', 'updated_at'));

        $this->migration()->up();

        $case = CaseModel::factory()->create();
        $this->assertSame(0, $case->fresh()->lock_version);
    }
}
