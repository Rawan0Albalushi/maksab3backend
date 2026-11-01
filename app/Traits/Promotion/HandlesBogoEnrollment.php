<?php

namespace App\Traits\Promotion;

use App\CentralLogics\Helpers;
use App\Models\AddOn;
use App\Models\BogoOffer;
use App\Models\BogoOfferItem;
use App\Models\BogoOfferStore;
use App\Models\Item;
use App\Models\Module;
use App\Models\Store;
use App\Scopes\StoreScope;
use App\Scopes\ZoneScope;
use App\Support\Storage\FileStorage;
use Illuminate\Support\Arr;

/**
 * Joining a BOGO offer: validating a store's chosen bundle, freezing it, and keeping two stores
 * from enrolling the same combination twice.
 *
 * This is the half of the feature the vendor panel drives, and the half with money in it -- the
 * prices frozen here are what the customer is billed and what the store's give-away is booked
 * against, for as long as the enrolment lives.
 */
trait HandlesBogoEnrollment
{

    // Composed rather than left to the consumer. The checks below call into both: the visibility
    // the vendor guards need enrollmentState() and its siblings. A class using this trait alone
    // otherwise fatals on the first drawer it opens.
    use HandlesPromotionEnrollment;
    use ProvidesStoreItemPicker;

    /**
     * Why this selection cannot be enrolled, as a list of coded errors.
     *
     * The quantity rule is EQUALITY, not a minimum: buy quantities must total the offer's
     * buy_qty and get quantities its get_qty. An offer is a shape, and a bundle that does not
     * fill it is a different offer.
     */
    protected function validateEnrollmentItems(BogoOffer $offer, array $buyItems, array $getItems, int $storeId): array
    {
        $errors = [];

        $buyTotal = collect($buyItems)->sum(fn ($item) => (int) ($item['quantity'] ?? 0));
        $getTotal = collect($getItems)->sum(fn ($item) => (int) ($item['quantity'] ?? 0));

        if ($buyTotal !== (int) $offer->buy_qty) {
            $errors[] = ['code' => 'buy_items', 'message' => translate('messages.You must add buy items matching the offer quantity')];
        }

        if ($getTotal !== (int) $offer->get_qty) {
            $errors[] = ['code' => 'get_items', 'message' => translate('messages.you_must_add_get_items_matching_the_offer_quantity')];
        }

        foreach ([$buyItems, $getItems] as $side) {
            // Each line is one selection and the same item may appear on several lines with
            // different variations, so the item's cart cap applies to the SIDE'S TOTAL for that
            // item, not to one line.
            $quantityByItem = [];

            foreach ($side as $line) {
                $item = Item::withoutGlobalScope(StoreScope::class)
                    ->withoutGlobalScope(ZoneScope::class)
                    ->find($line['item_id'] ?? null);

                if (! $item || (int) $item->store_id !== $storeId) {
                    $errors[] = ['code' => 'item', 'message' => translate('messages.Selected item does not belong to this store')];

                    continue;
                }

                $quantity = (int) ($line['quantity'] ?? 0);

                if ($quantity < 1) {
                    $errors[] = ['code' => 'item', 'message' => translate('messages.quantity_must_be_at_least_one')];

                    continue;
                }

                $quantityByItem[$item->id] = ($quantityByItem[$item->id] ?? 0) + $quantity;

                if ($item->maximum_cart_quantity && $quantityByItem[$item->id] > $item->maximum_cart_quantity) {
                    $errors[] = [
                        'code' => 'cart_item_limit',
                        'message' => $item->getRawOriginal('name').' : '.translate('messages.Maximum cart quantity exceeded'),
                    ];
                }
            }
        }

        return array_values(array_unique($errors, SORT_REGULAR));
    }

