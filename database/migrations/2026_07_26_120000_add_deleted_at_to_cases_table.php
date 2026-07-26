<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cases are soft-deleted rather than destroyed.
 *
 * audit_logs.case_id is nullOnDelete, so a hard delete would blank the case_id
 * on the very DELETE entry that is meant to make the deletion traceable.
 * Keeping the row also means a case removed in error can be restored, which
 * matters for an official records system.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cases', function (Blueprint $table) {
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::table('cases', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });
    }
};
