<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('store_notification_settings', function (Blueprint $table) {
            $table->string('module_type',20)->default('all');
        });
    }

    public function down(): void
    {
        Schema::table('store_notification_settings', function (Blueprint $table) {
            $table->dropColumn('module_type');
        });
    }
};
