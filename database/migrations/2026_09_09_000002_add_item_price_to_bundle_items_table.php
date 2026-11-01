<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What the member product itself cost when the bundle was priced.
 *
 * `unit_price` is the whole line -- the product plus its chosen variation and add-ons -- so it
 * cannot be compared against the product's current price to tell whether a bundle has gone stale.
 * Recomputing the line instead would mean re-reading variations and add-ons for every row of every
 * bundle on the page, which is the N+1 this column exists to avoid.
 *
 * Nullable: rows written before this existed have nothing to compare, and a bundle is reported as
 * current rather than falsely stale when the answer is simply unknown.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bundle_items', function (Blueprint $table) {
            $table->decimal('item_price', 24, 2)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('bundle_items', function (Blueprint $table) {
            $table->dropColumn('item_price');
        });
    }
};
