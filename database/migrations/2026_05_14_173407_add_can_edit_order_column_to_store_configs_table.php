<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('store_configs', function (Blueprint $table) {
            $table->boolean('can_edit_order')->default(false);
        });
    }

    public function down(): void
    {
        Schema::table('store_configs', function (Blueprint $table) {
            $table->dropColumn('can_edit_order');
        });
    }
};
