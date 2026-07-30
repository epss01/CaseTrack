<?php

use App\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Registration approval gate.
 *
 * `registration_status` defaults to 'pending' — fail closed, so a row
 * inserted by anything other than RegisterController or the Admin
 * account-management screen still can't authenticate. `approved_by` and
 * `approved_at` record who approved a registration and when, independent of
 * the general audit_logs trail (audit_logs has no per-row detail beyond the
 * action constant, so this is the only place that answer lives).
 *
 * Every account already in the database predates this feature and was
 * already in active use, so it backfills to 'approved' in this same
 * migration — this can't retroactively lock out every existing user.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('registration_status')->default(User::REGISTRATION_PENDING)->after('role_id');
            $table->foreignId('approved_by')->nullable()->after('registration_status')->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable()->after('approved_by');
        });

        DB::table('users')->update(['registration_status' => User::REGISTRATION_APPROVED]);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('approved_by');
            $table->dropColumn(['registration_status', 'approved_at']);
        });
    }
};
