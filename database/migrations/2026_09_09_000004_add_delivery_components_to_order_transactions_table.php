<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Mirror the order's delivery-fee components onto the transaction row.
 *
 * order_transactions.delivery_charge is copied straight from orders.delivery_charge, which is
 * the BASE plus any surge -- never the express/slightly-delay premium, which orders keeps in its
 * own delivery_type_charge column that order_transactions has no equivalent of. A transaction
 * report reading delivery_charge alone therefore understates what an express order actually
 * collected for delivery, and cannot separate surge from base either.
 *
 * Both are mirrored here rather than derived later: the order's own columns can still change
 * historically-inconvenient ways (a refund, say), so the transaction keeps its own frozen copy
 * of what was true the moment it was created, the same way delivery_charge itself already does.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_transactions', function (Blueprint $table) {
            $table->decimal('delivery_type_charge', 24, 2)->default(0);
            $table->decimal('surge_amount', 24, 2)->default(0);
        });
    }

    public function down(): void
    {
        Schema::table('order_transactions', function (Blueprint $table) {
            $table->dropColumn(['delivery_type_charge', 'surge_amount']);
        });
    }
};
