<?php

namespace App\Builder;

use App\Traits\Item\ItemStockTrait;
use App\CentralLogics\Helpers;
use App\Mail\RefundRequest;
use App\Models\AddOn;
use App\Models\Admin;
use App\Models\BusinessSetting;
use App\Models\Cart;
use App\Models\Item;
use App\Models\ItemCampaign;
use App\Models\Order;
use App\Models\OrderCancelReason;
use App\Models\OrderDetail;
use App\Models\Refund;
use App\Models\RefundReason;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\View;
use Modules\Builder\Contracts\CartProvider;
use Modules\Builder\Contracts\OrderActionsProvider as OrderActionsProviderContract;
use Modules\Builder\ValueObjects\StorefrontScope;
use App\Services\Order\OrderTransactionService;
use App\Support\Notification\SendNotification;
use App\Support\Storage\FileStorage;

class OrderActionsProvider implements OrderActionsProviderContract
{
    use ItemStockTrait;

    public function cancellationReasons(): array
    {
        return OrderCancelReason::query()
            ->where('status', 1)
            ->where('user_type', 'customer')
            ->orderBy('id')
            ->get(['id', 'reason'])
            ->map(fn ($r) => ['id' => (int) $r->id, 'reason' => (string) $r->reason])
            ->all();
    }

    public function refundReasons(): array
    {
        return RefundReason::query()
            ->where('status', 1)
            ->orderBy('id')
            ->get(['id', 'reason'])
            ->map(fn ($r) => ['id' => (int) $r->id, 'reason' => (string) $r->reason])
            ->all();
    }

    public function cancel(
        ?StorefrontScope $scope,
        int $orderId,
        ?int $customerId,
        ?string $guestPhone,
        ?string $reason = null,
        ?string $note = null,
    ): array {
        $order = $this->loadOrder($scope, $orderId, $customerId, $guestPhone);
        if (!$order) {
            return ['success' => false, 'error' => 'Order not found.'];
        }

        $allowed = ['pending', 'failed'];
        if (!in_array($order->order_status, $allowed, true)) {
            return ['success' => false, 'error' => 'This order can no longer be cancelled.'];
        }

        if (!$reason && !$note) {
            return ['success' => false, 'error' => 'Please provide a reason or a note for cancelling.'];
        }

        $hasStock = config('module.' . ($order->module->module_type ?? '') . '.stock');
        $hasFlash = $order->flash_admin_discount_amount > 0
                 && $order->flash_store_discount_amount > 0;

        try {
            DB::beginTransaction();

            if ($hasStock || $hasFlash) {
                foreach ($order->details as $detail) {
                    $item = $detail->campaign ?? $detail->item;
                    if ($hasStock) {
                        $variant = json_decode($detail->variation, true);
                        $variantType = !empty($variant) ? ($variant[0]['type'] ?? null) : null;
                        self::updateItemStock($item, -$detail->quantity, $variantType)?->save();
                    }
                    if ($hasFlash) {
                        self::updateFlashSaleStock($detail->item, $detail->quantity, true)?->save();
                    }
                }
            }

            if ((int) $order->is_guest === 0) {
                try { app(OrderTransactionService::class)->refundBeforeDelivered($order); } catch (\Throwable) { /* best effort */ }
            }

            $order->order_status         = 'canceled';
            $order->canceled             = now();
            $order->cancellation_reason  = $reason ?: null;
            $order->cancellation_note    = $note   ?: null;
            $order->canceled_by          = 'customer';
            $order->save();

            DB::commit();
        } catch (\Throwable) {
            DB::rollBack();
            return ['success' => false, 'error' => 'Could not cancel the order. Please try again.'];
        }

        try { SendNotification::sendOrderNotifications($order); } catch (\Throwable) { /* best effort */ }

        return ['success' => true, 'message' => 'Order cancelled.'];
    }

