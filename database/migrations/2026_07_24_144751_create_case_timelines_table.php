<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('case_timelines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('case_id')->unique()->constrained('cases')->cascadeOnDelete();
            $table->date('date_of_docket');
            $table->date('date_submission_rop')->nullable();
            $table->date('extension_30_days')->nullable();
            $table->date('submission_60th_day')->nullable();
            $table->date('submission_120th_day')->nullable();
            $table->date('target_date_fir')->nullable();
            $table->date('date_fir_submitted')->nullable();
            $table->string('date_submitted_to')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('case_timelines');
    }
};
