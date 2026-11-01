<?php

namespace App\Support\Notification;

use App\Support\Settings\BusinessRules;
use App\Mail\OrderVerificationMail;
use App\Mail\PlaceOrder;
use App\Models\VendorEmployee;
use Illuminate\Support\Facades\Log;

class OrderNotifier
{
    public static function notify($order): bool
    {
        $storePushEnabled = self::attempt('store_gate', $order, fn () => NotificationGate::allows(
            'store',
            'store_order_notification',
            'push_notification_status',
            $order?->store?->id,
        ));

        $status = ($order?->order_status == 'delivered' && $order?->delivery_man) ? 'delivery_boy_delivered' : (string) $order?->order_status;

        $steps = [
            'admin_new_order' => fn () => self::adminNewOrder($order),
            'customer_order_status' => fn () => self::customerOrderStatus($order, $status),
            'vendor_order_picked_up' => fn () => self::vendorOrderPickedUp($order, $status, $storePushEnabled),
            'vendor_self_delivery_new_order' => fn () => self::vendorSelfDeliveryNewOrder($order, $storePushEnabled),
            'delivery_man_new_order' => fn () => self::deliveryManNewOrder($order, $storePushEnabled),
            'delivery_man_parcel' => fn () => self::deliveryManParcel($order),
            'vendor_store_confirms_order' => fn () => self::vendorStoreConfirmsOrder($order, $storePushEnabled),
            'vendor_take_away_or_prepaid_order' => fn () => self::vendorTakeAwayOrPrepaidOrder($order, $storePushEnabled),
            'delivery_man_self_delivery_confirmed' => fn () => self::deliveryManSelfDeliveryConfirmed($order, $storePushEnabled),
            'vendor_confirmed_order' => fn () => self::vendorConfirmedOrder($order, $storePushEnabled),
            'delivery_man_confirmed_delivery' => fn () => self::deliveryManConfirmedDelivery($order, $storePushEnabled),
            'delivery_man_assigned_order' => fn () => self::deliveryManAssignedOrder($order),
            'mail_customer' => fn () => self::mailCustomer($order),
        ];

        $failed = 0;

        foreach ($steps as $step => $run) {
            if (self::attempt($step, $order, $run) === null) {
                $failed++;
            }
        }

        return $failed === 0;
    }

    public static function mailCustomer($order): void
    {
        if ($order->order_status == 'confirmed' && $order->payment_method != 'cash_on_delivery' && $order->is_guest == 0
            && SendNotification::canSendMail('place_order_mail_status_user', 'customer', 'customer_order_notification')) {
            SendNotification::mail($order->customer?->getRawOriginal('email'), new PlaceOrder($order->id));
        }

        if ($order->order_status == 'pending' && BusinessRules::deliveryVerificationEnabled() && $order->is_guest == 0
            && SendNotification::canSendMail('order_verification_mail_status_user', 'customer', 'customer_delivery_verification')) {
            SendNotification::mail($order->customer?->getRawOriginal('email'), new OrderVerificationMail($order->otp, $order->customer->f_name));
        }
    }

    private static function attempt(string $step, $order, callable $run): mixed
    {
        try {
            return $run() ?? true;
        } catch (\Throwable $exception) {
            Log::channel(NotificationConfig::logChannel())->error('order_notification.step_failed', [
                'step' => $step,
                'order_id' => $order?->id,
                'order_status' => $order?->order_status,
                'error' => $exception->getMessage(),
                'file' => $exception->getFile().':'.$exception->getLine(),
            ]);

            return null;
        }
    }

    public static function notifyStoreEmployees($order, array $data): void
    {
        $tokens = VendorEmployee::where('store_id', $order->store->id)
            ->whereNotNull('firebase_token')
            ->pluck('firebase_token')
            ->all();

        SendNotification::pushToMany($tokens, $data, context: [
            'batch' => 'store-employees',
            'store_id' => $order->store->id,
        ]);
    }

