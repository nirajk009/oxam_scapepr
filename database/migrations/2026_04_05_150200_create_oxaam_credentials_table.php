<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('oxaam_credentials', function (Blueprint $table) {
            $table->id();
            $table->foreignId('first_seen_run_id')->nullable()->constrained('oxaam_runs')->nullOnDelete();
            $table->foreignId('last_seen_run_id')->nullable()->constrained('oxaam_runs')->nullOnDelete();
            $table->foreignId('last_session_id')->nullable()->constrained('oxaam_sessions')->nullOnDelete();
            $table->string('target_service')->default('cgai');
            $table->string('service_label')->nullable();
            $table->string('account_email');
            $table->string('account_password');
            $table->string('code_url');
            $table->unsignedInteger('seen_count')->default(1);
            $table->timestamp('first_seen_at')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();

            $table->unique(
                ['target_service', 'account_email', 'account_password', 'code_url'],
                'oxaam_credentials_unique_row'
            );
            $table->index('last_seen_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('oxaam_credentials');
    }
};
