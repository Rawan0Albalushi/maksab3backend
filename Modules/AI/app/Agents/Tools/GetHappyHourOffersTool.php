<?php

namespace Modules\AI\app\Agents\Tools;

use App\Models\HappyHour;
use App\Models\Store;
use App\Services\Promotion\HappyHourCatalog;
use App\Services\Promotion\HappyHourCustomerService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Modules\AI\app\Agents\AiResponseContext;

class GetHappyHourOffersTool implements Tool
{
    /**
     * @param int[] $zoneIds Overlapping zones the customer falls inside.
     */
    public function __construct(
        private readonly AiResponseContext $context,
        private readonly ?int $moduleId = null,
        private readonly array $zoneIds = [],
    ) {}

    public function description(): string
    {
        return 'Check whether a Happy Hour promotion is running right now, and get details about it: the discount percentage, which stores are participating, the time window (starts_at / ends_at / remaining seconds), and any minimum order amount. Use for "happy hour", "happy hours", "is there a happy hour", "happy hour discount", "happy hour restaurants", "happy hour stores", "happy hour deal" queries. Distinct from GetBestDealsTool (item discounts) and GetBogoOffersTool (BOGO campaigns) — Happy Hour is a time-limited store-wide discount campaign, not a product-level discount.';
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'include_stores' => $schema->boolean()->description('true to include a list of participating stores in the response, false for summary only. Default true.')->required()->nullable(),
            'limit'          => $schema->number()->description('Number of participating stores to return, default 6, or null for default')->required()->nullable(),
        ];
    }

    public function handle(Request $request): string
    {
        $args          = $request->all();
        $includeStores = ($args['include_stores'] ?? null) !== false;
        $limit         = min((int) ($args['limit'] ?? 6), 10);

        if (empty($this->zoneIds) || ! $this->moduleId) {
            return 'No Happy Hour information is available without a zone and module context.';
        }

        /** @var HappyHourCatalog $catalog */
        $catalog    = app(HappyHourCatalog::class);
        $happyHour  = $catalog->runningIn($this->zoneIds, $this->moduleId);

        if (! $happyHour) {
            $this->context->recordTool('GetHappyHourOffersTool');
            return 'No Happy Hour promotion is running right now in your area.';
        }

        $window    = $catalog->windowPayload($happyHour, $this->zoneIds, $this->moduleId);
        $formatted = $this->formatHappyHour($happyHour, $window);

        $storeLines = '';
        if ($includeStores) {
            /** @var HappyHourCustomerService $service */
            $service = app(HappyHourCustomerService::class);
            $stores  = $service->getStoreList([
                'zone_ids'  => $this->zoneIds,
                'module_id' => $this->moduleId,
                'running_only' => true,
            ], ['per_page' => $limit]);

            $storeData = collect($stores->items())->map($this->formatStore(...))->values()->all();
            $this->context->addHappyHourStores($storeData);

            if (! empty($storeData)) {
                $storeLines = '; Participating stores: ' . implode(', ', array_map(
                    fn (array $s): string => $s['name'] . ' [ID:' . $s['id'] . ']',
                    $storeData
                ));
            }
        }

        $formatted['store_count'] = (int) ($window['store_count'] ?? 0);
        $this->context->recordTool('GetHappyHourOffersTool');
        $this->context->addHappyHours([$formatted]);

        $minOrderNote = $happyHour->min_order_amount
            ? ' (min order: ' . number_format((float) $happyHour->min_order_amount, 2) . ')'
            : '';

        $remainingMin = $window['remaining_seconds'] > 0
            ? ceil($window['remaining_seconds'] / 60) . ' min remaining'
            : 'window may have just closed';

        return sprintf(
            'Happy Hour "%s" is LIVE — %s%% discount%s — ends at %s (%s) — %d store(s) participating%s.',
            $happyHour->title,
            number_format((float) $happyHour->discount, 0),
            $minOrderNote,
            $window['ends_at'] ?? 'unknown',
            $remainingMin,
            (int) ($window['store_count'] ?? 0),
            $storeLines
        );
    }

    private function formatHappyHour(HappyHour $happyHour, array $window): array
    {
        return [
            'id'                  => $happyHour->getKey(),
            'slug'                => $happyHour->getAttribute('slug'),
            'title'               => $happyHour->getAttribute('title'),
            'short_description'   => $happyHour->getAttribute('short_description'),
            'discount'            => (float) $happyHour->getAttribute('discount'),
            'min_order_amount'    => $happyHour->min_order_amount !== null
                ? (float) $happyHour->getAttribute('min_order_amount')
                : null,
            'module_id'           => (int) $happyHour->getAttribute('module_id'),
            'duration_type'       => $happyHour->getAttribute('duration_type'),
            'is_permanent'        => (bool) $happyHour->getAttribute('is_permanent'),
            'weekly_days'         => $happyHour->getAttribute('weekly_days') ?? [],
            'start_time'          => $happyHour->getAttribute('start_time'),
            'end_time'            => $happyHour->getAttribute('end_time'),
            'is_running_now'      => true,
            'started_at'          => $window['started_at'] ?? null,
            'ends_at'             => $window['ends_at'] ?? null,
            'remaining_seconds'   => (int) ($window['remaining_seconds'] ?? 0),
            'icon_full_url'       => $happyHour->icon_full_url,
            'cover_image_full_url' => $happyHour->cover_image_full_url,
        ];
    }

    private function formatStore(Store $store): array
    {
        return [
            'id'            => $store->getKey(),
            'name'          => $store->getAttribute('name'),
            'logo_full_url' => $store->logo_full_url,
            'rating'        => (float) ($store->getAttribute('rating') ?? 0),
            'delivery_time' => $store->getAttribute('delivery_time'),
            'minimum_order' => (float) ($store->getAttribute('minimum_order') ?? 0),
            'free_delivery' => (bool) $store->getAttribute('free_delivery'),
        ];
    }
}