    private static function adminNewOrder($order): void
    {
        $isNewOrder = (in_array($order->payment_method, ['cash_on_delivery', 'offline_payment']) && $order->order_status == 'pending')
            || (! in_array($order->payment_method, ['cash_on_delivery', 'offline_payment']) && $order->order_status == 'confirmed');

        if (! $isNewOrder) {
            return;
        }

        SendNotification::pushToTopic(
            NotificationMessages::newOrder($order, ['zone_id' => $order->zone_id]),
            'admin_message',
            'order_request',
            NotificationLinks::adminOrders(),
        );
    }

    private static function customerOrderStatus($order, string $status): void
    {
        if ($order->is_guest) {
            $customer_details = json_decode((string) $order['delivery_address'], true);
            $value = NotificationText::forOrderStatus($order, $status, (string) data_get($customer_details, 'contact_person_name'));
            $user_fcm = $order->guest?->fcm_token;
        } else {
            $value = NotificationText::forOrderStatus($order, $status, locale: $order->customer ?
                $order->customer->current_language_key : 'en');
            $user_fcm = $order?->customer?->cm_firebase_token;
        }

        if (NotificationGate::allows('customer', 'customer_order_notification', 'push_notification_status') && $value && $user_fcm) {
            SendNotification::pushToCustomer($order->user_id, $user_fcm, NotificationMessages::orderStatus($order, $value), isGuest: (bool) $order->is_guest);
        }
    }

    private static function vendorOrderPickedUp($order, string $status, mixed $storePushEnabled): void
    {
        if ($status != 'picked_up') {
            return;
        }

        self::pushToStore($order, NotificationMessages::orderStatus($order, $order->id.' '.translate('Order is picked up')), $storePushEnabled);
    }

    private static function vendorSelfDeliveryNewOrder($order, mixed $storePushEnabled): void
    {
        if (! self::awaitingDeliverymanConfirmation($order) || ! self::storeHandlesOwnDelivery($order, $storePushEnabled)) {
            return;
        }

        self::pushToStore($order, NotificationMessages::newOrder($order), $storePushEnabled, NotificationLinks::vendorOrders());
    }

    private static function deliveryManNewOrder($order, mixed $storePushEnabled): void
    {
        if (! self::awaitingDeliverymanConfirmation($order) || self::storeHandlesOwnDelivery($order, $storePushEnabled)) {
            return;
        }

        self::pushToZoneRiders($order, self::newOrderAlert($order, $order->order_type));
    }

    private static function deliveryManParcel($order): void
    {
        if ($order->order_type != 'parcel' || ! in_array($order->order_status, ['pending', 'confirmed'])) {
            return;
        }

        self::pushToZoneRiders($order, self::newOrderAlert($order, 'parcel_order'));
    }

    private static function vendorStoreConfirmsOrder($order, mixed $storePushEnabled): void
    {
        if (! ($order->order_type == 'delivery' && ! $order->scheduled && $order->order_status == 'pending'
            && $order->payment_method == 'cash_on_delivery' && BusinessRules::storeConfirmsOrder())) {
            return;
        }

        self::pushToStore($order, NotificationMessages::newOrder($order), $storePushEnabled, NotificationLinks::vendorOrders());
    }

    private static function vendorTakeAwayOrPrepaidOrder($order, mixed $storePushEnabled): void
    {
        if ($order->scheduled) {
            return;
        }

        $applies = ($order->order_type == 'take_away' && $order->order_status == 'pending')
            || ($order->payment_method != 'cash_on_delivery' && $order->order_status == 'confirmed');

        if (! $applies) {
            return;
        }

        $data = NotificationMessages::make(
            translate('Order notification'),
            translate('New order alert, confirm to proceed'),
            ['order_id' => $order->id, 'type' => 'new_order'],
        );

        self::pushToStore($order, $data, $storePushEnabled, NotificationLinks::vendorOrders());
    }

    private static function deliveryManSelfDeliveryConfirmed($order, mixed $storePushEnabled): void
    {
        if (! self::confirmedAwaitingDeliveryman($order) || ! self::storeHandlesOwnDelivery($order, $storePushEnabled)) {
            return;
        }

        SendNotification::pushToTopic(self::newOrderAlert($order, $order->order_type), 'restaurant_dm_'.$order->store_id, 'new_order', null);
    }

