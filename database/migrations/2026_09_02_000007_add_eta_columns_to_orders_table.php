<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What an order needs to carry its own ETA — port doc §11.1 and §11.4.
 *
 * `delivery_duration` is the map API's travel time, in **SECONDS**, exactly as it arrives. It is
 * converted to minutes once, at the edge, before any arithmetic — storing it converted would lose
 * the precision the API gave and invite a second, different rounding somewhere else.
 *
 * `eta_snapshot` freezes the inputs an order was quoted against, so an admin editing the zone's
 * configuration later cannot retroactively change what a live order was promised (§11.4). Orders
 * placed before this shipped have no snapshot and keep reading the live configuration — both cases
 * have to be handled, so the column is nullable rather than backfilled with a guess.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            if (! Schema::hasColumn('orders', 'delivery_duration')) {
                $table->unsignedInteger('delivery_duration')->nullable()->comment('seconds, from the map API');
            }

            if (! Schema::hasColumn('orders', 'eta_snapshot')) {
                $table->json('eta_snapshot')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['delivery_duration', 'eta_snapshot']);
        });
    }
};
