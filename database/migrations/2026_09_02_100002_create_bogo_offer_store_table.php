<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A store's enrolment in a BOGO offer -- the negotiation between admin and vendor.
 *
 * Renamed from the source's bogo_offer_restaurant. It is a pivot only in shape: it carries the
 * whole approval conversation, and bogo_offer_items hangs off *this* row rather than off the
 * offer, because the bundle is per store.
 *
 * requested_by records who opened the conversation, and it changes what the row means. An admin
 * invitation arrives as pending and waits for the vendor to accept or deny; a vendor request
 * arrives as pending and waits for the admin. Same status, opposite audiences -- which is why the
 * notification rows seeded later distinguish the two.
 *
 * status is a varchar rather than an enum to match the house schema (orders.order_status is a
 * varchar too) and so that adding a state later is not an ALTER on a table with live rows.
 *
 * rejection_reason is bounded at 255 on purpose. The source left it an unbounded text column and
 * the vendor side had no length rule, so a paste could fill it; the bound belongs in the schema
 * as well as the request class.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('bogo_offer_store')) {
            return;
        }

        Schema::create('bogo_offer_store', function (Blueprint $table) {
            $table->id();

            // No FK constraint: kept plain like every other cross-table id in this migration set,
            // so a fresh install never depends on migration run order to create this row before
            // that one. The unique(bogo_offer_id, store_id) and index(store_id, status) below
            // already cover lookups on both columns via their leftmost-prefix.
            $table->foreignId('bogo_offer_id');
            $table->foreignId('store_id');

            $table->string('status', 20)->default('pending');
            $table->string('rejection_reason', 255)->nullable();

            // 'admin' or 'vendor' -- see the note above.
            $table->string('requested_by', 10)->default('vendor');

            // Which SIDE said no, not which user: 'admin' or 'store', null until one does.
            // requested_by cannot answer it, because a resubmit flips the direction the row was
            // raised from and the original refusal would be attributed to the wrong side.
            $table->string('rejected_by', 10)->nullable();

            // Whether the other side has seen this row yet; drives the unread badge.
            $table->boolean('checked')->default(0);
            $table->timestamp('joined_at')->nullable();

            // The frozen total of the buy side, kept so a listing does not have to sum the items.
            $table->decimal('bundle_price', 24, 2)->nullable();

            // Hash of the chosen (item, variation, add-on, quantity) tuples. Two stores may join
            // the same offer with different bundles, but one store may not join twice with the
            // same one, and comparing signatures is cheaper than comparing item sets.
            $table->string('combination_signature', 64)->nullable();

            $table->timestamps();

            // A store joins an offer once. This is the constraint the double-click race relies on.
            $table->unique(['bogo_offer_id', 'store_id']);

            // "What has this store joined, and what is still waiting on someone" -- the two
            // queries both panels open with.
            $table->index(['store_id', 'status']);
            $table->index('combination_signature');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bogo_offer_store');
    }
};
