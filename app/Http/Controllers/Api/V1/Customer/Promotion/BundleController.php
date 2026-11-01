<?php

namespace App\Http\Controllers\Api\V1\Customer\Promotion;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Requests\Customer\Promotion\BundleListRequest;
use App\Http\Resources\Customer\Promotion\BundleResource;
use App\Services\Promotion\BundleCustomerService;
use App\Services\Customer\PersonalizationService;
use App\Services\Promotion\BundleService;
use App\Traits\Api\ApiRequestContextTrait;
use Illuminate\Http\JsonResponse;

class BundleController extends BaseApiController
{
    use ApiRequestContextTrait;

    public function __construct(private readonly BundleCustomerService $bundleService)
    {
    }

    public function home(BundleListRequest $request): JsonResponse
    {
        $bundles = $this->bundleService->getHomeList(
            $this->filters($request),
            (int) ($request->input('limit') ?: 10)
        );

        return $this->responseFormatter(config('response.default_200'), [
            'data' => $this->present($bundles),
        ]);
    }

    public function index(BundleListRequest $request): JsonResponse
    {
        $bundles = $this->bundleService->getList(
            $this->filters($request),
            ['per_page' => $request->perPage(), 'page' => $request->page()],
        );

        return $this->responseFormatter(config('response.default_200'), [
            'data' => $this->present($bundles->getCollection()),
            'pagination' => $this->paginateFormatter($bundles),
        ]);
    }

    public function storeBundles(BundleListRequest $request): JsonResponse
    {
        if (! $request->filled('store_id')) {
            return $this->errorResponse(config('response.default_404'), translate('No data found'), 'store_id');
        }

        $bundles = $this->bundleService->getList(
            $this->filters($request),
            ['per_page' => $request->perPage(), 'page' => $request->page()],
        );

        return $this->responseFormatter(config('response.default_200'), [
            'data' => $this->present($bundles->getCollection()),
            'pagination' => $this->paginateFormatter($bundles),
        ]);
    }

    public function show(BundleListRequest $request, string $id): JsonResponse
    {
        $bundle = $this->bundleService->find($id, $this->filters($request));

        if (! $bundle) {
            return $this->errorResponse(config('response.default_404'), translate('No data found'), 'bundle');
        }

        $this->recordView($bundle);

        $pricing = $this->bundleService->pricingFor([$bundle]);

        return $this->responseFormatter(
            config('response.default_200'),
            (new BundleResource(
                $bundle,
                $pricing[$bundle->id] ?? [],
                $this->bundleService->unavailableReason($bundle),
                withItems: true,
                addOnLines: app(BundleService::class)->addOnLinesFor([$bundle]),
            ))->render()
        );
    }

    /**
     * Opening a bundle is a view of the items inside it.
     *
     * Recorded per member rather than once for the bundle: `customer_preferences` scores items,
     * categories and stores, and none of those is a bundle -- `recordItemAction()` raises all
     * three from one item id, so the members are what carries the view into the summary the
     * lists above are then ranked by. The same call ItemController makes on an item's own page.
     *
     * `items` is already loaded by the list query, so this adds no relation lookup.
     */
    private function recordView(mixed $bundle): void
    {
        $userId = auth('api')->id();

        if (! $userId) {
            return;
        }

        foreach ($bundle->items as $line) {
            if ($line->isService() || ! $line->targetId()) {
                continue;
            }

            app(PersonalizationService::class)->recordItemAction($userId, (int) $line->targetId(), 'item_view');
        }
    }

    private function present(iterable $bundles): array
    {
        $pricing = $this->bundleService->pricingFor($bundles);
        // Once for the page, not once per bundle — these endpoints render every bundle's lines
        // and a per-bundle lookup would be an N+1 across the list (rule 11).
        $addOnLines = app(BundleService::class)->addOnLinesFor($bundles);

        $data = [];

        foreach ($bundles as $bundle) {
            $data[] = (new BundleResource(
                $bundle,
                $pricing[$bundle->id] ?? [],
                $this->bundleService->unavailableReason($bundle),
                withItems: true,
                addOnLines: $addOnLines,
            ))->render();
        }

        return $data;
    }

    private function filters(BundleListRequest $request): array
    {
        return [
            'module_id' => $this->headerModuleId($request),
            'zone_ids' => $this->zoneIds($request),
            'store_id' => $request->input('store_id'),
            'search' => $request->input('search'),
            // Null for a guest, which is how every personalisation call site signs "rank this
            // for nobody in particular".
            'customer_id' => auth('api')->id(),
        ];
    }
}
