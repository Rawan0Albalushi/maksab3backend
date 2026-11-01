<?php

namespace App\Http\Controllers\Api\V1\Common\ProCustomer;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Resources\Common\ProCustomer\FaqResource;
use App\Services\System\ProCustomerFaqService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FaqController extends BaseApiController
{
    public function __construct(
        private readonly ProCustomerFaqService $proCustomerFaqService
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $faqs = $this->proCustomerFaqService->getList(
            filters: ['search' => $request->query('search')],
            paginate: ['per_page' => $this->perPage($request), 'page' => $this->page($request)],
        );

        return $this->responseFormatter(config('response.default_200'), [
            'data' => FaqResource::collection($faqs),
            'pagination' => $this->paginateFormatter($faqs),
        ]);
    }
}
