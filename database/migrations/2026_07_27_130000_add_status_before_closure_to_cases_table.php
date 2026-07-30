<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Where a case sat before closure was proposed on it.
 *
 * The maker-checker workflow parks a case at CaseModel::STATUS_PENDING_CLOSURE
 * while it waits for a supervisor's decision, which overwrites whatever status
 * it held. Without this column a rejected proposal could only revert to a fixed
 * value, flattening an "Under investigation" case back to "Docketed" — the case
 * would look like it had regressed to intake because someone asked to close it.
 *
 * Set when closure is proposed, restored and cleared on rejection, cleared on
 * confirmation. Null at every other moment in a case's life.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cases', function (Blueprint $table) {
            $table->string('status_before_closure')->nullable()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('cases', function (Blueprint $table) {
            $table->dropColumn('status_before_closure');
        });
    }
};