    public function switchToCod(
        ?StorefrontScope $scope,
        int $orderId,
        ?int $customerId,
        ?string $guestPhone,
    ): array {
        $order = $this->loadOrder($scope, $orderId, $customerId, $guestPhone);
        if (!$order) {
            return ['success' => false, 'error' => 'Order not found.'];
        }

        if ($order->payment_method === 'cash_on_delivery') {
            return ['success' => false, 'error' => 'This order is already cash on delivery.'];
        }

        // Only an unpaid order that has not moved on: this resets the status to pending and deletes
        // the offline-payment proof, which on a paid, delivered or cancelled order rewound it.
        if ($order->payment_status === 'paid' || !in_array($order->order_status, ['pending', 'failed'], true)) {
            return ['success' => false, 'error' => 'This order can no longer be switched to cash on delivery.'];
        }

        try {
            DB::beginTransaction();

            $order->offline_payments()?->delete();

            if ($order->payment_method === 'partial_payment') {
                $order->payments()
                    ->where('payment_status', 'unpaid')
                    ->update(['payment_method' => 'cash_on_delivery']);
            }

            if ($order->order_status !== 'pending') {
                $order->order_status = 'pending';
            }
            $order->payment_method = 'cash_on_delivery';
            $order->save();

            DB::commit();
        } catch (\Throwable) {
            DB::rollBack();
            return ['success' => false, 'error' => 'Could not switch payment method. Please try again.'];
        }

        try { SendNotification::sendOrderNotifications($order); } catch (\Throwable) { /* best effort */ }

        return ['success' => true, 'message' => 'Switched to Cash on Delivery.'];
    }

    public function repay(
        ?StorefrontScope $scope,
        int $orderId,
        int $customerId,
        string $paymentMethod,
    ): array {
        $order = $this->loadOrder($scope, $orderId, $customerId, null);
        if (!$order) {
            return ['success' => false, 'error' => 'Order not found.'];
        }

        if ($order->payment_status === 'paid') {
            return ['success' => false, 'error' => 'This order has already been paid.'];
        }

        $order->payment_method = 'digital_payment';
        $order->save();

        $callback = url(route(
            'storefront.payment_callback',
            ['orderId' => $orderId],
            false,
        ));

        try {
            $url = route('payment-mobile', [
                'order_id'         => $orderId,
                'customer_id'      => $customerId,
                'payment_method'   => $paymentMethod,
                'payment_platform' => 'web',
                'callback'         => $callback,
            ]);
        } catch (\Throwable) {
            return ['success' => false, 'error' => 'Could not build the payment URL.'];
        }

        return ['success' => true, 'paymentRedirect' => $url ?: null];
    }

    public function requestRefund(
        ?StorefrontScope $scope,
        int $orderId,
        int $customerId,
        string $customerReason,
        ?string $customerNote,
        array $imageFiles,
    ): array {
        if ((int) (\App\Models\BusinessSetting::query()->where('key', 'refund_active_status')->value('value') ?? 0) !== 1) {
            return ['success' => false, 'error' => 'Refund requests are not currently accepted.'];
        }

        $order = $this->loadOrder($scope, $orderId, $customerId, null);
        if (!$order) {
            return ['success' => false, 'error' => 'Order not found.'];
        }

        if ($order->order_status !== 'delivered' || $order->payment_status !== 'paid') {
            return ['success' => false, 'error' => 'You can only request a refund on a delivered, paid order.'];
        }

        $imagePaths = [];
        foreach ($imageFiles as $file) {
            try {
                $path = FileStorage::upload('refund/', $file);
                $imagePaths[] = ['img' => $path, 'storage' => FileStorage::getDisk()];
            } catch (\Throwable $exception) {
                Log::warning('builder.order_actions_provider.request_refund_failed', [
                    'error' => $exception->getMessage(),
                    'file' => $exception->getFile().':'.$exception->getLine(),
                ]);
            }
        }

        // No delivery-related charge is refunded — base/surge (delivery_charge) and the
        // express/slightly-delay premium (delivery_type_charge) alike. Same fix as
        // RefundService::create() and OrderTransactionsTrait::refundOrderTransaction().
        $refundAmount = round(
            $order->order_amount - $order->delivery_charge - ($order->delivery_type_charge ?? 0) - ($order->dm_tips ?? 0),
            (int) (config('round_up_to_digit') ?? 2),
        );

        try {
            DB::beginTransaction();

            $refund = new Refund();
            $refund->order_id        = $order->id;
            $refund->user_id         = $order->user_id;
            $refund->order_status    = $order->order_status;
            $refund->refund_status   = 'pending';
            $refund->refund_method   = 'wallet';
            $refund->customer_reason = $customerReason;
            $refund->customer_note   = $customerNote;
            $refund->refund_amount   = $refundAmount;
            $refund->image           = json_encode($imagePaths);
            $refund->save();

            $order->order_status     = 'refund_requested';
            $order->refund_requested = now();
            $order->save();

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::warning('Refund request failed', [
                'order_id' => $order->id,
                'error'    => $e->getMessage(),
            ]);
            return ['success' => false, 'error' => 'Could not file the refund request. Please try again.'];
        }

        try {
            $admin = Admin::query()->where('role_id', 1)->first();
            $mailStatus = SendNotification::mailTemplateEnabled('refund_request_mail_status_admin');
            if (config('mail.status')
                && $admin?->email
                && $mailStatus
                && SendNotification::channelEnabled('admin', 'order_refund_request', 'mail_status')
            ) {
                SendNotification::mail($admin->getRawOriginal('email'), new RefundRequest($order->id));
            }
        } catch (\Throwable) { /* swallow — refund itself is committed */ }

        return ['success' => true, 'message' => 'Refund request submitted.'];
    }

