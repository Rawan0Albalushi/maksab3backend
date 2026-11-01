<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bundle_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('bundle_id');
            $table->unsignedBigInteger('item_id');
            $table->unsignedInteger('quantity')->default(1);
            $table->string('item_name');
            $table->string('item_image')->nullable();
            $table->decimal('unit_price', 24, 2)->default(0);
            $table->json('variations')->nullable();
            $table->json('add_on_ids')->nullable();
            $table->json('add_on_qtys')->nullable();
            $table->timestamps();

            $table->index('bundle_id');
            $table->index('item_id');

            // No FK constraints -- see bogo_offer_items' own note (database/migrations) on why a
            // fresh install must not depend on migration run order. Both columns are already
            // indexed above.
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bundle_items');
    }
};
