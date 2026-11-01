<?php

namespace App\Http\Controllers\Api\V1\Vendor\DeliveryMan;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Requests\Vendor\DeliveryMan\DeliveryManIdRequest;
use App\Http\Requests\Vendor\DeliveryMan\DeliveryManSearchRequest;
use App\Http\Requests\Vendor\DeliveryMan\DeliveryManStatusRequest;
use App\Http\Requests\Vendor\DeliveryMan\DeliveryManStoreRequest;
use App\Http\Requests\Vendor\DeliveryMan\DeliveryManUpdateRequest;
use App\Http\Resources\Vendor\DeliveryMan\DeliveryManResource;
use App\Services\DeliveryMan\DeliveryManService;
use App\Traits\Api\ApiRequestContextTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DeliveryManController extends BaseApiController
{
    use ApiRequestContextTrait;

    public function __construct(protected DeliveryManService $deliveryManService)
    {
        $this->middleware(function (Request $request, \Closure $next) {
            if (! $this->vendorStore($request)?->sub_self_delivery) {
                return $this->errorResponse(config('response.forbidden_403'), translate('messages.Permission denied'), 'unauthorized');
            }

            return $next($request);
        });
    }

    public function index(Request $request): JsonResponse
    {
        $deliveryMen = $this->deliveryManService->getList(
            $this->storeFilters($request),
            $this->pageParams($request)
        );

        return $this->responseFormatter(config('response.default_200'), [
            'data' => DeliveryManResource::renderList($deliveryMen),
            'pagination' => $this->paginateFormatter($deliveryMen),
        ]);
    }

    public function search(DeliveryManSearchRequest $request): JsonResponse
    {
        $deliveryMen = $this->deliveryManService->searchList($request->filters(), $this->pageParams($request));

        return $this->pagedResponse($deliveryMen, DeliveryManResource::class);
    }

    public function show(DeliveryManIdRequest $request): JsonResponse
    {
        $deliveryMan = $this->deliveryManService->find($request->input('delivery_man_id'), $this->storeFilters($request));

        if (! $deliveryMan) {
            return $this->errorResponse(config('response.default_404'), translate('No data found'), 'delivery_man_id');
        }

        return $this->responseFormatter(config('response.default_200'), (new DeliveryManResource($deliveryMan))->toArray($request));
    }

    public function store(DeliveryManStoreRequest $request): JsonResponse
    {
        $this->deliveryManService->create($request->payload());

        return $this->responseFormatter(
            ['message' => translate('Added successfully')] + config('response.default_store_201')
        );
    }

    public function update(DeliveryManUpdateRequest $request, mixed $id): JsonResponse
    {
        if (! $this->deliveryManService->update($id, $request->payload(), $this->storeFilters($request))) {
            return $this->errorResponse(config('response.default_404'), translate('No data found'), 'delivery_man_id');
        }

        return $this->responseFormatter(
            ['message' => translate('Updated successfully')] + config('response.default_update_200')
        );
    }

    public function updateStatus(DeliveryManStatusRequest $request): JsonResponse
    {
        if (! $this->deliveryManService->updateStatus($request->input('delivery_man_id'), $request->input('status'), $this->storeFilters($request))) {
            return $this->errorResponse(config('response.default_404'), translate('No data found'), 'delivery_man_id');
        }

        return $this->responseFormatter(
            ['message' => translate('messages.deliveryman_status_updated')] + config('response.default_update_200')
        );
    }

    public function destroy(DeliveryManIdRequest $request): JsonResponse
    {
        if (! $this->deliveryManService->delete($request->input('delivery_man_id'), $this->storeFilters($request))) {
            return $this->errorResponse(config('response.default_404'), translate('No data found'), 'delivery_man_id');
        }

        return $this->responseFormatter(
            ['message' => translate('Deleted successfully')] + config('response.default_delete_200')
        );
    }

    private function storeFilters(Request $request): array
    {
        return ['store_id' => $this->vendorStoreId($request)];
    }
}
