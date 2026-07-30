<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * P_i in the Workload Capacity Score: WCS_i = sum(C_j) x (2 - P_i).
 *
 * A normalized performance rating between 0.1 and 1.0, set by a supervisor.
 * It defaults to 1.0 because (2 - 1.0) = 1.0 — an unrated investigator's score
 * is simply the sum of their active cases' complexity weights, so the formula
 * behaves sensibly before anyone has been rated.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->decimal('performance_rating', 2, 1)->default(1.0)->after('is_staff');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('performance_rating');
        });
    }
};
