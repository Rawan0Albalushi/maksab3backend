<?php

namespace App\Http\Controllers\Api\V1\Customer\Order;

use App\CentralLogics\Helpers;
use App\Http\Controllers\Api\BaseApiController;
use App\Http\Requests\Customer\Order\MonthlyReorderRequest;
use App\Http\Requests\Customer\Order\ReorderRequest;
use App\Http\Resources\Customer\Order\ReorderResultResource;
use App\Services\Order\CartService;
use App\Services\Order\MonthlyOrderReminderService;
use App\Services\Order\OrderService;
use App\Traits\Order\CartValidationTrait;
use Illuminate\Http\JsonResponse;

class ReorderController extends BaseApiController
{
    use CartValidationTrait;

    public function __construct(
        private readonly OrderService $orderService,
        private readonly CartService $cartService,
        private readonly MonthlyOrderReminderService $monthlyOrderReminderService
    ) {}

    public function store(ReorderRequest $request): JsonResponse
    {
        if (! Helpers::get_business_settings('repeat_order_option')) {
            return $this->responseFormatter(config('response.forbidden_403'), errors: [
                ['code' => 'feature_disabled', 'message' => translate('Repeat order is currently disabled.')],
            ]);
        }

        return $this->refill($this->orderService->findReorderable([
            'order_id' => $request->input('order_id'),
            'user_id' => auth('api')->id(),
        ]));
    }

    public function storeFromReminder(MonthlyReorderRequest $request): JsonResponse
    {
        $reminder = $this->monthlyOrderReminderService->findOwned([
            'id' => $request->input('reminder_id'),
            'user_id' => auth('api')->id(),
        ]);

        return $this->refill($reminder ? $this->orderService->findReorderable(['order_id' => $reminder->order_id]) : null);
    }

    private function refill(mixed $order): JsonResponse
    {
        if (! $order) {
            return $this->responseFormatter(config('response.default_404'));
        }

        $userId = (int) auth('api')->id();
        $owner = ['user_id' => $userId, 'is_guest' => 0, 'module_id' => (int) $order->module_id];

        $this->cartService->clear(array_merge($owner, ['store_id' => (int) $order->store_id]));

        // BOGO lines are deliberately not carried over. A bundle is not a set of loose items --
        // re-adding its members individually would hand over the free one with no offer behind
        // it, and the offer that made it free may have ended, filled its cap, or been left by the
        // store since. The customer re-adds it from the offer screen, where the current terms
        // apply. Reported as skipped rather than dropped silently, so the response says what did
        // not come back.
        [$bundleLines, $plainLines] = collect($order->details)
            ->partition(fn ($detail) => ! empty($detail->bogo_group_id));

        $result = $this->processOrderDetailsToCart(
            details: $plainLines,
            userId: $userId,
            isGuest: 0,
            moduleId: (int) $order->module_id,
        );

        foreach ($bundleLines->where('is_free_item', 0) as $line) {
            $result['skipped'][] = [
                'status' => 'skipped',
                'item_id' => (int) $line->item_id,
                'item_name' => data_get(Helpers::decodeJsonToArray($line->item_details), 'name'),
                'code' => 'bogo_offer',
                'message' => translate('messages.Add this offer again from the offers screen'),
            ];
        }

        $result['cart_count'] = $this->cartService->count($owner);

        if (count($result['added']) === 0) {
            return $this->responseFormatter(
                config('response.forbidden_403'),
                new ReorderResultResource($result),
                [['code' => 'no_item_added', 'message' => translate('No item could be added to cart')]]
            );
        }

        return $this->responseFormatter(config('response.default_200'), new ReorderResultResource($result));
    }
}
