<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The BOGO offer shell: what the admin creates, before any store is involved.
 *
 * An offer carries the *rules* -- how many to buy, how many come free, when it runs and how often
 * it may be used -- and deliberately not the items. Which foods make up the bundle is decided per
 * store at enrolment and frozen into bogo_offer_items, because two stores joining the same offer
 * will price and staff it differently. That split is why the admin create form has no item picker.
 *
 * module_id is required and has no equivalent in the StackFood original, which knew only one
 * vertical. A zone here hosts several modules, so an offer that did not name one would let a
 * grocery promotion discount a food order in the same zone.
 *
 * order_types is limited to delivery and take_away. The source also allowed dine_in, but 6amMart
 * has no dine-in concept at all -- PlaceOrderRequest validates in:take_away,delivery,parcel -- and
 * parcel carries no items to bundle. Null means "no order-type restriction", which is a legal
 * state, so the column stays nullable rather than defaulting to every type.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('bogo_offers')) {
            return;
        }

        Schema::create('bogo_offers', function (Blueprint $table) {
            $table->id();

            $table->foreignId('module_id');

            // The default-language copy. Other locales live in the polymorphic translations
            // table, the way items and campaigns already do.
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('image')->nullable();

            // Buy N, get M. Enrolment validates that a store's chosen items total exactly these.
            $table->unsignedInteger('buy_qty');
            $table->unsignedInteger('get_qty');

            $table->dateTime('start_date');
            $table->dateTime('end_date');

            // Null means uncapped, on both axes. total_uses is the running counter behind the
            // whole-offer cap; the per-customer cap is answered from bogo_offer_usages instead,
            // because it is a different question asked of the same ledger.
            $table->unsignedInteger('usage_limit_total')->nullable();
            $table->unsignedInteger('usage_limit_per_customer')->nullable();
            $table->unsignedInteger('total_uses')->default(0);

            $table->json('order_types')->nullable();

            $table->boolean('status')->default(1);
            $table->string('slug')->nullable();
            $table->unsignedBigInteger('admin_id')->nullable();

            $table->timestamps();

            // The listing query is always "live offers for this module", so the two columns that
            // answer it are indexed together.
            $table->index(['module_id', 'status']);
            $table->index(['start_date', 'end_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bogo_offers');
    }
};
