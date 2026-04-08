<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('oxaam_batch_reports', function (Blueprint $table) {
            $table->id();
            $table->string('profile')->default('production');
            $table->string('notification_mode')->default('changed');
            $table->string('target_service')->default('cgai');
            $table->unsignedInteger('runs_requested')->default(1);
            $table->unsignedInteger('runs_completed')->default(0);
            $table->unsignedInteger('successful_runs')->default(0);
            $table->unsignedInteger('failed_runs')->default(0);
            $table->string('snapshot_hash', 64)->nullable()->index();
            $table->string('csv_path')->nullable();
            $table->boolean('should_notify')->default(false);
            $table->string('notification_reason')->nullable();
            $table->string('email_sent_to')->nullable();
            $table->timestamp('email_sent_at')->nullable();
            $table->json('meta')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('oxaam_batch_reports');
    }
};
