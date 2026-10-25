<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Remove the legacy closure rollback field only when no unresolved closure
     * data exists. Every environment must make the disposition decision
     * explicitly instead of having a migration silently rewrite case status.
     */
    public function up(): void
    {
        $pendingClosures = DB::table('cases')->where('status', 'Pending Closure')->count();
        $storedPriorStatuses = DB::table('cases')->whereNotNull('status_before_closure')->count();

        if ($pendingClosures > 0 || $storedPriorStatuses > 0) {
            throw new RuntimeException(
                "Cannot drop cases.status_before_closure: found {$pendingClosures} Pending Closure case(s) "
                ."and {$storedPriorStatuses} case(s) with a stored prior status. Resolve them explicitly before migrating."
            );
        }

        Schema::table('cases', function (Blueprint $table) {
            $table->dropColumn('status_before_closure');
        });
    }

    /**
     * Restore only the nullable schema column. Removed historical values
     * cannot be reconstructed by a rollback.
     */
    public function down(): void
    {
        Schema::table('cases', function (Blueprint $table) {
            $table->string('status_before_closure')->nullable()->after('status');
        });
    }
};