    private function loadOrder(?StorefrontScope $scope, int $orderId, ?int $customerId, ?string $guestPhone): ?Order
    {
        $normalizedPhone = $guestPhone
            ? (str_starts_with($guestPhone, '+') ? $guestPhone : '+' . ltrim($guestPhone, '+ '))
            : null;

        return Order::query()
            // `parcel_category` and the two tiers because the invoice prints all three for a
            // parcel order; they were being lazy-loaded off the rendered blade.
            ->with(['details', 'module:id,module_type', 'store', 'parcel_category', 'weight', 'dimension'])
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
    }

    /* ─── invoice ─────────────────────────────────────────── */

    public function downloadInvoice(?StorefrontScope $scope, int $orderId, ?int $customerId, ?string $guestPhone = null): array
    {
        $order = $this->loadOrder($scope, $orderId, $customerId, $guestPhone);
        if (!$order) {
            return ['success' => false, 'error' => 'Order not found.'];
        }

        try {
            $BusinessData = BusinessSetting::query()
                ->whereIn('key', ['footer_text', 'email_address'])
                ->pluck('value', 'key');
            $logo = BusinessSetting::query()->where('key', 'logo')->first();

            $mpdfView = View::make('storefront-order-invoice', compact('order', 'BusinessData', 'logo'));
            Helpers::gen_mpdf(view: $mpdfView, file_prefix: 'OrderInvoice', file_postfix: (string) $order->id);
        } catch (\Throwable $e) {
            Log::warning('Invoice download failed', [
                'order_id' => $order->id,
                'error'    => $e->getMessage(),
            ]);
            return ['success' => false, 'error' => 'Could not generate the invoice. Please try again.'];
        }

        return ['success' => true];
    }

    public function downloadDigitalProduct(
        ?StorefrontScope $scope,
        int $orderDetailId,
        ?int $customerId,
        ?string $guestPhone,
    ): array {
        return ['success' => false, 'error' => 'Digital downloads are not available.'];
    }

    /* ─── reorder ─────────────────────────────────────────── */

