<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('carts', function (Blueprint $table) {
            $table->unsignedBigInteger('bundle_id')->nullable()->index();
            $table->string('bundle_group_id', 40)->nullable()->index();
        });

        Schema::table('order_details', function (Blueprint $table) {
            $table->unsignedBigInteger('bundle_id')->nullable()->index();
            $table->string('bundle_group_id', 40)->nullable()->index();
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->decimal('bundle_discount_amount', 24, 2)->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('carts', function (Blueprint $table) {
            $table->dropColumn(['bundle_id', 'bundle_group_id']);
        });

        Schema::table('order_details', function (Blueprint $table) {
            $table->dropColumn(['bundle_id', 'bundle_group_id']);
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('bundle_discount_amount');
        });
    }
};
