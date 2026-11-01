<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ai_conversations', function (Blueprint $table) {
            $table->index(['user_id', 'status', 'module_id', 'zone_id', 'updated_at'], 'ai_conv_user_resume_idx');
            $table->index(['guest_id', 'status', 'module_id', 'zone_id', 'updated_at'], 'ai_conv_guest_resume_idx');
        });
    }

    public function down(): void
    {
        Schema::table('ai_conversations', function (Blueprint $table) {
            $table->dropIndex('ai_conv_user_resume_idx');
            $table->dropIndex('ai_conv_guest_resume_idx');
        });
    }
};
