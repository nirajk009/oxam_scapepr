<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('oxaam_sessions', function (Blueprint $table) {
            $table->id();
            $table->string('registration_name');
            $table->string('registration_email')->unique();
            $table->string('registration_phone', 20);
            $table->string('registration_password');
            $table->string('cookie_name')->default('PHPSESSID');
            $table->string('cookie_value')->nullable();
            $table->json('cookies')->nullable();
            $table->unsignedInteger('uses_count')->default(0);
            $table->unsignedInteger('max_uses')->default(300);
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_registered_at')->nullable();
            $table->timestamp('last_validated_at')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();

            $table->index(['is_active', 'uses_count']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('oxaam_sessions');
    }
};
