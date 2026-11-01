<?php

namespace Modules\AI\app\Agents\Tools;

use App\Models\User;
use App\Services\Promotion\BogoOfferCatalog;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Modules\AI\app\Agents\AiResponseContext;

/**
 * What is actually inside one BOGO offer, store by store.
 *
 * An offer is a headline ("Buy 2 Get 1"); the bundle is what a particular store enrolled
 * with, and two stores running the same offer put different items in it at different
 * prices. So "what do I get?" can only be answered per store, which is what this returns.
 */
class GetBogoOfferDetailsTool implements Tool
{
    /**
     * @param int[] $zoneIds  Overlapping zones the user falls inside (filter with whereIn).
     */
    public function __construct(
        private readonly AiResponseContext $context,
        private readonly ?int $moduleId = null,
        private readonly array $zoneIds = [],
        private readonly ?User $user = null,
        private readonly ?string $guestId = null,
    ) {}

    public function description(): string
    {
        return 'Get what is inside one BOGO offer — which stores run it, what the customer buys, what they get free, and the bundle price at each. Use after GetBogoOffersTool when the customer asks about a specific offer ("what do I get with that one?", "which stores have it?", "how much is it?"). Pass the OFFER ID from the offers list.';
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'offer_id' => $schema->number()->description('The OFFER ID from GetBogoOffersTool')->required(),
            'limit'    => $schema->number()->description('How many participating stores to return, default 5, or null')->required()->nullable(),
        ];
    }

    public function handle(Request $request): string
    {
        $this->context->recordTool('GetBogoOfferDetailsTool');

        $args    = $request->all();
        $offerId = (int) ($args['offer_id'] ?? 0);
        $limit   = min((int) ($args['limit'] ?? 5), 10);

        if (! $offerId) {
            return 'Ask which offer they mean, then call again with its OFFER ID.';
        }
        if (! $this->zoneIds || ! $this->moduleId) {
            return 'No delivery zone is known for this chat session, so the offer cannot be looked up.';
        }

        $catalog = app(BogoOfferCatalog::class);

        // Resolved through availableOffers() rather than by id, so an offer that has ended,
        // sold out, or has no servable store left reads as "not available" instead of being
        // described in detail and refused at the cart.
        $offer = $catalog->availableOffers($this->zoneIds, $this->moduleId)
            ->where(fn ($q) => $q->where('id', $offerId)->orWhere('slug', $offerId))
            ->first();

        if (! $offer) {
            return 'That offer is not available right now — it may have ended, run out, or have no'
                .' participating store open in this area. Suggest the current offers instead.';
        }

        $isGuest = $this->user === null;
        $card = $catalog->offerCard($offer, $this->user?->getKey(), $isGuest, null);
        $this->context->addBogoOffers([$card]);

        $bundles = $catalog->bundleQuery($offer, $this->zoneIds, $this->moduleId)
            ->limit($limit)->get()
            ->map(fn ($enrollment) => $catalog->bundleCard($enrollment))
            ->values()->all();

        if (! $bundles) {
            return $card['title'].' ('.$card['offer_label'].') has no store currently able to serve it.';
        }

        $lines = array_map(function (array $bundle) {
            $store = data_get($bundle, 'store.name', 'A store');
            $buy   = collect(data_get($bundle, 'buy_items', []))
                ->map(fn ($i) => data_get($i, 'quantity', 1).'x '.data_get($i, 'name', 'item'))->implode(' + ');
            $get   = collect(data_get($bundle, 'free_items', []))
                ->map(fn ($i) => data_get($i, 'quantity', 1).'x '.data_get($i, 'name', 'item'))->implode(' + ');
            $price = data_get($bundle, 'bundle_price', data_get($bundle, 'final_price'));

            return $store.': buy '.($buy ?: '—').', get '.($get ?: '—').' free'
                .($price !== null ? ' for '.$price : '');
        }, $bundles);

        return $card['title'].' — '.$card['offer_label'].', valid until '.$card['valid_until'].'. '
            .count($bundles).' store(s): '.implode(' | ', $lines)
            .'. The bundle is bought as one unit at that fixed price. The customer takes it from the'
            .' offer card — you cannot add it to their cart.';
    }
}
