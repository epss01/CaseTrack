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
        Schema::table('case_timelines', function (Blueprint $table) {
            $table->date('extension_60th_days')
                ->nullable()
                ->after('extension_30_days')
                ->comment('Stores the extended 60-day deadline when an extension is granted for torture cases');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('case_timelines', function (Blueprint $table) {
            $table->dropColumn('extension_60th_days');
        });
    }
};