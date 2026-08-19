<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Whether a case is a torture case (CHR-Answers-2026-08-01, item 3) — the
     * 60-day RORP milestone applies only to these. Nullable rather than
     * defaulted: existing cases have no ground truth for this, and a silent
     * default of false would assert a fact nobody verified. Required at
     * intake going forward (StoreCaseRequest), so only pre-existing rows can
     * ever be null.
     *
     * This column alone does not flip CaseDeadlineService::SIXTY_DAY_ENABLED
     * — that also needs the extension-request field and a "60th day RORP
     * filed" column, neither of which is in scope here.
     */
    public function up(): void
    {
        Schema::table('cases', function (Blueprint $table) {
            $table->boolean('is_torture_case')->nullable()->after('complexity_weight');
        });
    }

    public function down(): void
    {
        Schema::table('cases', function (Blueprint $table) {
            $table->dropColumn('is_torture_case');
        });
    }
};