    /**
     * A hash of the whole member set, used to stop one store enrolling the same combination
     * twice under a different shape.
     *
     * Identical lines are folded together before hashing, so the same bundle hashes the same
     * however the client chose to split it: two lines of one item and one line of two are the same
     * thing to a customer and must not enrol twice under different shapes.
     */
    protected function buildCombinationSignature(array $buyItems, array $getItems): string
    {
        $normalise = function (array $items, string $type) {
            return collect($items)
                ->map(fn ($item) => [
                    'type' => $type,
                    'item_id' => (int) ($item['item_id'] ?? 0),
                    'quantity' => (int) ($item['quantity'] ?? 1),
                    'variations' => $this->canonicalise($item['variations'] ?? null),
                    'add_ons' => $this->canonicalise($item['add_on_ids'] ?? null),
                ])
                ->groupBy(fn ($item) => json_encode(Arr::except($item, 'quantity')))
                ->map(fn ($group) => array_merge($group->first(), ['quantity' => $group->sum('quantity')]))
                ->map(fn ($item) => json_encode($item))
                ->sort()->values()->all();
        };

        return hash('sha256', json_encode(array_merge(
            $normalise($buyItems, 'buy'),
            $normalise($getItems, 'get')
        )));
    }

    /** Sorts recursively so key or selection order cannot hash the same combination differently. */
    protected function canonicalise($value)
    {
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            $value = json_last_error() === JSON_ERROR_NONE ? $decoded : $value;
        }

        if (! is_array($value)) {
            return $value;
        }

        $value = array_map(fn ($item) => $this->canonicalise($item), $value);

        if (array_is_list($value)) {
            sort($value);
        } else {
            ksort($value);
        }

