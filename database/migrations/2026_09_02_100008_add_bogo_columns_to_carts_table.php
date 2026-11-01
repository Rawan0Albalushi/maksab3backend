<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Makes a cart row able to say it is part of a BOGO bundle.
 *
 * A bundle in the cart is not one row. It is N rows -- the buy lines and the free lines -- sharing
 * a bogo_group_id, folded back into a single entry only for display. That shape is what lets the
 * existing per-line stock check and decrement cover every member without new code, and it is why
 * bogo_group_id is the id that matters here.
 *
 * Three ids are involved across this feature and none of them are interchangeable:
 *   bogo_offer_id  -- the offer, shared by every store that joined it
 *   bogo_group_id  -- one bundle instance in one cart, and the key everything groups by
 *   the enrolment id (bogo_offer_store.id) -- this store's terms for that offer
 * Two of the same bundle in one cart are two group ids against one offer id, so anything that
 * groups by offer id silently merges them. The source shipped that bug once.
 *
 * is_free_item marks the get lines. They are priced at zero, and the value the store gave away is
 * recorded on the order detail rather than here -- a cart has no expense yet.
 *
 * All three are nullable: an ordinary line carries none of them, and that is how a bundle row is
 * told apart from a plain row of the same item.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('carts')) {
            return;
        }

        Schema::table('carts', function (Blueprint $table) {
            if (! Schema::hasColumn('carts', 'bogo_offer_id')) {
                $table->unsignedBigInteger('bogo_offer_id')->nullable();
            }

            if (! Schema::hasColumn('carts', 'bogo_group_id')) {
                $table->string('bogo_group_id', 40)->nullable();
            }

            if (! Schema::hasColumn('carts', 'is_free_item')) {
                $table->boolean('is_free_item')->default(0);
            }
        });

        Schema::table('carts', function (Blueprint $table) {
            foreach (['carts_bogo_group_id_index' => 'bogo_group_id', 'carts_bogo_offer_id_index' => 'bogo_offer_id'] as $name => $column) {
                if (! $this->indexExists('carts', $name)) {
                    $table->index($column, $name);
                }
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('carts')) {
            return;
        }

        Schema::table('carts', function (Blueprint $table) {
            foreach (['carts_bogo_group_id_index', 'carts_bogo_offer_id_index'] as $name) {
                if ($this->indexExists('carts', $name)) {
                    $table->dropIndex($name);
                }
            }

            foreach (['bogo_offer_id', 'bogo_group_id', 'is_free_item'] as $column) {
                if (Schema::hasColumn('carts', $column)) {
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
