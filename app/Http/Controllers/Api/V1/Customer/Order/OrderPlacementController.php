<?php

namespace App\Http\Controllers\Api\V1\Customer\Order;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Requests\Customer\Order\CheckoutSummaryRequest;
use App\Http\Requests\Customer\Order\PlaceOrderRequest;
use App\Http\Requests\Customer\Order\SurgePriceRequest;
use App\Http\Resources\Customer\Order\CheckoutSummaryResource;
use App\Services\Order\OrderService;
use App\Traits\Order\CartValidationTrait;
use App\Traits\Order\CheckoutSummaryTrait;
use App\Traits\Order\PlaceNewOrderTrait;
use App\Traits\Order\POSDeliveryTypeTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrderPlacementController extends BaseApiController
{
    use PlaceNewOrderTrait;
    use CartValidationTrait;
    // Reaches PlaceNewOrderTrait's own helpers, so the quote runs the pipeline the charge runs.
    use CheckoutSummaryTrait;
    // Only for buildDeliveryOptions() — the POS picker's Standard/Express/Slightly Delay rows,
    // reused so checkout-summary can never show a different window than the POS does.
    use POSDeliveryTypeTrait;

    public function __construct(
        private readonly OrderService $orderService
    ) {}

    public function store(PlaceOrderRequest $request): JsonResponse
    {
        $deliveryType = $this->orderService->resolveDeliveryType($request->input('delivery_type'));

        if ($deliveryType) {
            $request->merge(['delivery_type' => $deliveryType]);
        }

        return $this->envelope($this->placeNewOrder($request), config('response.default_store_201'));
    }

    public function storePrescription(PlaceOrderRequest $request): JsonResponse
    {
        return $this->envelope($this->placeNewOrder($request, true), config('response.default_store_201'));
    }

    /**
     * §14.4 — everything the checkout page shows, in one call.
     *
     * `get-Tax` and `get-surge-price` remain for shipped clients; this answers both, plus the
     * delivery fee, the Pro benefit and the cashback, against one set of inputs — so the page
     * cannot assemble a total from four calls that each saw a slightly different basket.
     */
    public function checkoutSummary(CheckoutSummaryRequest $request): JsonResponse
    {
        $result = $this->buildCheckoutSummary($request);

        if ($result['status_code'] !== 200) {
            return $this->responseFormatter(
                $this->statusConfig($result['status_code']),
                errors: $result['errors'],
            );
        }

        return $this->responseFormatter(
            config('response.default_200'),
            new CheckoutSummaryResource($result['summary']),
        );
    }

    public function tax(Request $request): JsonResponse
    {
        return $this->envelope($this->getCalculatedTax($request, skipStockCheck: true), config('response.default_200'));
    }

    public function surgePrice(SurgePriceRequest $request): JsonResponse
    {
        return $this->responseFormatter(config('response.default_200'), $this->getSurgePriceValue(
            $request->input('zone_id'),
            $request->input('module_id'),
            $request->input('date_time')
        ));
    }

    private function envelope(JsonResponse $legacy, array $success): JsonResponse
    {
        $status = $legacy->getStatusCode();
        $body = $legacy->getData(true);

        if ($status < 400) {
            $message = is_array($body) ? ($body['message'] ?? null) : null;
            unset($body['message']);

            return $this->responseFormatter($message ? ['message' => $message] + $success : $success, $body);
        }

        return $this->responseFormatter($this->statusConfig($status), errors: $this->errorRows($body));
    }

    private function errorRows(mixed $body): array
    {
        $errors = is_array($body) ? ($body['errors'] ?? null) : null;

        if (! is_array($errors)) {
            return [['code' => 'order', 'message' => translate('messages.Failed to place order')]];
        }

        return array_values(array_map(fn ($error) => [
            'code' => data_get($error, 'code', 'order'),
            'message' => data_get($error, 'message'),
        ], $errors));
    }
}
