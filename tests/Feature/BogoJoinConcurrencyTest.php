<?php

namespace Tests\Feature;

use App\Models\BogoOffer;
use App\Models\BogoOfferStore;
use App\Models\Item;
use App\Models\Store;
use App\Models\Vendor;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * TC_160 — two genuinely concurrent joins for the same offer/store race between
 * BogoOfferController::join()'s exists() pre-check and writeEnrollment()'s insert. Both can pass
 * the check before either commits; the loser used to hit the table's own
 * (bogo_offer_id, store_id) unique constraint with no catch around it, surfacing as an unhandled
 * 500 instead of the same "you have already joined this offer" error the pre-check already knows
 * how to give.
 *
 * A real second HTTP request can't be made to land inside that window on demand, so the race is
 * reproduced deterministically: a BogoOfferStore::creating() listener inserts the "other request"'s
 * row directly, on a genuinely separate database connection, at the exact moment this request's
 * own row is about to be inserted -- after the exists() check already ran clean.
 *
 * Deliberately not DatabaseTransactions: that trait wraps the whole test in one open transaction,
 * and the FK from bogo_offer_store to this test's own (then-uncommitted) offer row would make the
 * separate connection's insert block on a real lock wait rather than racing. Fixtures are created
 * and torn down for real instead, same as a genuine request would see them.
 */
class BogoJoinConcurrencyTest extends TestCase
{
    public function test_the_losing_side_of_a_concurrent_join_gets_a_graceful_error_not_a_500(): void
    {
        $store = Store::withoutGlobalScopes()
            ->whereHas('module', fn ($q) => $q->whereIn('module_type', ['grocery', 'food', 'pharmacy', 'ecommerce']))
            ->whereNotNull('vendor_id')
            ->first();

        if (! $store) {
            $this->markTestSkipped('dataset has no store in a promotion-capable module');
        }

        $vendor = Vendor::find($store->vendor_id);

        if (! $vendor) {
            $this->markTestSkipped('the fixture store has no vendor');
        }

        $item = Item::withoutGlobalScopes()->where('store_id', $store->id)->first();

        if (! $item) {
            $this->markTestSkipped('the fixture store has no item to join with');
        }

        $offer = BogoOffer::create([
            'module_id' => $store->module_id,
            'title' => 'concurrency probe',
            'buy_qty' => 1,
            'get_qty' => 1,
            'start_date' => now()->subDay(),
            'end_date' => now()->addWeek(),
            'status' => 1,
        ]);

        // A second, genuinely separate connection to the same database -- its writes commit for
        // real and are visible to (and lock-checked by) the request's own connection, the way two
        // independent HTTP requests' connections actually would be.
        config(['database.connections.race_probe' => config('database.connections.mysql')]);
        DB::purge('race_probe');

        BogoOfferStore::creating(function (BogoOfferStore $model) use ($offer, $store) {
            if ((int) $model->bogo_offer_id !== $offer->id || (int) $model->store_id !== $store->id) {
                return;
            }

            DB::connection('race_probe')->table('bogo_offer_store')->insert([
                'bogo_offer_id' => $offer->id,
                'store_id' => $store->id,
                'status' => 'pending',
                'requested_by' => 'store',
                'checked' => 0,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        });

        try {
            $response = $this->actingAs($vendor, 'vendor')
                ->withSession(['login_remember_token' => $vendor->login_remember_token])
                ->postJson(route('vendor.bogo-offer.join', $offer->id), [
                    'buy_items' => [['item_id' => $item->id, 'quantity' => 1]],
                    'get_items' => [['item_id' => $item->id, 'quantity' => 1]],
                ]);

            $response->assertStatus(403);
            $this->assertSame('store', $response->json('errors.0.code'));

            $this->assertSame(
                1,
                BogoOfferStore::where('bogo_offer_id', $offer->id)->where('store_id', $store->id)->count(),
                'exactly one row must survive the race -- the loser must not leave a partial write behind'
            );
        } finally {
            BogoOfferStore::flushEventListeners();
            // Cascade-deletes any surviving bogo_offer_store row (the migration's
            // cascadeOnDelete()), regardless of which connection wrote it.
            $offer->delete();
            DB::purge('race_probe');
        }
    }
}
