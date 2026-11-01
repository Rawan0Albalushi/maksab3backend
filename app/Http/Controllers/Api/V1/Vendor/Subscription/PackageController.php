<?php

namespace App\Http\Controllers\Api\V1\Vendor\Subscription;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Resources\Vendor\Subscription\PackageResource;
use App\Services\Payment\SubscriptionPackageService;
use App\Traits\Api\ApiRequestContextTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PackageController extends BaseApiController
{
    use ApiRequestContextTrait;

    public function __construct(private readonly SubscriptionPackageService $subscriptionPackageService) {}

    public function index(Request $request): JsonResponse
    {
        $packages = $this->subscriptionPackageService->getList(
            filters: ['module_id' => $request->input('module_id')],
            paginate: $this->pageParams($request),
        );

        return $this->responseFormatter(config('response.default_200'), [
            'data' => PackageResource::collection($packages),
            'pagination' => $this->paginateFormatter($packages),
        ]);
    }
}
