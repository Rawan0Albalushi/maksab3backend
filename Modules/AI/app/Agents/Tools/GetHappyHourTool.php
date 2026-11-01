<?php

namespace Modules\AI\app\Agents\Tools;

use App\CentralLogics\Helpers;
use App\Http\Resources\Customer\Promotion\HappyHourResource;
use App\Services\Promotion\HappyHourCatalog;
use App\Services\Promotion\HappyHourCustomerService;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Modules\AI\app\Agents\AiResponseContext;

/**
 * Happy hour: a scheduled window during which a store takes a percentage off its whole menu.
 *
 * Distinct from the other things the platform calls a discount — an item's own discount, the
 * admin's standing store discount, a flash sale, a BOGO bundle, and a bundle deal — and only
 * one of them ever applies to a given item. Reads through HappyHourCatalog /
 * HappyHourCustomerService, the same services the customer happy-hour screens answer from,
 * because a window reaches a customer only through an approved enrolment in their own
 * zone/module and whether it is live can only be decided in PHP.
 */
class GetHappyHourTool implements Tool
{
    /**
     * @param int[] $zoneIds  Overlapping zones the user falls inside (filter with whereIn).
     */
    public function __construct(
        private readonly AiResponseContext $context,
        private readonly ?int $moduleId = null,
        private readonly array $zoneIds = [],
        private readonly ?float $latitude = null,
        private readonly ?float $longitude = null,
    ) {}

    public function description(): string
    {
        return 'Get happy hour information — whether one is running right now in the customer\'s area, and which stores take part. Use for "happy hour", "is there a happy hour", "any discounts on now", "which stores have offers today". A happy hour is a scheduled window with a percentage off a store\'s whole menu, NOT a per-item discount, NOT a BOGO bundle, and NOT a bundle deal.';
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'running_only' => $schema->boolean()->description('true for only the stores whose window is open right now, false or null for every store taking part')->required()->nullable(),
            'limit'        => $schema->number()->description('How many stores to return, default 6, or null')->required()->nullable(),
        ];
    }

    public function handle(Request $request): string
    {
        $this->context->recordTool('GetHappyHourTool');

        $args        = $request->all();
        $runningOnly = (bool) ($args['running_only'] ?? false);
        $limit       = min((int) ($args['limit'] ?? 6), 10);

        if (! $this->zoneIds || ! $this->moduleId) {
            return 'No delivery zone is known for this chat session, so happy hours cannot be looked up.';
        }

        $filters = ['zone_ids' => $this->zoneIds, 'module_id' => $this->moduleId];
        $running = app(HappyHourCustomerService::class)->findRunning($filters);

        if ($running) {
            // The same whitelisted shape the home-banner API sends — not the raw model, which
            // would leak columns like admin_id that a customer-facing card has no business
            // carrying.
            $card = (new HappyHourResource($running['happy_hour']))->render() + [
                'started_at'        => $running['window']['started_at'] ?? null,
                'ends_at'           => $running['window']['ends_at'] ?? null,
                'remaining_seconds' => (int) ($running['window']['remaining_seconds'] ?? 0),
                'store_count'       => (int) ($running['window']['store_count'] ?? 0),
            ];
            $this->context->addHappyHours([$card]);
        }

        $catalog = app(HappyHourCatalog::class);
        $query = $catalog->storesQuery($this->zoneIds, $this->moduleId, $this->longitude, $this->latitude);
        if ($runningOnly) {
            $query = $catalog->restrictToLive($query, $this->zoneIds, $this->moduleId, $this->longitude, $this->latitude);
        }
        $stores = $query->limit($limit)->get();

        if ($stores->isEmpty()) {
            return $runningOnly
                ? 'No store has a happy hour running right now in this area.'
                    .($running ? ' A happy hour is scheduled but no store is currently open in it.' : '')
                : 'No store in this area takes part in a happy hour.';
        }

        // Named per store rather than for the window in general: the window is the schedule,
        // but the rate in force is the store's, and it can be the admin's standing discount
        // instead when that one is larger.
        $lines = $stores->map(function ($store) use ($catalog) {
            $live = $catalog->runningHappyHour($store);
            $rate = Helpers::get_store_discount($store);
            $off = $live && ($rate['discount'] ?? 0) > 0 ? ' — '.(float) $rate['discount'].'% off right now' : '';

            return $store->name.' [STORE ID:'.$store->id.']'
                .($live ? ' (happy hour live'.$off.')' : ' (enrolled, not running now)');
        })->implode(' | ');

        $headline = $running
            ? 'A happy hour is running now: "'.($running['happy_hour']->title ?? 'Happy Hour').'".'
            : 'No happy hour window is open at this moment.';

        return $headline.' '.$stores->count().' store(s): '.$lines
            .'. A happy hour takes a percentage off the store\'s whole menu while the window is'
            .' open; it is not a per-item discount, not a BOGO bundle, and not a bundle deal.';
    }
}
