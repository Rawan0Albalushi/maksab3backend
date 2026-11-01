<?php

namespace App\Http\Controllers\Api\V1\Customer\Promotion;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Requests\Customer\Promotion\CashBackCalculateRequest;
use App\Http\Resources\Customer\Promotion\CashBackCalculationResource;
use App\Http\Resources\Customer\Promotion\CashBackResource;
use App\Services\Marketing\CashBackService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CashBackController extends BaseApiController
{
    public function __construct(
        private readonly CashBackService $cashBackService
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $cashBacks = $this->cashBackService->getList(
            filters: $this->filters($request),
            paginate: ['per_page' => $this->perPage($request), 'page' => $this->page($request)],
        );

        return $this->responseFormatter(config('response.default_200'), [
            'data' => CashBackResource::collection($cashBacks),
            'pagination' => $this->paginateFormatter($cashBacks),
        ]);
    }

    public function calculate(CashBackCalculateRequest $request): JsonResponse
    {
        $filters = $request->filters();

        $calculation = $this->cashBackService->calculateForAmount(
            amount: $request->amount(),
            customerId: $filters['customer_id'],
            moduleId: $filters['module_id'],
        );

        return $this->responseFormatter(
            config('response.default_200'),
            new CashBackCalculationResource($calculation)
        );
    }

    private function filters(Request $request): array
    {
        return [
            'customer_id' => auth()->id() ?? $request->input('customer_id') ?? 'all',
            'module_id' => getModuleId($request->header('moduleId')),
        ];
    }

}
