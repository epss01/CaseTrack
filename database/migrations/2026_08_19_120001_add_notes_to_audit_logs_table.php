<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Free-text context for an audit entry. First consumer:
     * AuditLog::ACTION_EDIT_ON_BEHALF, which requires a supervisor to state
     * why they edited a case they aren't assigned to (CHR-Answers-2026-08-01,
     * item 6) — a reason nobody can read isn't accountability, so it's stored
     * here rather than only validated and discarded.
     */
    public function up(): void
    {
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->text('notes')->nullable()->after('action_performed');
        });
    }

    public function down(): void
    {
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->dropColumn('notes');
        });
    }
};
