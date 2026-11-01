<?php

namespace App\Http\Controllers\Api\V1\Customer\Promotion;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Requests\Customer\Promotion\HappyHourRunningRequest;
use App\Http\Requests\Customer\Promotion\HappyHourStoreListRequest;
use App\Http\Resources\Customer\Promotion\HappyHourStoreResource;
use App\Http\Resources\Customer\Promotion\RunningHappyHourResource;
use App\Services\Promotion\BogoOfferCustomerService;
use App\Services\Promotion\HappyHourCustomerService;
use App\Traits\Api\ApiRequestContextTrait;
use Illuminate\Http\JsonResponse;

/**
 * Customer-facing Happy Hour.
 *
 * A happy hour belongs to a module and reaches a store only through an approved enrolment, so the
 * list is scoped by the caller's moduleId header and by the zone their coordinates resolve to.
 *
 * The discount is not computed here. It comes from Helpers::get_store_discount(), the same
 * resolver every item price goes through, so a store's happy hour price cannot disagree with the
 * price on its own menu.
 */
class HappyHourController extends BaseApiController
{
    use ApiRequestContextTrait;

    public function __construct(private readonly HappyHourCustomerService $happyHourService)
    {
    }

    /**
     * Stores taking part in a happy hour here.
     *
     * Defaults to every participating store with an `is_running_now` flag; `running=1` narrows to
     * the ones whose window is open at this moment.
     */
    public function index(HappyHourStoreListRequest $request): JsonResponse
    {
        $this->applyZoneIds($request);

        $stores = $this->happyHourService->getStoreList(
            filters: $request->filters() + [
                'zone_ids' => $this->zoneIds($request),
                'module_id' => $this->currentModuleId(),
                'longitude' => $request->header('longitude'),
                'latitude' => $request->header('latitude'),
            ],
            paginate: ['per_page' => $request->perPage(), 'page' => $request->page()],
        );

        // The card carries a BOGO strip too, so the offers are resolved for the whole page in one
        // pass rather than per store.
        app(BogoOfferCustomerService::class)->primeStoreOffers($stores->getCollection(), [
            'zone_ids' => $this->zoneIds($request),
            'module_id' => $this->currentModuleId(),
        ]);

        return $this->responseFormatter(config('response.default_200'), [
            'data' => HappyHourStoreResource::collection($stores),
            'pagination' => $this->paginateFormatter($stores),
        ]);
    }

    /**
     * The happy hour running here right now -- the home screen banner.
     *
     * Exactly one, never a list: a module cannot hold two overlapping happy hours, because the
     * admin side refuses a colliding schedule in either order of creation and a switched-off
     * record still holds its slot.
     *
     * Answers `is_running: false` rather than 404 when nothing is on, so the client can treat it
     * as a poll and simply hide the banner.
     */
    public function running(HappyHourRunningRequest $request): JsonResponse
    {
        $this->applyZoneIds($request);

        $running = $this->happyHourService->findRunning([
            'zone_ids' => $this->zoneIds($request),
            'module_id' => $this->currentModuleId(),
        ]);

        return $this->responseFormatter(
            config('response.default_200'),
            (new RunningHappyHourResource($running ?? []))->render()
        );
    }
}
