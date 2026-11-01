<?php

namespace Modules\AI\app\Agents\Tools;

use App\Models\User;
use App\Services\Promotion\BogoOfferCatalog;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Modules\AI\app\Agents\AiResponseContext;

/**
 * Buy-N-get-M offers the customer could actually take right now.
 *
 * Reads through BogoOfferCatalog, the same service the customer BOGO screens answer from,
 * so the assistant can never advertise an offer those screens do not show. availableOffers()
 * already excludes anything ended, exhausted, or with no servable store in the customer's
 * zone/module — nothing here re-derives that.
 */
class GetBogoOffersTool implements Tool
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
        return 'Get the BOGO ("buy N, get M") offers running right now — "bogo", "buy one get one", "buy 2 get 1", "b1g1", "buy one get one free", "any free item deals". These are BUNDLES: the customer buys a set of items and gets others free, at a fixed bundle price. Only offers that can actually be ordered are returned. You CANNOT add one to the cart — the customer taps the offer card to open it in the app.';
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'limit' => $schema->number()->description('How many offers to return, default 6, or null for the default')->required()->nullable(),
        ];
    }

    public function handle(Request $request): string
    {
        $this->context->recordTool('GetBogoOffersTool');

        $limit = min((int) ($request->all()['limit'] ?? 6), 10);

        if (! $this->zoneIds || ! $this->moduleId) {
            return 'No delivery zone is known for this chat session, so BOGO offers cannot be listed.';
        }

        $catalog = app(BogoOfferCatalog::class);
        $offers = $catalog->availableOffers($this->zoneIds, $this->moduleId)->latest()->limit($limit)->get();

        if ($offers->isEmpty()) {
            return 'There are no BOGO offers available in this area right now.';
        }

        // isGuest/phone: a guest session in chat has no phone number to attribute past usage
        // to, so bogoRemainingUses() falls back to reporting the offer's full allowance for
        // them — the same "nothing to attribute it to" behaviour the API gives a phone-less
        // guest there.
        $isGuest = $this->user === null;
        $cards = $offers->map(fn ($offer) => $catalog->offerCard($offer, $this->user?->getKey(), $isGuest, null))
            ->values()->all();
        $this->context->addBogoOffers($cards);

        // A compact line per offer beside the card. Without it the model narrates from memory
        // of the question rather than from the data, and describes "buy 2 get 1" over a card
        // that says something else.
        $lines = array_map(function (array $card) {
            $remaining = $card['remaining_uses'] ?? null;
            $left = $remaining === null ? '' : ' — '.$remaining.' use(s) left for this customer';

            return $card['title'].' [OFFER ID:'.$card['id'].'] '.$card['offer_label']
                .' (valid until '.$card['valid_until'].')'.$left;
        }, $cards);

        return count($cards).' BOGO offer(s) available: '.implode(' | ', $lines)
            .'. Each is a bundle bought as one unit at a fixed price. To take one, the customer'
            .' opens the offer card and picks their items there — you cannot add it for them.';
    }
}
