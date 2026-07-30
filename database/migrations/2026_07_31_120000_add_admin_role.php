<?php

use App\Models\Role;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Adds the Admin role row directly, rather than only through RoleSeeder.
 *
 * RoleSeeder only ever runs against a fresh database (`db:seed`) or the
 * in-memory SQLite test database (RefreshDatabase re-runs migrations, not
 * seeders, unless a test calls $this->seed()) — the live MySQL database
 * behind this app was seeded once and nobody re-runs seeders against it. A
 * migration is the only thing guaranteed to run against every environment,
 * so this is the one place the Admin role actually reaches production data.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('roles')->updateOrInsert(
            ['role_name' => Role::ADMIN],
            ['updated_at' => now(), 'created_at' => now()]
        );
    }

    public function down(): void
    {
        DB::table('roles')->where('role_name', Role::ADMIN)->delete();
    }
};
