<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bundles', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('store_id');
            $table->unsignedBigInteger('module_id');
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('image')->nullable();
            $table->string('slug')->nullable();
            $table->dateTime('start_date');
            $table->dateTime('end_date');
            $table->decimal('base_price', 24, 2)->default(0);
            $table->decimal('discount_percentage', 5, 2)->default(0);
            $table->decimal('discounted_price', 24, 2)->default(0);
            $table->boolean('status')->default(1);
            $table->string('created_by', 10)->default('admin');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['store_id', 'status']);
            $table->index(['module_id', 'status']);
            $table->index(['start_date', 'end_date']);

            // No FK constraints -- see bogo_offer_items' own note (database/migrations) on why a
            // fresh install must not depend on migration run order. Both columns are already
            // indexed above.
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bundles');
    }
};
