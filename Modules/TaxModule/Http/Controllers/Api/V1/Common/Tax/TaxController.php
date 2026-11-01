<?php

namespace Modules\TaxModule\Http\Controllers\Api\V1\Common\Tax;

use App\Http\Controllers\Api\BaseApiController;
use Illuminate\Http\JsonResponse;
use Modules\TaxModule\Http\Requests\Common\Tax\TaxCalculationRequest;
use Modules\TaxModule\Http\Requests\Common\Tax\TaxListRequest;
use Modules\TaxModule\Http\Resources\Common\Tax\TaxResource;
use Modules\TaxModule\Services\CalculateTaxService;
use Modules\TaxModule\Services\TaxService;

class TaxController extends BaseApiController
{
    public function __construct(
        private readonly TaxService $taxService
    ) {
    }

    public function index(TaxListRequest $request): JsonResponse
    {
        $taxes = $this->taxService->getList(
            paginate: ['per_page' => $request->perPage(), 'page' => $request->page()],
        );

        return $this->responseFormatter(config('response.default_200'), [
            'data' => TaxResource::collection($taxes),
            'pagination' => $this->paginateFormatter($taxes),
        ]);
    }

    public function calculate(TaxCalculationRequest $request): JsonResponse
    {
        $payload = $request->payload();

        return $this->responseFormatter(config('response.default_200'), CalculateTaxService::getCalculatedTax(
            amount: $payload['amount'],
            productIds: $payload['productIds'],
            addonIds: $payload['addonIds'],
            storeData: false,
            additionalCharges: $payload['additionalCharges'],
            taxPayer: $payload['taxPayer'],
            orderId: $payload['orderId'],
            countryCode: $payload['countryCode'],
        ));
    }
}
