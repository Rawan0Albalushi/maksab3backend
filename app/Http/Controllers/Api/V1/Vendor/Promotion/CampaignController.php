<?php

namespace App\Http\Controllers\Api\V1\Vendor\Promotion;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Requests\Vendor\Promotion\CampaignIdRequest;
use App\Http\Resources\Vendor\Promotion\CampaignResource;
use App\Services\Marketing\CampaignService;
use App\Traits\Api\ApiRequestContextTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CampaignController extends BaseApiController
{
    use ApiRequestContextTrait;

    public function __construct(private readonly CampaignService $campaignService) {}

    public function index(Request $request): JsonResponse
    {
        $store = $this->vendorStore($request);
        $request->merge(['vendor_store_id' => $store?->id]);

        return $this->pagedResponse(
            $this->campaignService->getVendorList(['module_id' => $store?->module_id], $this->pageParams($request)),
            CampaignResource::class
        );
    }

    public function join(CampaignIdRequest $request): JsonResponse
    {
        return $this->serviceResponse(
            $this->campaignService->updateStoreMembership($request->input('campaign_id'), $this->vendorStore($request), join: true),
            config('response.default_update_200')
        );
    }

    public function leave(CampaignIdRequest $request): JsonResponse
    {
        return $this->serviceResponse(
            $this->campaignService->updateStoreMembership($request->input('campaign_id'), $this->vendorStore($request), join: false),
            config('response.default_update_200')
        );
    }
}