    private static function vendorConfirmedOrder($order, mixed $storePushEnabled): void
    {
        if (! self::confirmedAwaitingDeliveryman($order) || self::storeHandlesOwnDelivery($order, $storePushEnabled)) {
            return;
        }

        self::pushToStore($order, NotificationMessages::newOrder($order), $storePushEnabled, NotificationLinks::vendorOrders());
    }

    private static function deliveryManConfirmedDelivery($order, mixed $storePushEnabled): void
    {
        if (! ($order->order_type == 'delivery' && ! $order->scheduled && $order->order_status == 'confirmed'
            && ($order->payment_method != 'cash_on_delivery' || BusinessRules::storeConfirmsOrder()))) {
            return;
        }

        $data = self::newOrderAlert($order, $order->order_type);

        if (self::storeHandlesOwnDelivery($order, $storePushEnabled)) {
            SendNotification::pushToTopic($data, 'restaurant_dm_'.$order->store_id, 'order_request', null);

            return;
        }

        self::pushToZoneRiders($order, $data);
    }

    private static function deliveryManAssignedOrder($order): void
    {
        if (! in_array($order->order_status, ['processing', 'handover']) || ! $order->delivery_man || $order->delivery_man->status != 1
            || ! NotificationGate::allows('deliveryman', 'deliveryman_order_notification', 'push_notification_status')) {
            return;
        }

        $data = NotificationMessages::orderStatus(
            $order,
            $order->order_status == 'processing' ? translate('Order is processing') : translate('messages.Ready for delivery'),
        );

        SendNotification::pushToDeliveryMan($order->delivery_man->id, $order->delivery_man->fcm_token, $data);
    }

    private static function pushToStore($order, array $data, mixed $storePushEnabled, ?string $webPushLink = null): void
    {
        if (! $order->store || ! $order->store->vendor || ! $storePushEnabled) {
            return;
        }

        if ($webPushLink === null) {
            SendNotification::pushToVendor($order->store->vendor_id, $order->store->vendor->firebase_token, $data);
        } else {
            SendNotification::notifyVendor(
                vendorId: $order->store->vendor_id,
                token: $order->store->vendor->firebase_token,
                storeId: $order->store_id,
                data: $data,
                webPushLink: $webPushLink,
            );
        }

        self::notifyStoreEmployees($order, $data);
    }

    private static function pushToZoneRiders($order, array $data): void
    {
        if (! $order->zone || ! NotificationGate::allows('deliveryman', 'deliveryman_order_notification', 'push_notification_status')) {
            return;
        }

        if ($order->dm_vehicle_id) {
            SendNotification::pushToTopic($data, 'delivery_man_'.$order->zone_id.'_'.$order->dm_vehicle_id, 'order_request');
        }

        SendNotification::pushToTopic($data, $order->zone->deliveryman_wise_topic, 'order_request');
    }

    private static function newOrderAlert($order, mixed $orderType): array
    {
        return NotificationMessages::make(
            translate('Order notification'),
            translate('New order alert, confirm to proceed'),
            ['order_id' => $order->id, 'module_id' => $order->module_id, 'order_type' => $orderType],
        );
    }

    private static function awaitingDeliverymanConfirmation($order): bool
    {
        return $order->order_type == 'delivery' && ! $order->scheduled && $order->order_status == 'pending'
            && $order->payment_method == 'cash_on_delivery' && BusinessRules::deliverymanConfirmsOrder();
    }

    private static function confirmedAwaitingDeliveryman($order): bool
    {
        return $order->order_status == 'confirmed' && $order->order_type != 'take_away'
            && BusinessRules::deliverymanConfirmsOrder() && $order->payment_method == 'cash_on_delivery';
    }

    private static function storeHandlesOwnDelivery($order, mixed $storePushEnabled): bool
    {
        return (bool) ($order->store?->sub_self_delivery && $storePushEnabled);
    }
}
