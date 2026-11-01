<?php

namespace App\Http\Resources\Customer\Promotion;

use App\Http\Resources\BaseResource;
use App\Http\Resources\Customer\Store\StoreResource;
use App\Services\Promotion\BogoOfferCatalog;
use Illuminate\Http\Request;

/**
 * One store's bundle: what you buy, what you get free, and what it costs.
 *
 * `bundle_id` is the ENROLMENT's id and is what every write endpoint expects back -- adding this
 * bundle to a cart, or asking about it again. It is not the offer id, and the two are never
 * interchangeable, because several stores run one offer.
 *
 * The pricing block is built by the catalog rather than here: a happy hour is the one promotion
 * that reaches a bundle, and the cart shows the same figures from the same method, so the offer
 * screen and the cart cannot disagree about what a bundle costs.
 */
class BogoBundleResource extends BaseResource
{
    /**
     * @param  bool  $withStore  off for the store screen, which already knows the store.
     * @param  array  $addOnLines  "Extra Cheese (2)" per enrolment-line id, from
     *                             `AddOnLabels::forParents()`. Passed in because a frozen line
     *                             holds add-on IDS and resolving names is a query — which a
     *                             resource never runs (rule 3), and which the list endpoints have
     *                             to batch across the page anyway.
     * @param  array  $ratings  `['avg_rating' => .., 'rating_count' => ..]` per enrolment-line id,
     *                          from `ItemRatings::forParents()`. Passed in for the same reason:
     *                          a rating is read LIVE off the item rather than frozen with the
     *                          line, and that is a query per page, not per line.
     */
    public function __construct(
        $resource,
        private readonly bool $withStore = true,
        private readonly array $addOnLines = [],
        private readonly array $ratings = [],
    ) {
        parent::__construct($resource);
    }

    public function toArray(Request $request): array
    {
        $items = $this->resource->items;
        $buyItems = $items->where('type', 'buy');
        $freeItems = $items->where('type', 'get');

        $card = array_merge(parent::toArray($request), [
            'bundle_id' => (int) $this->resource->id,
            'bogo_offer_id' => (int) $this->resource->bogo_offer_id,
            'offer_title' => $this->resource->bogoOffer?->title,
            'offer_slug' => $this->resource->bogoOffer?->slug,
            'buy_count' => (int) $buyItems->sum('quantity'),
            'get_count' => (int) $freeItems->sum('quantity'),
            // Same fix as `image_full_url` on each line below: this was plucking the bare
            // stored filename under a key that promises an image, so a client could only render
            // it by guessing the folder and disk. The accessor resolves both.
            'item_thumbnails' => $items->take(4)->pluck('item_image_full_url')->filter()->values()->all(),
            'buy_items' => $buyItems->map(fn ($line) => $this->line($line))->values()->all(),
            'free_items' => $freeItems->map(fn ($line) => $this->line($line))->values()->all(),
        ]);

        if ($this->withStore) {
            $card['store'] = $this->resource->store
                ? (new StoreResource($this->resource->store))->render()
                : null;
        }

        return $card + $this->pricing();
    }

    /**
     * bundle_price / original_price / discount_* / final_price / is_happy_hour, from the trait the
     * cart reads too. `happy_hour_percentage` was attached by the service; a null one means no
     * window is open and the bundle is simply its own price.
     */
    private function pricing(): array
    {
        return app(BogoOfferCatalog::class)->bundlePricingFor(
            $this->resource,
            $this->resource->happy_hour_percentage ?? null
        );
    }

    /**
     * One frozen member, described from the snapshot rather than the live menu, so it still reads
     * correctly after the item is renamed or deleted.
     *
     * `price` is what the member was worth when the bundle was built -- the same figure on both
     * sides. A buy line is charged it; a free line is what the customer is spared.
     */
    private function line($line): array
    {
        return [
            'item_id' => (int) $line->item_id,
            'service_id' => $line->service_id,
            'name' => $line->item_name,
            // item_image is the bare stored filename; the accessor is what resolves it against
            // the line's own storage disk (DescribesFrozenItemLine::getItemImageFullUrlAttribute).
            // Same one every other frozen-line payload uses -- the bundle resources and the vendor
            // enrolment resource all read it, so this was the one place still shipping a filename
            // under a key promising a URL.
            'image_full_url' => $line->item_image_full_url,
            'quantity' => (int) $line->quantity,
            'price' => (float) $line->original_price,
            'is_free' => $line->type === 'get',
            'variations' => $line->variations ?: [],
            'variation_summary' => implode(', ', $line->variationLabels()),
            // The add-on counterpart of `variation_summary` — same shape as the bundle payloads,
            // so one client parser reads a BOGO line and a bundle line the same way.
            'add_on_summary' => implode(', ', $this->addOnLines[$line->id] ?? []),
            'add_on_ids' => $line->add_on_ids ?: [],
            'add_on_qtys' => $line->add_on_qtys ?: [],
            // Read LIVE off the item, not frozen with the line: name and price are what the
            // bundle was built from and must not move, but a rating is whatever customers say
            // today, which is how ItemResource reports it on an ordinary product card. Both keys
            // are always present, 0 where nobody has reviewed it yet.
            'avg_rating' => (float) ($this->ratings[$line->id]['avg_rating'] ?? 0),
            'rating_count' => (int) ($this->ratings[$line->id]['rating_count'] ?? 0),
        ];
    }
}
