<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Closes the "who was acted on" gap: every existing admin/account/rating
     * entry named only the actor. Nullable so nothing needs backfilling with
     * a guess, and nullOnDelete (not RESTRICT like user_id) because a target
     * is descriptive, not the attribution the row exists for.
     *
     * The timestamp index is new too — the audit log viewer sorts by it, and
     * nothing has queried this table by anything but id until now.
     */
    public function up(): void
    {
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->foreignId('target_user_id')->nullable()->after('user_id')
                ->constrained('users')->nullOnDelete();
            $table->index('timestamp');
        });
    }

    public function down(): void
    {
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->dropForeign(['target_user_id']);
            $table->dropColumn('target_user_id');
            $table->dropIndex(['timestamp']);
        });
    }
};
