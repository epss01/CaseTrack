<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Account deactivation flag.
 *
 * Distinct from registration_status: that gate gets crossed once, at
 * approval; this one can be flipped at any time, regardless of how old the
 * account is or how it was provisioned. A soft flag rather than a delete,
 * matching the cases.deleted_at precedent — audit_logs.user_id is NOT NULL
 * with no onDelete clause (RESTRICT), so a hard-deleted user with any audit
 * history would already fail at the database.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_active')->default(true)->after('approved_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('is_active');
        });
    }
};
