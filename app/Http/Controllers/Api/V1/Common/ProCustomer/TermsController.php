<?php

namespace App\Http\Controllers\Api\V1\Common\ProCustomer;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Resources\Common\ProCustomer\TermsResource;
use App\Services\System\DataSettingService;
use Illuminate\Http\JsonResponse;

class TermsController extends BaseApiController
{
    public function __construct(
        private readonly DataSettingService $dataSettingService
    ) {
    }

    public function show(): JsonResponse
    {
        return $this->responseFormatter(
            config('response.default_200'),
            new TermsResource($this->dataSettingService->proTermsPage(app()->getLocale()))
        );
    }
}
