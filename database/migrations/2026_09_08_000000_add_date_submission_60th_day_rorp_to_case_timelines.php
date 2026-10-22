<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Adds a nullable date column to record when the 60th-day RORP was actually submitted.
     * This column serves as the discharged_by for the 60th day milestone in CaseDeadlineService.
     * It mirrors date_submission_rop and date_fir_submitted as the actual discharging event
     * for the 60-day deadline.
     */
    public function up(): void
    {
        Schema::table('case_timelines', function (Blueprint $table) {
            $table->date('date_submission_60th_day_rorp')
                ->nullable()
                ->comment('When the 60th-day RORP was actually submitted')
                ->after('submission_60th_day');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('case_timelines', function (Blueprint $table) {
            $table->dropColumn('date_submission_60th_day_rorp');
        });
    }
};