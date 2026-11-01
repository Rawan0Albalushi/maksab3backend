<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Which delivery vehicle categories may take an EXPRESS order.
 *
 * From the design: "By this, when customer select Express Delivery the order will only show to
 * the selected vehicle's deliveryman." So this is a dispatch FILTER, not a price input — it never
 * reaches the fee engine. It is marked Optional on the screen, and empty means no filter: every
 * vehicle category may take the order, which is what happens today.
 *
 * A pivot rather than a column because the choice is a set, and because the categories are rows
 * in `d_m_vehicles` that an admin can add to — a JSON blob or a bitmask would silently rot the
 * day somebody renames or deletes one.
 *
 * Slightly-delay has no equivalent: the design offers the filter under Express only.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('additional_delivery_charge_vehicle', function (Blueprint $table) {
            $table->id();
            $table->foreignId('additional_delivery_charge_id');
            // d_m_vehicles.id — DMVehicle's table name is Laravel's default for that class name.
            $table->foreignId('d_m_vehicle_id');
            $table->timestamps();

            $table->unique(['additional_delivery_charge_id', 'd_m_vehicle_id'], 'adcv_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('additional_delivery_charge_vehicle');
    }
};
