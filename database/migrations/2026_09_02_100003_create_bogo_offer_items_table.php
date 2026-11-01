<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The frozen bundle: a copy of every item a store put into its enrolment.
 *
 * The parent is the *enrolment*, not the offer, because the bundle is per store.
 *
 * Everything here is a snapshot taken the day the store joined, and that is the point. price is
 * what the line costs inside the bundle -- zero for a get row, since the free item is free --
 * while original_price is what the item was worth when the bundle was built. Without the second
 * figure a free item could only be valued from the live menu, which puts the same item on screen
 * at two different prices the moment its menu entry is edited.
 *
 * Both prices are resolved from the item's BASE price plus its variation and add-on cost, never
 * from a discounted price. A bundle built on an already-discounted price would compound two
 * discounts and the store would carry both.
 *
 * item_id and service_id are both nullable and exactly one must be set: the Service module keeps
 * its own entity rather than a row in items, and it is the only other thing that can be bundled.
 * The CHECK enforces it in the schema -- MySQL 9 honours CHECK, so this is real -- and the model
 * repeats it on save for the benefit of servers that parse CHECK and ignore it.
 *
 * variations stores the chosen options as a label list, because that is all 6amMart has: food
 * variations carry no ids and no stock, so a selection can only be re-identified by group name
 * and option label. A rename therefore resolves to nothing and prices at zero *silently* -- both
 * Helpers::food_variation_price() and Helpers::variation_price() start their total at zero and
 * only accumulate on a match. The drift check that guards this is not defensive, it is the only
 * protection there is.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('bogo_offer_items')) {
            return;
        }

        Schema::create('bogo_offer_items', function (Blueprint $table) {
            $table->id();

            // No FK constraints on any of these three id columns. A fresh install must not depend
            // on migration run order to have created the referenced row/table first -- item_id and
            // bogo_offer_store_id are core-to-core and would likely be fine either way, but
            // service_id never can be: `services` only exists once the Service module's own
            // migrations run, a separate, independently-run batch (`module:migrate`, not
            // `migrate`) with no ordering guarantee relative to this core migration, and a hard FK
            // to a not-yet-created table fails the migration outright ("Failed to open the
            // referenced table"). The CHECK constraint below still enforces the item_id/service_id
            // one-target business rule regardless of there being no FK. bundle_items' own
            // service_id (2026_09_08_100001_add_service_target_to_bundle_items_table.php) already
            // made this same call for the identical reason.
            $table->foreignId('bogo_offer_store_id');

            $table->foreignId('item_id')->nullable();
            $table->index('item_id');
            $table->unsignedBigInteger('service_id')->nullable();
            $table->index('service_id');

            // 'buy' or 'get'.
            $table->string('type', 3);
            $table->unsignedInteger('quantity')->default(1);

            // Kept so the bundle still renders after the item is renamed or deleted.
            $table->string('item_name');
            $table->string('item_image')->nullable();

            $table->decimal('price', 24, 2)->default(0);
            $table->decimal('original_price', 24, 2)->default(0);

            $table->json('variations')->nullable();
            $table->json('add_on_ids')->nullable();
            $table->json('add_on_qtys')->nullable();

            $table->timestamps();

            // Reading a bundle always means "the buy side and the get side of this enrolment".
            $table->index(['bogo_offer_store_id', 'type']);
        });

        // Exactly one of the two targets, expressed as XOR. Written raw because the schema
        // builder has no portable CHECK helper.
        DB::statement(
            'ALTER TABLE `bogo_offer_items`
             ADD CONSTRAINT `bogo_offer_items_one_target`
             CHECK ((`item_id` IS NOT NULL) <> (`service_id` IS NOT NULL))'
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('bogo_offer_items');
    }
};
