<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One row per order that consumed a BOGO offer -- the ledger both usage caps are answered from.
 *
 * The two caps are separate questions asked of the same rows: usage_limit_total is a count across
 * everybody, and usage_limit_per_customer is a count for one person. bogo_offers.total_uses keeps
 * a running answer to the first so a listing does not have to aggregate; the second has to be
 * asked here, because there is nowhere to cache a per-customer figure.
 *
 * Guests are counted by phone, not by any client-supplied identifier. A guest has no user_id, and
 * anything the client can set is something the client can change to reset its own allowance.
 *
 * quantity is bundles, not items: an order containing two of the same bundle spends two of the
 * allowance. Caps count bundles per offer -- never per cart group, which is a different id and a
 * different question.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('bogo_offer_usages')) {
            return;
        }

        Schema::create('bogo_offer_usages', function (Blueprint $table) {
            $table->id();

            // No FK constraints -- see bogo_offer_items' own note on why a fresh install must not
            // depend on migration run order. Both columns are already indexed below.
            $table->foreignId('bogo_offer_id');
            $table->foreignId('order_id');

            $table->unsignedBigInteger('user_id')->nullable();
            $table->boolean('is_guest')->default(0);
            $table->string('phone', 30)->nullable();

            $table->unsignedInteger('quantity')->default(1);

            $table->timestamps();

            // The per-customer cap, asked once for a signed-in customer and once for a guest.
            $table->index(['bogo_offer_id', 'user_id']);
            $table->index(['bogo_offer_id', 'phone']);

            // Release on cancel needs to find an order's rows directly.
            $table->index('order_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bogo_offer_usages');
    }
};
