<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('submission_receipts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->char('actor_hash', 64);
            $table->string('action', 100);
            $table->unsignedBigInteger('target_id')->nullable();
            $table->unsignedBigInteger('result_case_id')->nullable();
            $table->boolean('requires_supervisor')->default(false);
            $table->char('request_hash', 64);
            $table->text('redirect_path')->nullable();
            $table->timestamp('completed_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('submission_receipts');
    }
};
