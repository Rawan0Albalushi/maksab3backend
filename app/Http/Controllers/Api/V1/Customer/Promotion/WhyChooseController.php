<?php

namespace App\Http\Controllers\Api\V1\Customer\Promotion;

use App\Traits\Api\CachesApiPayloadTrait;
use App\Http\Controllers\Api\BaseApiController;
use App\Http\Resources\Customer\Promotion\WhyChooseResource;
use App\Services\Marketing\ModuleWiseWhyChooseService;
use App\Traits\Api\ApiRequestContextTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WhyChooseController extends BaseApiController
{
    use CachesApiPayloadTrait;

    use ApiRequestContextTrait;

    public function __construct(
        private readonly ModuleWiseWhyChooseService $moduleWiseWhyChooseService
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        return $this->cachedJson('api.other_banners_why_choose', $request, [], function () use ($request) {
            $banners = $this->moduleWiseWhyChooseService->getList(
                filters: ['module_id' => $this->currentModuleId()],
                paginate: ['per_page' => $this->perPage($request), 'page' => $this->page($request)],
            );

            return $this->responseFormatter(config('response.default_200'), [
                'data' => WhyChooseResource::collection($banners),
                'pagination' => $this->paginateFormatter($banners),
            ]);
        });
    }
}
