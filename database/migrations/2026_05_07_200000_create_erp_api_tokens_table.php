<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('erp_api_tokens', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('api_key', 64)->unique();
            $table->string('api_secret', 64);
            $table->string('webhook_url', 500)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('webhook_last_dispatched_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('erp_api_tokens');
    }
};