    public function reorder(?StorefrontScope $scope, int $orderId, int $customerId): array
    {
        Log::info('Reorder: start', [
            'orderId'      => $orderId,
            'customerId'   => $customerId,
            'scope'        => $scope ? [
                'subTenantId' => $scope->subTenantId,
                'moduleId'    => $scope->moduleId,
                'tenantId'    => $scope->tenantId,
            ] : null,
        ]);

        $order = Order::query()
            ->with(['details.item', 'details.campaign', 'module:id,module_type', 'store'])
            ->where('id', $orderId)
            ->where('user_id', $customerId)
            ->where('is_guest', 0)
            ->when(
                $scope?->subTenantId !== null,
                fn ($q) => $q->where('store_id', $scope->subTenantId),
            )
            ->first();

        if (!$order) {
            Log::info('Reorder: order not found', ['orderId' => $orderId, 'customerId' => $customerId]);
            return ['success' => false, 'errors' => ['Order not found.']];
        }
        Log::info('Reorder: order loaded', [
            'orderId'   => $order->id,
            'storeId'   => $order->store_id,
            'moduleId'  => $order->module_id,
            'orderType' => $order->order_type,
            'detailsCount' => $order->details?->count() ?? 0,
        ]);

        if ((string) ($order->order_type ?? '') === 'parcel') {
            return ['success' => false, 'errors' => ['Parcel orders can\'t be reordered.']];
        }

        $details = $order->details ?? collect();
        if ($details->isEmpty()) {
            return ['success' => false, 'errors' => ['This order has no items to re-add.']];
        }

        $activeStoreId  = $scope?->subTenantId;
        $activeModuleId = $scope?->moduleId;
        if (!$activeStoreId || !$activeModuleId) {
            Log::info('Reorder: missing scope', [
                'activeStoreId'  => $activeStoreId,
                'activeModuleId' => $activeModuleId,
            ]);
            return ['success' => false, 'errors' => ['Open the storefront first, then reorder.']];
        }
        if ((int) $order->store_id !== (int) $activeStoreId) {
            Log::info('Reorder: store mismatch', [
                'orderStoreId'  => $order->store_id,
                'activeStoreId' => $activeStoreId,
            ]);
            return ['success' => false, 'errors' => ['This order is from a different store. Switch stores to reorder it.']];
        }
        $moduleType     = (string) ($order->module?->module_type ?? '');
        $hasStock       = (bool) config('module.' . $moduleType . '.stock');
        Log::info('Reorder: resolved context', [
            'activeStoreId'  => $activeStoreId,
            'activeModuleId' => $activeModuleId,
            'moduleType'     => $moduleType,
            'hasStock'       => $hasStock,
        ]);

        $cart = \app(CartProvider::class);

        $errors   = [];
        $payloads = [];
        foreach ($details as $d) {
            [$ok, $payload, $err] = $this->planReorderLine($d, $activeStoreId, $activeModuleId, $moduleType, $hasStock, $customerId);
            Log::info('Reorder: planLine', [
                'detailId'      => $d->id,
                'itemId'        => $d->item_id,
                'campaignId'    => $d->item_campaign_id,
                'quantity'      => $d->quantity,
                'ok'            => $ok,
                'error'         => $err,
                'payloadPrice'  => $payload['price'] ?? null,
            ]);
            if (!$ok) {
                $errors[] = $err;
                continue;
            }
            $payloads[] = $payload;
        }

        if ($errors) {
            Log::info('Reorder: pre-flight rejected', ['errors' => $errors]);
            return ['success' => false, 'errors' => $errors];
        }

        if (empty($payloads)) {
            Log::warning('Reorder: empty payloads after clean pre-flight', [
                'orderId'      => $order->id,
                'detailsCount' => $details->count(),
            ]);
            return ['success' => false, 'errors' => ['Nothing to add — please contact support.']];
        }

        try {
            DB::beginTransaction();
            foreach ($payloads as $p) {
                Log::info('Reorder: cart->add', ['payload' => $p]);
                $cart->add($p);
            }
            DB::commit();
            Log::info('Reorder: committed', [
                'orderId' => $order->id,
                'rows'    => count($payloads),
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            DB::rollBack();
            $messages = [];
            foreach ($e->errors() as $field => $fieldMessages) {
                foreach ((array) $fieldMessages as $m) {
                    $messages[] = (string) $m;
                }
            }
            return [
                'success' => false,
                'errors'  => $messages ?: ['Could not add items to cart.'],
            ];
        } catch (\Throwable $e) {
            DB::rollBack();
            Log::warning('Reorder write failed', [
                'order_id' => $order->id,
                'error'    => $e->getMessage(),
                'trace'    => $e->getTraceAsString(),
            ]);
            return ['success' => false, 'errors' => ['Could not add items to cart. Please try again.']];
        }

        $count = count($payloads);
        $orderId = (int) $order->id;
        return [
            'success' => true,
            'count'   => $count,
            'message' => $count === 1
                ? "Added 1 item from order #{$orderId} to your cart."
                : "Added {$count} items from order #{$orderId} to your cart.",
        ];
    }

    private function planReorderLine(
        OrderDetail $d,
        int $activeStoreId,
        int $activeModuleId,
        string $moduleType,
        bool $hasStock,
        int $customerId,
    ): array {
        $isCampaign = !empty($d->item_campaign_id);
        $item = $isCampaign ? ($d->campaign ?? null) : ($d->item ?? null);

        $snapshotName = $this->snapshotItemName($d);

        if (!$item) {
            return [false, null, "{$snapshotName} is no longer available."];
        }

        if ((int) $item->store_id !== $activeStoreId) {
            return [false, null, "{$snapshotName} is from a different store."];
        }
        if ((int) ($item->module_id ?? 0) !== $activeModuleId) {
            return [false, null, "{$snapshotName} isn't available in this section."];
        }

        if ((int) $item->status !== 1) {
            return [false, null, "{$snapshotName} is currently unavailable."];
        }

        if (!$isCampaign && (int) ($item->is_approved ?? 1) !== 1) {
            return [false, null, "{$snapshotName} is currently unavailable."];
        }

        if ($isCampaign) {
            $end = $item->end_date ? $item->end_date->format('Y-m-d') : null;
            if ($end && $end < date('Y-m-d')) {
                return [false, null, "The offer for {$snapshotName} has ended."];
            }
        }

        $now = date('H:i:s');
        $startTime = $item->available_time_starts ?? null;
        $endTime   = $item->available_time_ends ?? null;
        if ($startTime && $endTime && ($now < (string) $startTime || $now > (string) $endTime)) {
            return [false, null, "{$snapshotName} isn't available right now."];
        }

        $variation = $this->decodeJsonArray($d->variation);
        if ($moduleType === 'food') {
            $variation = $this->normalizeFoodVariation($variation);
        }
        $addOnIds  = array_values(array_map('intval', $this->decodeJsonArray($d->add_on_ids)));
        $addOnQtys = array_values(array_map('intval', $this->decodeJsonArray($d->add_on_qtys)));
        $qty       = max(1, (int) $d->quantity);

        $variationError = $this->validateVariation($item, $variation, $moduleType);
        if ($variationError) {
            return [false, null, str_replace('{name}', $snapshotName, $variationError)];
        }

        $existingCartQty = $this->existingCartLineQty(
            $item,
            $variation,
            $addOnIds,
            $addOnQtys,
            $customerId,
            $activeModuleId,
        );

        if ($hasStock) {
            $stockError = $this->validateStock($item, $variation, $qty, $snapshotName, $existingCartQty, $moduleType);
            if ($stockError) {
                return [false, null, $stockError];
            }
        }

        if (!empty($addOnIds)) {
            $liveAddons = AddOn::query()
                ->whereIn('id', $addOnIds)
                ->where('status', 1)
                ->get(['id', 'name', 'price'])
                ->keyBy('id');
            foreach ($addOnIds as $aid) {
                if (!$liveAddons->has($aid)) {
                    return [false, null, "An add-on for {$snapshotName} is no longer available."];
                }
            }
        }

        $maxCartQty = (int) ($item->maximum_cart_quantity ?? 0);
        if ($maxCartQty > 0 && ($qty + $existingCartQty) > $maxCartQty) {
            return [false, null, $existingCartQty > 0
                ? "You can add at most {$maxCartQty} of {$snapshotName} per order (you already have {$existingCartQty} in your cart)."
                : "You can add at most {$maxCartQty} of {$snapshotName} per order."];
        }

        $price = $this->liveLinePrice($item, $variation, $addOnIds, $addOnQtys, $qty, $moduleType);

        $payload = [
            'item_id'     => (int) $item->id,
            'model'       => $isCampaign ? 'ItemCampaign' : 'Item',
            'price'       => $price,
            'quantity'    => $qty,
            'variation'   => $variation,
            'add_on_ids'  => $addOnIds,
            'add_on_qtys' => $addOnQtys,
        ];
        return [true, $payload, null];
    }

    private function validateVariation($item, array $variation, string $moduleType): ?string
    {
        if (empty($variation)) return null;

        if ($moduleType === 'food') {
            $live = $this->decodeJsonArray($item->food_variations ?? []);
            foreach ($variation as $sel) {
                $name   = (string) ($sel['name'] ?? '');
                $labels = $this->coerceLabels($sel['values']['label'] ?? null);
                if ($name === '') continue;

                $group = collect($live)->firstWhere('name', $name);
                if (!$group) {
                    return "An option you previously chose for {name} is no longer offered.";
                }
                $liveLabels = collect($group['values'] ?? [])->pluck('label')->map(fn ($l) => (string) $l)->all();
                foreach ($labels as $lbl) {
                    if (!in_array($lbl, $liveLabels, true)) {
                        return "An option you previously chose for {name} is no longer offered.";
                    }
                }
            }
            return null;
        }

        $live = $this->decodeJsonArray($item->variations ?? []);
        $liveTypes = array_column($live, 'type');
        foreach ($variation as $sel) {
            $type = (string) ($sel['type'] ?? '');
            if ($type === '') continue;
            if (!in_array($type, $liveTypes, true)) {
                return "A variation you previously chose for {name} is no longer offered.";
            }
        }
        return null;
    }

    private function validateStock(
        $item,
        array $variation,
        int $qty,
        string $name,
        int $existingCartQty = 0,
        string $moduleType = '',
    ): ?string {
        $needed = $qty + $existingCartQty;

        if ($moduleType !== 'food' && !empty($variation[0]['type'])) {
            $variant = (string) $variation[0]['type'];
            $live = $this->decodeJsonArray($item->variations ?? []);
            foreach ($live as $v) {
                if (($v['type'] ?? null) === $variant) {
                    $available = (int) ($v['stock'] ?? 0);
                    if ($available < $needed) {
                        return $this->stockMessage($name, $available, $existingCartQty);
                    }
                    return null;
                }
            }
            return null;
        }

        $stock = (int) ($item->stock ?? 0);
        if ($stock < $needed) {
            return $this->stockMessage($name, $stock, $existingCartQty);
        }
        return null;
    }

    private function stockMessage(string $name, int $available, int $existingCartQty): string
    {
        if ($available <= 0) {
            return "{$name} is out of stock.";
        }
        if ($existingCartQty > 0) {
            return "Only {$available} of {$name} left in stock (you already have {$existingCartQty} in your cart).";
        }
        return "Only {$available} of {$name} left in stock.";
    }

    private function existingCartLineQty(
        $item,
        array $variation,
        array $addOnIds,
        array $addOnQtys,
        int $customerId,
        int $moduleId,
    ): int {
        $itemType = $item instanceof ItemCampaign ? ItemCampaign::class : Item::class;
        $needle = $this->variationMatchKey($variation);
        $wantAddOnIds  = array_values(array_map('intval', $addOnIds));
        $wantAddOnQtys = array_values(array_map('intval', $addOnQtys));

        $rows = Cart::query()
            ->where('user_id', $customerId)
            ->where('is_guest', 0)
            ->where('module_id', $moduleId)
            ->where('item_id', $item->id)
            ->whereIn('item_type', [$itemType, class_basename($itemType)])
            ->get(['variation', 'add_on_ids', 'add_on_qtys', 'quantity']);

        $total = 0;
        foreach ($rows as $row) {
            $rowVar       = $this->decodeJsonArray($row->variation);
            $rowAddOnIds  = array_values(array_map('intval', $this->decodeJsonArray($row->add_on_ids)));
            $rowAddOnQtys = array_values(array_map('intval', $this->decodeJsonArray($row->add_on_qtys)));

            if ($this->variationMatchKey($rowVar) === $needle
                && $rowAddOnIds  == $wantAddOnIds
                && $rowAddOnQtys == $wantAddOnQtys) {
                $total += (int) $row->quantity;
            }
        }
        return $total;
    }

    private function variationMatchKey(array $variation): string
    {
        $normalized = array_map(static function ($entry) {
            if (!is_array($entry)) return $entry;
            $copy = $entry;
            unset($copy['price'], $copy['stock'], $copy['oldPrice'], $copy['discountPercent'], $copy['inStock']);
            return $copy;
        }, $variation);
        return json_encode($normalized) ?: '';
    }

    private function liveLinePrice($item, array $variation, array $addOnIds, array $addOnQtys, int $qty, string $moduleType): float
    {
        $base = (float) ($item->price ?? 0);

        if (!empty($variation)) {
            if ($moduleType === 'food') {
                $live = $this->decodeJsonArray($item->food_variations ?? []);
                foreach ($variation as $sel) {
                    $name   = (string) ($sel['name'] ?? '');
                    $labels = $this->coerceLabels($sel['values']['label'] ?? null);
                    if ($name === '') continue;
                    foreach ($live as $group) {
                        if (($group['name'] ?? null) !== $name) continue;
                        foreach (($group['values'] ?? []) as $val) {
                            if (in_array((string) ($val['label'] ?? ''), $labels, true)) {
                                $base += (float) ($val['optionPrice'] ?? 0);
                            }
                        }
                    }
                }
            } else {
                $live = $this->decodeJsonArray($item->variations ?? []);
                $type = (string) ($variation[0]['type'] ?? '');
                foreach ($live as $v) {
                    if (($v['type'] ?? null) === $type) {
                        $base = (float) ($v['price'] ?? $base);
                        break;
                    }
                }
            }
        }

        $addonExtra = 0.0;
        if (!empty($addOnIds)) {
            $liveAddons = AddOn::query()
                ->whereIn('id', $addOnIds)
                ->where('status', 1)
                ->get(['id', 'price'])
                ->keyBy('id');
            foreach ($addOnIds as $i => $aid) {
                $aq = (int) ($addOnQtys[$i] ?? 0);
                if (isset($liveAddons[$aid])) {
                    $addonExtra += (float) $liveAddons[$aid]->price * $aq;
                }
            }
        }

        return round($base * $qty + $addonExtra, (int) (config('round_up_to_digit') ?? 2));
    }

    private function normalizeFoodVariation(array $orderVariation): array
    {
        $out = [];
        foreach ($orderVariation as $group) {
            if (!is_array($group)) continue;

            $labels = [];
            $values = $group['values'] ?? null;
            if (is_array($values)) {
                if (array_key_exists('label', $values)) {
                    $labels = is_array($values['label']) ? $values['label'] : [$values['label']];
                } else {
                    foreach ($values as $v) {
                        if (is_array($v) && isset($v['label'])) {
                            $labels[] = $v['label'];
                        }
                    }
                }
            }

            $normalized = $group;
            $normalized['values'] = [
                'label' => array_values(array_map(static fn ($x) => (string) $x, $labels)),
            ];
            $out[] = $normalized;
        }
        return $out;
    }

    private function snapshotItemName(OrderDetail $d): string
    {
        $details = $this->decodeJsonArray($d->item_details ?? null);
        $name = $details['name'] ?? null;
        return is_string($name) && $name !== '' ? $name : 'Item';
    }

    private function coerceLabels($raw): array
    {
        if (is_array($raw)) {
            return array_values(array_map(static fn ($x) => (string) $x, $raw));
        }
        if (is_string($raw) && $raw !== '') {
            return [$raw];
        }
        return [];
    }

    private function decodeJsonArray($value): array
    {
        if (is_array($value)) return $value;
        if (is_string($value) && $value !== '') {
            $decoded = json_decode($value, true);
            return is_array($decoded) ? $decoded : [];
        }
        return [];
    }
}
