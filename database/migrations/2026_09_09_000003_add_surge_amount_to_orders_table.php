<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Freeze the surge component of the delivery charge onto the order.
 *
 * getDeliveryCharge() already returns surge_amount at quote time, but nothing wrote it down --
 * it folds into delivery_charge before the order is saved, so a surged order and a plain one
 * looked identical to every report, export and refund calculation once placed. The total charged
 * was always correct; only which part of it was surge became unrecoverable.
 *
 * Nullable-by-default is not enough here: a pre-migration order has no surge figure at all, and
 * 0.00 is the right read for "unknown" the same way it is for "no surge ran" -- neither case
 * should print a value the order never recorded.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->decimal('surge_amount', 24, 2)->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn('surge_amount');
        });
    }
};
