<?php

namespace Modules\AI\app\Agents\Tools;

use App\Models\Bundle;
use App\Services\Promotion\BundleCustomerService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Modules\AI\app\Agents\AiResponseContext;

/**
 * Bundle deals the customer could actually take right now — a fixed set of items sold
 * together at one discounted price.
 *
 * A newer sibling of BOGO ("buy N, get M"): both put several items together as one thing
 * bought at a fixed price, but a bundle has no "free" half — every member is charged, just
 * at a lower combined total. Reads through BundleCustomerService, the same service the
 * customer bundle screens answer from, so the assistant can never advertise a bundle those
 * screens do not show.
 */
class GetBundlesTool implements Tool
{
    /**
     * @param int[] $zoneIds  Overlapping zones the user falls inside (filter with whereIn).
     */
    public function __construct(
        private readonly AiResponseContext $context,
        private readonly ?int $moduleId = null,
        private readonly array $zoneIds = [],
    ) {}

    public function description(): string
    {
        return 'Get the bundle deals running right now — "bundle", "combo", "value pack", "meal deal", "set deal". These are a fixed set of items sold together at one discounted price — every member is charged, just less than buying them separately. Distinct from a BOGO offer (nothing in a bundle is free) and from GetBestDealsTool (a bundle is its own product, not a discount on an existing one). Only bundles that can actually be ordered are returned. You CANNOT add one to the cart — the customer taps the bundle card to open it in the app.';
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'search' => $schema->string()->description('Optional keyword to filter bundles by name/store, or null for all active bundles')->required()->nullable(),
            'limit'  => $schema->number()->description('How many bundles to return, default 8, or null for the default')->required()->nullable(),
        ];
    }

    public function handle(Request $request): string
    {
        $this->context->recordTool('GetBundlesTool');

        $args   = $request->all();
        $limit  = min((int) ($args['limit'] ?? 8), 10);
        $search = $args['search'] ?? null;

        if (! $this->zoneIds || ! $this->moduleId) {
            return 'No delivery zone is known for this chat session, so bundle deals cannot be listed.';
        }

        $service = app(BundleCustomerService::class);
        $bundles = $service->getHomeList([
            'zone_ids'  => $this->zoneIds,
            'module_id' => $this->moduleId,
            'search'    => $search,
        ], $limit);

        if (empty($bundles)) {
            return 'There are no bundle deals available' . ($search ? ' for "' . $search . '"' : ' in this area right now') . '.';
        }

        $pricing = $service->pricingFor($bundles);
        $cards = array_map(fn (Bundle $bundle) => $this->format($bundle, $pricing[$bundle->id] ?? []), $bundles);
        $this->context->addBundles($cards);

        // A compact line per bundle beside the card, matching how BOGO offers are described,
        // so the model narrates from the data rather than from memory of the question.
        $lines = array_map(function (array $card) {
            $line = $card['name'] . ' [BUNDLE ID:' . $card['id'] . '] ' . $card['item_count'] . ' items for ' . $card['final_price'];
            if ($card['discount_percentage'] > 0) {
                $line .= ' (' . $card['discount_percentage'] . '% off ' . $card['base_price'] . ')';
            }

            return $line;
        }, $cards);

        return count($cards) . ' bundle deal(s) available: ' . implode(' | ', $lines)
            . '. Each bundle is a fixed set of items bought together as one unit at that price. To'
            . ' take one, the customer opens the bundle card and adds it there — you cannot add it for them.';
    }

    private function format(Bundle $bundle, array $pricing): array
    {
        $memberNames = $bundle->items
            ->map(fn ($line) => $line->item?->name)
            ->filter()
            ->values()
            ->all();

        return [
            'id'                  => $bundle->getKey(),
            'name'                => $bundle->getAttribute('name'),
            'description'         => $bundle->getAttribute('description'),
            'image_full_url'      => $bundle->image_full_url,
            'base_price'          => $pricing['base_price'] ?? (float) $bundle->getAttribute('base_price'),
            'discount_percentage' => $pricing['discount_percentage'] ?? (float) $bundle->getAttribute('discount_percentage'),
            'final_price'         => $pricing['final_price'] ?? (float) $bundle->getAttribute('discounted_price'),
            'item_count'          => (int) $bundle->getAttribute('items_count'),
            'member_items'        => $memberNames,
            'store_id'            => (int) $bundle->getAttribute('store_id'),
            'store_name'          => $bundle->store?->getAttribute('name'),
            'module_id'           => (int) $bundle->getAttribute('module_id'),
        ];
    }
}
