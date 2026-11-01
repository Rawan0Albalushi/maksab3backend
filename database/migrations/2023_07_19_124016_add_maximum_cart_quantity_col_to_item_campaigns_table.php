<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('item_campaigns', function (Blueprint $table) {
            $table->integer('maximum_cart_quantity')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('item_campaigns', function (Blueprint $table) {
            $table->dropColumn('maximum_cart_quantity');
        });
    }
};
