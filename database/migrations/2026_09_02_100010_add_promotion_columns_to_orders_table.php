<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The two order-level promotion figures, one per feature.
 *
 * happy_hour_id keeps the discount attributable to its happy hour after the window has closed and
 * the offer may have been edited or deleted. It is stamped only when a store-wide amount was
 * actually applied -- an order placed during a window that discounted nothing does not get one,
 * because the expense booked off the back of it would then be for a discount that never happened.
 *
 * bogo_discount_amount is the sum over the order's free lines of bogo_free_value x quantity. Both
 * columns are read by the accounting path, and the two features do not share a bearer rule:
 * happy hour and BOGO are carried 100% by the vendor, with their own expense types and no admin
 * counterpart, while an ordinary item discount is split with the admin on commission stores. That
 * is a deliberate asymmetry -- the vendor agreed to the whole cost when it joined the offer, and
 * commission is charged on what the goods were worth before the promotion took its cut, so
 * charging commission on the discounted total would quietly make the admin a co-sponsor.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('orders')) {
            return;
        }

        Schema::table('orders', function (Blueprint $table) {
            if (! Schema::hasColumn('orders', 'happy_hour_id')) {
                $table->unsignedBigInteger('happy_hour_id')->nullable();
            }

            if (! Schema::hasColumn('orders', 'bogo_discount_amount')) {
                $table->decimal('bogo_discount_amount', 24, 2)->default(0);
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('orders')) {
            return;
        }

        Schema::table('orders', function (Blueprint $table) {
            foreach (['happy_hour_id', 'bogo_discount_amount'] as $column) {
                if (Schema::hasColumn('orders', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
