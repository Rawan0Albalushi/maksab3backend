<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Carries the cart's bundle grouping onto the placed order, plus the one figure the cart has no
 * use for: what the store actually gave away.
 *
 * The first three columns mirror carts exactly, because an order line is a cart line that has
 * been paid for and the grouping has to survive the transition. Order display folds the group
 * back into one line the same way the cart does.
 *
 * bogo_free_value is the new one. It is the worth of a free line at the price frozen when the
 * store joined -- not the live menu price, which may have moved since. orders.bogo_discount_amount
 * is the sum of these times their quantities, and the store's expense is booked off that column,
 * so a wrong value here corrupts the ledger rather than a screen. It is null on buy lines and on
 * ordinary lines; only a free line has a give-away value.
 *
 * Recomputing it must happen wherever the lines are rebuilt. The source stamped it at placement
 * only, so editing an order left the column holding the figure from placement while the bundles
 * under it grew or shrank -- and the expense was charged on the stale number the moment anyone
 * touched the order.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('order_details')) {
            return;
        }

        Schema::table('order_details', function (Blueprint $table) {
            if (! Schema::hasColumn('order_details', 'bogo_offer_id')) {
                $table->unsignedBigInteger('bogo_offer_id')->nullable();
            }

            if (! Schema::hasColumn('order_details', 'bogo_group_id')) {
                $table->string('bogo_group_id', 40)->nullable();
            }

            if (! Schema::hasColumn('order_details', 'is_free_item')) {
                $table->boolean('is_free_item')->default(0);
            }

            if (! Schema::hasColumn('order_details', 'bogo_free_value')) {
                $table->decimal('bogo_free_value', 24, 2)->nullable();
            }
        });

        Schema::table('order_details', function (Blueprint $table) {
            if (! $this->indexExists('order_details', 'order_details_bogo_group_id_index')) {
                $table->index('bogo_group_id', 'order_details_bogo_group_id_index');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('order_details')) {
            return;
        }

        Schema::table('order_details', function (Blueprint $table) {
            if ($this->indexExists('order_details', 'order_details_bogo_group_id_index')) {
                $table->dropIndex('order_details_bogo_group_id_index');
            }

            foreach (['bogo_offer_id', 'bogo_group_id', 'is_free_item', 'bogo_free_value'] as $column) {
                if (Schema::hasColumn('order_details', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }

    private function indexExists(string $table, string $name): bool
    {
        return DB::selectOne(
            'SELECT 1 FROM information_schema.statistics
             WHERE table_schema = ? AND table_name = ? AND index_name = ? LIMIT 1',
            [DB::getDatabaseName(), $table, $name]
        ) !== null;
    }
};
