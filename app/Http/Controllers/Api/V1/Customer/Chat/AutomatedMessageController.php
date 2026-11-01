<?php

namespace App\Http\Controllers\Api\V1\Customer\Chat;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Resources\Common\Chat\AutomatedMessageResource;
use App\Services\Chat\AutomatedMessageService;
use App\Traits\Api\ApiRequestContextTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AutomatedMessageController extends BaseApiController
{
    use ApiRequestContextTrait;

    public function __construct(
        private readonly AutomatedMessageService $automatedMessageService
    ) {}

    public function index(Request $request): JsonResponse
    {
        return $this->pagedResponse(
            $this->automatedMessageService->getList([], $this->pageParams($request)),
            AutomatedMessageResource::class
        );
    }
}
