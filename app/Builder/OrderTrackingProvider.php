<?php

namespace App\Builder;

use App\Models\Order;
use Modules\Builder\Contracts\OrderTrackingProvider as OrderTrackingProviderContract;
use Modules\Builder\ValueObjects\StorefrontScope;

class OrderTrackingProvider implements OrderTrackingProviderContract
{
    public function __construct(private OrderProvider $orderFormatter)
    {
    }

    public function track(
        ?StorefrontScope $scope,
        int $orderId,
        ?string $contactNumber,
        ?int $customerId,
    ): ?array {
        $normalizedPhone = $contactNumber
            ? (str_starts_with($contactNumber, '+') ? $contactNumber : '+' . ltrim($contactNumber))
            : null;

        $order = Order::query()
            ->with([
                'details', 'store', 'customer', 'module:id,module_type',
                'delivery_man.rating', 'delivery_man.last_location',
            ])
            ->where('id', $orderId)
            ->when(
                $customerId,
                fn ($q) => $q->where('user_id', $customerId)->where('is_guest', 0),
                fn ($q) => $q
                    ->where('is_guest', 1)
                    ->whereJsonContains('delivery_address->contact_person_number', $normalizedPhone),
            )
            ->when(
                $scope?->subTenantId !== null,
                fn ($q) => $q->where('store_id', $scope->subTenantId),
            )
            ->first();

        return $order ? $this->orderFormatter->formatOrder($order) : null;
    }
}
