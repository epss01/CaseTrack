<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('case_timelines', function (Blueprint $table) {
            $table->date('date_extension_30_days_requested')->nullable();
            $table->date('date_submission_120th_day_rop')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('case_timelines', function (Blueprint $table) {
            $table->dropColumn(['date_extension_30_days_requested', 'date_submission_120th_day_rop']);
        });
    }
};
