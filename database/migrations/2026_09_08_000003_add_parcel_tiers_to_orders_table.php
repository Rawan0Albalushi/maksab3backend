<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What a parcel order was charged by, beside the category it already records.
 *
 * The fee has three additive tiers — category, weight band, size class (parcel brief §1,
 * model (a)) — and until now only the first was written down. An order whose delivery charge
 * included 20 for its weight band could not say WHICH band, so order details, the invoice and
 * every support conversation about a parcel fee had a gap in the middle of the arithmetic.
 *
 * Ids rather than a denormalised snapshot of the name and the amount, matching how
 * `parcel_category_id` already works. A band's charge is not a property of the band anyway — it
 * lives on the delivery rule for that (zone, module) — so freezing a number here would create a
 * second source of truth for a figure `orders.delivery_charge` already holds.
 *
 * Nullable on every row and never backfilled. Orders placed before this ran genuinely had no
 * selection: the tiers were stored by the delivery-rule wizard but nothing on an order could
 * choose one. Guessing a band for them would invent history.
 *
 * No foreign key, for the reason the parcel category column has none: `weights` and `dimensions`
 * are setup rows an admin deletes, and a delete that fails because a two-year-old order points at
 * the row is worse than an order pointing at nothing. The display side already handles a null
 * relation, because a category can go missing the same way.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (! Schema::hasColumn('orders', 'weight_id')) {
                $table->unsignedBigInteger('weight_id')->nullable();
            }

            if (! Schema::hasColumn('orders', 'dimension_id')) {
                $table->unsignedBigInteger('dimension_id')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $columns = array_values(array_filter(
                ['weight_id', 'dimension_id'],
                fn ($column) => Schema::hasColumn('orders', $column)
            ));

            if ($columns) {
                $table->dropColumn($columns);
            }
        });
    }
};
