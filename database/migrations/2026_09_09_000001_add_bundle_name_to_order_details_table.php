<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Freeze the bundle's name onto the order line.
 *
 * A bundle's MEMBER names are already frozen -- `bundle_items.item_name` is written when the
 * bundle is saved, so renaming a product never rewrites what a past order says it contained.
 * The bundle's OWN name was the one thing still read live, off `bundles.name`, which meant
 * renaming a bundle retroactively changed the title on every order that had ever bought it.
 *
 * Nullable because every order placed before this column existed has no name to backfill: those
 * rows keep falling back to the live name, which is exactly what they did before.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_details', function (Blueprint $table) {
            $table->string('bundle_name', 80)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('order_details', function (Blueprint $table) {
            $table->dropColumn('bundle_name');
        });
    }
};