        return $value;
    }

    /**
     * Scoped to offers that have not ended, so an expired offer's combination becomes reusable
     * rather than being burned forever.
     */
    protected function combinationTaken(int $storeId, string $signature, ?int $ignoreEnrollmentId = null): bool
    {
        return BogoOfferStore::where('store_id', $storeId)
            ->where('combination_signature', $signature)
            ->when($ignoreEnrollmentId, fn ($q) => $q->where('id', '!=', $ignoreEnrollmentId))
            ->whereHas('bogoOffer', fn ($q) => $q->where(function ($q) {
                $q->whereNull('end_date')->orWhere('end_date', '>=', now());
            }))
            ->exists();
    }

    /**
     * Freezes a copy of every selected item so later edits to the menu cannot move the offer,
     * and returns the bundle price.
     *
     * Buy lines carry their variation and add-on cost; get lines are fully free, add-ons
     * included. Both record what the item was WORTH regardless of side -- price is what the line
     * costs inside the bundle, original_price is what it was worth the day the bundle was built.
     * Without the second figure a free item could only be valued from the live menu, which puts
     * the same item on screen at two different prices the moment its menu entry is edited.
     */
    protected function syncEnrollmentItems(BogoOfferStore $enrollment, array $buyItems, array $getItems): float
    {
        $enrollment->items()->delete();

        $bundlePrice = 0.0;

        foreach ([['buy', $buyItems], ['get', $getItems]] as [$type, $lines]) {
            foreach ($lines as $line) {
                $item = Item::withoutGlobalScope(StoreScope::class)
                    ->withoutGlobalScope(ZoneScope::class)
                    ->find($line['item_id'] ?? null);

                if (! $item) {
                    continue;
                }

                $variations = Helpers::decodeJsonToArray($line['variations'] ?? null);
                $addOnIds = Helpers::decodeJsonToArray($line['add_on_ids'] ?? null);
                $addOnQtys = Helpers::decodeJsonToArray($line['add_on_qtys'] ?? null);

                $worth = $this->unitPrice($item, $variations, $addOnIds, $addOnQtys);
                $unitPrice = $type === 'buy' ? $worth : 0.0;

                // A physical copy, not just the filename -- the item's own image file gets
                // deleted out from under us the next time the store replaces it (FileStorage::
                // update() deletes-then-uploads), which would leave this frozen line pointing at
                // a 404. See DescribesFrozenItemLine::sourceImageDisk() for how the copy's disk
                // is looked back up.
                $frozenImage = FileStorage::copyStorageFile('product/', $item->image, FileStorage::getStorageDiskByKey($item, 'image', 'public'));

                $bogoItem = BogoOfferItem::create([
                    'bogo_offer_store_id' => $enrollment->id,
                    'item_id' => $item->id,
                    'type' => $type,
                    'quantity' => (int) ($line['quantity'] ?? 1),
                    // The default-language name, not the caller's current locale -- this copy has
                    // to outlive edits to and deletion of the item.
                    'item_name' => $item->getRawOriginal('name'),
                    'item_image' => $frozenImage ?? $item->image,
                    'price' => $unitPrice,
                    'original_price' => $worth,
                    'variations' => $variations,
                    'add_on_ids' => $addOnIds,
                    'add_on_qtys' => $addOnQtys,
                ]);

                if ($frozenImage) {
                    FileStorage::updateStorageTable(BogoOfferItem::class, $bogoItem->id, $frozenImage);
                }

                $bundlePrice += $unitPrice * (int) ($line['quantity'] ?? 1);
            }
        }

        $bundlePrice = round($bundlePrice, config('round_up_to_digit', 2));

        $enrollment->update(['bundle_price' => $bundlePrice]);

        return $bundlePrice;
    }

    /**
     * Why a customer cannot see this bundle right now, as a list of sentences.
     *
     * "Approved" is the admin's answer to the store, not the customer's view of the bundle: a
     * sold-out item, a switched-off item, a closed store or a filled usage cap all hide it while
     * they last. Without this the vendor sees "Approved" and has to work out from the app why
     * nothing is selling.
     *
     * Returns an empty array when the bundle is visible.
     */
    public function customerVisibilityProblems(BogoOfferStore $enrollment, ?BogoOffer $offer = null): array
    {
        $offer ??= $enrollment->bogoOffer;
        $problems = [];

        if (! $offer || ! $offer->status) {
            $problems[] = translate('messages.This_offer_is_no_longer_available');

            return $problems;
        }

        if ($offer->start_date && $offer->start_date->greaterThan(now())) {
            $problems[] = translate('messages.This offer has not started yet');
        }

        if ($offer->end_date && $offer->end_date->lessThan(now())) {
            $problems[] = translate('This offer has already ended');
        }

        if ($enrollment->status !== BogoOfferStore::STATUS_APPROVED) {
            $problems[] = translate('messages.This_store_is_no_longer_part_of_the_offer');
        }

        if (! $enrollment->store || ! $enrollment->store->status) {
            $problems[] = translate('messages.This_store_is_currently_unavailable');
        }

        if ($offer->usage_limit_total !== null && $offer->total_uses >= $offer->usage_limit_total) {
            $problems[] = translate('messages.This_offer_has_reached_its_usage_limit');
        }

        if ($reason = $this->linesUnavailableReason(
            $enrollment->items, 1, 'messages.Is no longer available'
        )) {
            $problems[] = $reason;
        }

        return $problems;
    }

    /**
     * The live state a vendor's row shows, alongside the reasons.
     *
     * The source pushed this onto the list row as well as the drawer, because the row said only
     * "Approved" and the vendor had to open every offer in turn to find the one that was not
     * running.
     */
    public function bogoCustomerVisibility(?BogoOfferStore $enrollment, ?BogoOffer $offer = null): array
    {
        $offer ??= $enrollment?->bogoOffer;

        // Nothing to be visible yet: the enrolment state is the whole story, and a live-state
        // badge beside a request nobody has answered would be answering a question not yet asked.
        if (! $enrollment || $enrollment->status !== BogoOfferStore::STATUS_APPROVED) {
            return $this->promotionVisibility('not_applicable');
        }

        if ($this->bogoOfferHasEnded($offer)) {
            return $this->promotionVisibility('ended', [translate('This offer has already ended')]);
        }

        if ($offer && $offer->start_date && $offer->start_date->greaterThan(now())) {
            return $this->promotionVisibility('scheduled', [translate('messages.This offer has not started yet')]);
        }

        $problems = $this->customerVisibilityProblems($enrollment, $offer);

        return $problems
            ? $this->promotionVisibility('not_visible', $problems)
            : $this->promotionVisibility('running');
    }

    /**
     * The same question asked of a whole page of enrolments at once, keyed by enrolment id.
     *
     * The row-level answer reads every enrolled item, so asking it per row costs a query per item
     * per row -- and lazy loading throws outside production anyway. Priming resolves the whole
     * page's items in one go and hands each line its own.
     *
     * @param  Module|null  $module  the enrolments' offer's module, when the caller already has
     *                               it to hand -- passing it lets primeEnrollmentItems() skip
     *                               re-fetching it (and its translations) per page load
     * @return array<int, string[]>
     */
    public function customerVisibilityProblemsFor($enrollments, ?Module $module = null): array
    {
        $enrollments = collect($enrollments)->filter()->values();

        if ($enrollments->isEmpty()) {
            return [];
        }

        $this->primeEnrollmentItems($enrollments, $module);

        return $enrollments->mapWithKeys(fn (BogoOfferStore $enrollment) => [
            $enrollment->id => $this->customerVisibilityProblems($enrollment),
        ])->all();
    }

    /**
     * Resolve the items behind these enrolments once and hang each on its own frozen line.
     *
     * Read without the store and zone scopes on purpose: the question is whether the enrolled
     * item exists at all, and a scope narrowed to the current request's store or zone would
     * answer "deleted" for an item that is merely somebody else's. The checks that follow compare
     * store_id themselves.
     */
    protected function primeEnrollmentItems($enrollments, ?Module $module = null): void
    {
        $lines = collect($enrollments)->flatMap(fn (BogoOfferStore $enrollment) => $enrollment->items);
        $ids = $lines->pluck('item_id')->filter()->unique()->values()->all();

        // storage comes along because setRelation() below REPLACES whatever the caller had
        // already eager-loaded onto the line. Without it a page that loaded items.item.storage
        // for its images lost it again here, and every frozen line fell back to a raw `storages`
        // lookup of its own -- see DescribesFrozenItemLine::sourceImageDisk().
        $items = $ids
            ? Item::withoutGlobalScope(StoreScope::class)
                ->withoutGlobalScope(ZoneScope::class)
                ->with(['category.parent', 'storage'])
                ->whereIn('id', $ids)->get()->keyBy('id')
            : collect();

        if ($module && $items->every(fn (Item $item) => (int) $item->module_id === (int) $module->id)) {
            $items->each(fn (Item $item) => $item->setRelation('module', $module));
        } else {
            $items->load(['module' => fn ($query) => $query->withoutTranslation()]);
        }

        foreach ($lines as $line) {
            // Null is an answer too -- the loops below read it as a deleted item -- so the
            // relation is set either way rather than left unloaded.
            $line->setRelation('item', $items->get($line->item_id));
        }

        // The customer-side rule's own memo of which items are listable, for the same batch.
        $this->primeActiveItems($ids);
    }

    /**
     * Whether this frozen selection can still be built, split into what stops it and what merely
     * hides it.
     *
     * A selection is validated when it is submitted, not when it is approved. Between the two the
     * item can have been deleted, or its variation or add-on dropped, and approving that publishes
     * a bundle nobody can assemble -- so blocking problems refuse the approval outright. Warnings
     * do not: a switched-off or sold-out item comes back, and the admin is told which one rather
     * than left to wonder why a live offer is nowhere in the app.
     *
     * @return array{blocking: string[], warnings: string[]}
     */
    protected function enrollmentIntegrityProblems(BogoOfferStore $enrollment): array
    {
        $blocking = [];
        $warnings = [];

        foreach ($enrollment->items as $line) {
            $label = $line->item_name ?: translate('messages.this item');

            // Primed by primeEnrollmentItems() when a whole page is being asked at once; looked
            // up on its own otherwise, which is what the drawers do.
            $item = $line->relationLoaded('item')
                ? $line->item
                : Item::withoutGlobalScope(StoreScope::class)
                    ->withoutGlobalScope(ZoneScope::class)
                    ->find($line->item_id);

            if (! $item || (int) $item->store_id !== (int) $enrollment->store_id) {
                $blocking[] = $label.' : '.translate('messages.this item is no longer on the stores menu');

                continue;
            }

            // Frozen variations name their option by LABEL in both shapes, so a renamed option and
            // a deleted one are the same failure: nothing on the item matches what was chosen, and
            // both pricing helpers answer that with zero rather than with an error.
            $chosen = Helpers::decodeJsonToArray($line->variations) ?? [];
            $foodGroups = Helpers::decodeJsonToArray($item->food_variations) ?? [];

            if (! $this->usesFoodVariations($item)) {
                // The non-food shape: one row addressed by its "-" joined combination key. A
                // selection frozen in the food shape -- which happens when the item's variation
                // setup is switched after the line was saved -- names the same combination by its
                // labels, so it is joined back into a key rather than read as a missing option.
                // The combination itself still has to exist: rename or remove one and this blocks.
                $key = $this->combinationKey($chosen);

                if ($key !== null && ! $this->combinationFor($item, $chosen)) {
                    $blocking[] = $label.' : '.translate('The selected variation is no longer available')
                        .' ('.$key.')';
                }

                $chosen = [];
            }

            foreach ($chosen as $variation) {
                // A food item holding a {type} selection was frozen by a picker that disagreed
                // with the order path about which shape applies. It cannot be priced the way the
                // order will price it, so it blocks.
                if (isset($variation['type'])) {
                    $blocking[] = $label.' : '.translate('The selected variation is no longer available');

                    continue;
                }

                $group = collect($foodGroups)->firstWhere('name', $variation['name'] ?? null);
                $labels = collect($group['values'] ?? [])->pluck('label')->all();

                foreach ($variation['values']['label'] ?? [] as $option) {
                    if (! in_array($option, $labels, true)) {
                        $blocking[] = $label.' : '.translate('The selected variation is no longer available')
                            .' ('.($variation['name'] ?? '').' - '.$option.')';
                    }
                }
            }

            $addOnIds = Helpers::decodeJsonToArray($line->add_on_ids) ?? [];

            if ($addOnIds) {
                $alive = AddOn::whereIn('id', $addOnIds)->pluck('id')->all();

                if (count($alive) !== count(array_unique($addOnIds))) {
                    $blocking[] = $label.' : '.translate('messages.a selected add on has been deleted from this item');
                }
            }

            if (! $item->status) {
                $warnings[] = $label.' : '.translate('messages.this item is currently switched off');
            } elseif ($reason = $this->stockShortfallReason($item, $line, 1)) {
                $warnings[] = $label.' : '.$reason;
            }

            // A switched-off CATEGORY takes the item off every customer surface while the item's
            // own switch stays on and its stock stays full, so the vendor reads the item as fine
            // and cannot see what is wrong with it. The customer-side rule that hides the bundle
            // -- Item::active() -- can only answer "<item> is currently unavailable", which sends
            // them to look at the item. Named here, where the panel is explaining itself.
            if ($item->status && ! $this->itemIsActive($item)) {
                $warnings[] = $label.' : '.translate('This item is not listable so customers can not see it');
            }
        }

        return [
            'blocking' => array_values(array_unique($blocking)),
            'warnings' => array_values(array_unique($warnings)),
        ];
    }

    /** An offer past its end date cannot be enrolled into: the bundle would never be served once. */
    protected function bogoOfferHasEnded(?BogoOffer $offer): bool
    {
        return (bool) ($offer?->end_date && $offer->end_date->isPast());
    }

    /** Whether the store offers at least one of the order types the offer is limited to. */
    protected function bogoStoreServesOfferTypes(?Store $store, BogoOffer $offer): bool
    {
        if (! BogoOffer::orderTypesEnabled() || ! $store) {
            return true;
        }

        foreach (['delivery', 'take_away'] as $type) {
            if ($store->{$type} && $this->bogoServesOrderType($offer, $type)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Why a store may not join this offer, as a coded error, or null when it may.
     *
     * @return array{code: string, message: string}|null
     */
    protected function bogoJoinBlockedReason(BogoOffer $offer, ?Store $store): ?array
    {
        if (! $offer->status) {
            return ['code' => 'offer', 'message' => translate('This offer is not running')];
        }

        if ($this->bogoOfferHasEnded($offer)) {
            return ['code' => 'offer', 'message' => translate('This offer has already ended')];
        }

        if (! $this->bogoStoreServesOfferTypes($store, $offer)) {
            return ['code' => 'order_type', 'message' => translate('messages.your store does not support the order types of this offer')];
        }

        return null;
    }

    /** A denial the ADMIN issued, which the store may rework but not erase. */
    protected function bogoDeniedByAdmin($enrollment): bool
    {
        return $enrollment
            && $this->enrollmentState($enrollment) === 'rejected'
            && $enrollment->rejected_by === 'admin';
    }

    /**
     * Why this enrolment's actions are inert, or null when they are live.
     *
     * An ended offer closes the record: nothing about it can be answered, cancelled or left any
     * more. An admin's denial is not erasable either -- it is reworked and resubmitted.
     */
    protected function bogoEnrollmentLockedReason($enrollment, ?BogoOffer $offer = null): ?string
    {
        $offer ??= $enrollment?->bogoOffer;

        if ($this->bogoOfferHasEnded($offer)) {
            return translate('This offer has already ended');
        }

        if ($this->bogoDeniedByAdmin($enrollment)) {
            return translate('A denied request can only be reworked and resubmitted');
        }

        return null;
    }

    /**
     * The shared delete guard plus the closed-record rule.
     *
     * Order matters: the shared guard sends an unanswered admin request off to be answered rather
     * than deleted, which is right until the offer ends -- after that there is nothing to answer
     * either, and pointing the vendor at a decision they can no longer make is worse than saying
     * plainly that the offer is over.
     */
    protected function bogoDeleteBlockedReason($enrollment, ?BogoOffer $offer = null): ?string
    {
        return $this->bogoEnrollmentLockedReason($enrollment, $offer)
            ?? $this->storeDeleteBlockedReason($enrollment);
    }

    /** Answering an admin's invitation, which an ended offer no longer has anything to give. */
    protected function bogoRespondBlockedReason($enrollment, ?BogoOffer $offer, string $notPendingMessage): ?string
    {
        return $this->respondBlockedReason($enrollment, $notPendingMessage)
            ?? ($this->bogoOfferHasEnded($offer) ? translate('This offer has already ended') : null);
    }

    /**
     * What the store may do, with the closed-record rule applied.
     *
     * Rework survives a denial -- it is how a store answers one, and the only thing an admin's
     * denial may be answered with -- but not the end of the offer: resubmit runs through the same
     * joinable guard as join, which refuses an offer that has finished, so a button left on an
     * ended row could only ever collect a 403.
     */
    protected function bogoEnrollmentActions($enrollment, ?BogoOffer $offer, bool $isEligible): array
    {
        $actions = $this->enrollmentActions($enrollment, $isEligible);
        $ended = $this->bogoOfferHasEnded($offer);

        return array_merge($actions, [
            'can_respond' => $actions['can_respond'] && ! $ended,
            'can_resubmit' => $actions['can_resubmit'] && ! $ended,
            // Cancelling survives the store's own refusal but not the admin's.
            'can_cancel' => $actions['can_cancel'] && ! $ended && ! $this->bogoDeniedByAdmin($enrollment),
            'can_leave' => $actions['can_leave'] && ! $ended,
        ]);
    }
}
