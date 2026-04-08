<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('oxaam_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('oxaam_session_id')->nullable()->constrained()->nullOnDelete();
            $table->string('target_service')->default('cgai');
            $table->string('status')->default('success');
            $table->unsignedSmallInteger('http_status')->nullable();
            $table->unsignedInteger('duration_ms')->nullable();
            $table->unsignedInteger('session_uses_after')->nullable();
            $table->string('service_label')->nullable();
            $table->string('page_title')->nullable();
            $table->string('dashboard_name')->nullable();
            $table->string('account_email')->nullable();
            $table->string('account_password')->nullable();
            $table->string('code_url')->nullable();
            $table->json('report')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('scraped_at')->nullable();
            $table->timestamps();

            $table->index(['target_service', 'status']);
            $table->index('scraped_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('oxaam_runs');
    }
};
