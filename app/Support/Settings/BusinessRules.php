<?php

namespace App\Support\Settings;

use App\Services\System\BusinessSettingService;

class BusinessRules
{
    public static function orderConfirmationModel(): string
    {
        return (string) (self::value('order_confirmation_model') ?? 'deliveryman');
    }

    public static function storeConfirmsOrder(): bool
    {
        return self::orderConfirmationModel() === 'store';
    }

    public static function deliverymanConfirmsOrder(): bool
    {
        return self::orderConfirmationModel() === 'deliveryman';
    }

    public static function deliveryVerificationEnabled(): bool
    {
        return (int) (self::value('order_delivery_verification') ?? 0) === 1;
    }

    public static function dmMaximumOrders(): int
    {
        return (int) (self::value('dm_maximum_orders') ?? 1);
    }

    public static function canceledByStore(): bool
    {
        return (bool) (self::value('canceled_by_store') ?? false);
    }

    public static function canceledByDeliveryman(): bool
    {
        return (bool) (self::value('canceled_by_deliveryman') ?? false);
    }

    public static function vegNonVegEnabled(): bool
    {
        return (bool) (self::value('toggle_veg_non_veg') ?? false);
    }

    private static function value(string $key): mixed
    {
        return app(BusinessSettingService::class)->value($key, false);
    }
}
