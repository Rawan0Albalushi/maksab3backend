<?php

namespace App\Traits\Order;

use App\Traits\Item\ItemStockTrait;
use App\Services\Marketing\CouponService;
use App\CentralLogics\Helpers;
use App\Models\OrderDetail;
use App\Scopes\StoreScope;
use Brian2694\Toastr\Facades\Toastr;
use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Facades\DB;
use App\Services\Order\OrderDetailService;
use App\Services\Promotion\BogoOrderService;
use App\Services\Promotion\BundleOrderService;
use App\Services\Item\ItemService;
use App\Services\System\BusinessSettingService;
use App\Services\Zone\FreeDeliveryService;
use App\Services\System\CurrencyService;

trait OrderFromCartTrait
{
    use DeliveryFeeTrait;
    use ItemStockTrait;
    public function commitEditedOrderFromCart(Request $request, $order, string $editedBy): array
    {
        $carts = $request->input('carts');
        if (!is_array($carts) || count($carts) === 0) {
            return ['status' => 403, 'code' => 'cart', 'message' => translate('messages.Cart is empty')];
        }

        $store = $order->store;
        $originalDetailQtys = $order->details->pluck('quantity', 'id')->all();
        $originalIds = array_map('intval', array_keys($originalDetailQtys));

        $cart = collect([]);
        $keptIds = [];
        $preservedIds = [];

        foreach ($carts as $line) {
            $detailId = isset($line['order_details_id']) ? (int) $line['order_details_id'] : null;
            $isKept = $detailId && in_array($detailId, $originalIds, true);
            if ($isKept) {
                $keptIds[] = $detailId;
            }
            if ($isKept && !empty($line['unavailable'])) {
                $preservedIds[] = $detailId;
                continue;
            }

            $detail = new OrderDetail();
            if ($isKept) {
                $detail->id = $detailId;
            }
            $detail->item_id = $line['item_id'] ?? null;
            $detail->item_campaign_id = null;
            $detail->item_type = 'App\Models\Item';
            $detail->order_id = $order->id;
            $detail->quantity = max(1, (int) ($line['quantity'] ?? 1));
            $detail->variation = json_encode($line['variation'] ?? []);
            $detail->variant = json_encode($line['variant'] ?? []);
            $detail->add_ons = json_encode($line['add_on_ids'] ?? []);
            $detail->add_on_qtys = $line['add_on_qtys'] ?? [];
            $detail->status = true;

            $storedLine = $isKept ? $order->details->firstWhere('id', $detailId) : null;
            $detail->bogo_offer_id = $storedLine?->bogo_offer_id;
            $detail->bogo_group_id = $storedLine?->bogo_group_id;
            $detail->is_free_item = $storedLine?->is_free_item ?? 0;

            $cart->push($detail);
        }

        $deletedIds = array_values(array_diff($originalIds, $keptIds));

        foreach ($order->details as $existing) {
            if (in_array((int) $existing->id, $keptIds, true)) {
                continue;
            }
            $removed = new OrderDetail();
            $removed->id = $existing->id;
            $removed->item_id = $existing->item_id;
            $removed->item_campaign_id = $existing->item_campaign_id;
            $removed->variation = $existing->variation;
            $removed->quantity = $existing->quantity;
            $removed->status = false;
            $cart->push($removed);
        }

        $editLogs = $this->collectEditLogs($cart, $originalDetailQtys);

        $bundlesBefore = app(BogoOrderService::class)->bundlesFor($order->details, (int) $store->id);
        $bundleCopiesBefore = app(BundleOrderService::class)->copiesFor($order->details);

        $computed = $this->makeEditOrderDetails($cart, $request, $store, $originalDetailQtys);
        if (data_get($computed, 'status_code') === 403) {
            return ['status' => 403, 'code' => data_get($computed, 'code', 'cart'), 'message' => data_get($computed, 'message')];
        }

        DB::beginTransaction();
        try {
            $coupon = $order->coupon_code ? app(CouponService::class)->findByCode($order->coupon_code) : null;

            $order_details = $computed['order_details'];
            $total_addon_price = $computed['total_addon_price'];
            $product_price = $computed['product_price'];
            $store_discount_amount = $computed['store_discount_amount'];
            $flash_sale_admin_discount_amount = $computed['flash_sale_admin_discount_amount'];
            $flash_sale_vendor_discount_amount = $computed['flash_sale_vendor_discount_amount'];

            foreach ($order->details->whereIn('id', $preservedIds) as $pd) {
                $order_details[] = app(OrderDetailService::class)->buildPreservedRow($pd);
                $product_price += (float) $pd->price * (int) $pd->quantity;
                $total_addon_price += (float) ($pd->total_add_on_price ?? 0);
                if (($pd->discount_type ?? null) != 'flash_sale') {
                    $store_discount_amount += (float) ($pd->discount_on_item ?? 0) * (int) $pd->quantity;
                }
            }

            if ($bogoRefusal = app(BogoOrderService::class)->editRefusalReason($order, $order_details, $bundlesBefore, (int) $store->id)) {
                DB::rollBack();

                return ['status' => 403, 'code' => 'bogo_offer', 'message' => $bogoRefusal];
            }

            if ($bundleRefusal = app(BundleOrderService::class)
                ->editRefusalReason($order, $order_details, $bundleCopiesBefore, (int) $store->id)) {
                DB::rollBack();

                return ['status' => 403, 'code' => 'bundle', 'message' => $bundleRefusal];
            }

            $store_discount = Helpers::get_store_discount($store);
            if (isset($store_discount)) {
                if ($product_price + $total_addon_price < $store_discount['min_purchase']) {
                    $store_discount_amount = 0;
                }

                if ($store_discount['max_discount'] !== null && $store_discount_amount > $store_discount['max_discount']) {
                    $store_discount_amount = $store_discount['max_discount'];
                }
            }

            $order->delivery_charge = $order->original_delivery_charge;
            if ($coupon && $coupon->coupon_type == 'free_delivery') {
                $order->delivery_charge = 0;
                $coupon = null;
            }
            if ($order->store->free_delivery || $order->order_type == 'take_away') {
                $order->delivery_charge = 0;
            }

            $additionalCharges = [];
            $settings = app(BusinessSettingService::class)->valuesFor(['additional_charge_status', 'additional_charge']);
            $order->additional_charge = 0;
            if (($settings['additional_charge_status'] ?? null) == 1) {
                $order->additional_charge = $settings['additional_charge'] ?? 0;
            }

            $coupon_discount_amount = $coupon ? app(CouponService::class)->calculateDiscount($coupon, $product_price + $total_addon_price - $store_discount_amount) : 0;
            $total_price = $product_price + $total_addon_price - $store_discount_amount - $flash_sale_admin_discount_amount - $flash_sale_vendor_discount_amount - $coupon_discount_amount;
            $totalDiscount = $store_discount_amount + $flash_sale_admin_discount_amount + $flash_sale_vendor_discount_amount + $coupon_discount_amount + $order->ref_bonus_amount;

            $isProCustomer = $order->user_id && \App\Models\User::where('id', $order->user_id)->where('pro_status', 1)->exists();
            if ($isProCustomer) {
                if (app(FreeDeliveryService::class)->frees(
                    [$store?->zone_id],
                    $store?->module_id,
                    $product_price + $total_addon_price - $coupon_discount_amount - $store_discount_amount,
                )) {
                    $order->delivery_charge = 0;
                    $order->free_delivery_by = 'admin';
                }
                $proRecompute = $this->recomputeOrderProDiscountOnEdit(
                    order: $order,
                    subtotal: $product_price + $total_addon_price,
                    totalPrice: $total_price,
                    moduleType: $store?->module?->module_type,
                    deliveryCharge: (float) $order->delivery_charge,
                );
                $total_price = (float) $proRecompute['total_price'];
                $order->delivery_charge = (float) $proRecompute['delivery_charge'];
                if ($proRecompute['delivery_savings'] > 0) {
                    $order->free_delivery_by = $proRecompute['free_delivery_by'];
                }
                $totalDiscount += (float) $proRecompute['discount'];
            }

            $finalCalculatedTax = Helpers::getFinalCalculatedTax($order_details, $additionalCharges, $totalDiscount, $total_price, $store->id);
            $tax_amount = $finalCalculatedTax['tax_amount'];
            $tax_status = $finalCalculatedTax['tax_status'];
            $taxMap = $finalCalculatedTax['taxMap'];
            $orderTaxIds = data_get($finalCalculatedTax, 'taxData.orderTaxIds', []);
            $taxType = data_get($finalCalculatedTax, 'taxType');
            $order->tax_type = $taxType;
            $order->tax_status = $tax_status;
            $total_tax_amount = $order->tax_status == 'included' ? 0 : $tax_amount;

            if ($store->minimum_order > $product_price + $total_addon_price) {
                DB::rollBack();
                return ['status' => 403, 'code' => 'minimum_order', 'message' => translate('messages.Your order is below the store minimum.') . ' ' . translate('messages.Minimum order amount') . ': ' . Helpers::format_currency($store->minimum_order)];
            }

            if (!$isProCustomer) {
                if (app(FreeDeliveryService::class)->frees(
                    [$store?->zone_id],
                    $store?->module_id,
                    $product_price + $total_addon_price - $coupon_discount_amount - $store_discount_amount,
                )) {
                    $order->delivery_charge = 0;
                    $order->free_delivery_by = 'admin';
                }
            }

            $total_order_ammount = $total_price + $total_tax_amount + $order->delivery_charge + $order->additional_charge;
            $total_order_ammount = $this->applyDeliveryTypeToAmount($order, (float) $total_order_ammount);
            $adjustment = $order->order_amount - $total_order_ammount;

            $order->coupon_discount_amount = $coupon_discount_amount;
            $order->store_discount_amount = $store_discount_amount;
            $order->total_tax_amount = $total_tax_amount;
            $order->order_amount = $total_order_ammount;
            $order->adjusment = $adjustment;

            $order->bogo_discount_amount = round(
                collect($order_details)->sum(fn ($line) => (float) ($line['bogo_free_value'] ?? 0) * (int) ($line['quantity'] ?? 0)),
                config('round_up_to_digit')
            );
            $order->bundle_discount_amount = round(
                collect($order_details)->sum(
                    fn ($line) => data_get($line, 'bundle_group_id')
                        ? (float) ($line['discount_on_item'] ?? 0) * (int) ($line['quantity'] ?? 0)
                        : 0
                ),
                config('round_up_to_digit')
            );
            $order->happy_hour_id = $computed['happy_hour_id'] ?? null;
            $order->discount_on_product_by = $computed['discount_on_product_by'] ?? $order->discount_on_product_by;
            $order->edited = true;
            $order->save();

            foreach ($editLogs as $log) {
                $this->makeEditOrderLogs($order->id, $log, $editedBy);
            }

            if (!empty($deletedIds)) {
                app(OrderDetailService::class)->deleteByIds($deletedIds, $order->id);
            }

            if ($order->order_type !== 'parcel') {
                $taxMapCollection = collect($taxMap);
                foreach ($order_details as $key => $item) {
                    $item_id = $item['item_id'] ?: $item['item_campaign_id'];
                    $index = $taxMapCollection->search(fn ($tax) => $tax['product_id'] == $item_id);
                    if ($index !== false) {
                        $matchedTax = $taxMapCollection->pull($index);
                        $order_details[$key]['tax_status'] = $matchedTax['include'] == 1 ? 'included' : 'excluded';
                        $order_details[$key]['tax_amount'] = $matchedTax['totalTaxamount'];
                    }
                }

                foreach ($order_details as $detail) {
                    $cartId = $detail['cart_id'] ?? null;
                    unset($detail['cart_id']);
                    $detail['order_id'] = $order->id;
                    if ($cartId && isset($originalDetailQtys[$cartId])) {
                        unset($detail['created_at']);
                        app(OrderDetailService::class)->updateForOrder($cartId, $order->id, $detail);
                    } else {
                        app(OrderDetailService::class)->insertOne($detail);
                    }
                }

                $order?->orderTaxes()?->delete();
                if (count($orderTaxIds)) {
                    \Modules\TaxModule\Services\CalculateTaxService::updateOrderTaxData(
                        orderId: $order->id,
                        orderTaxIds: $orderTaxIds,
                    );
                }

                app(BogoOrderService::class)->syncUsage($order, $order_details);

                $this->adjustEditedStockFromCart($cart, $originalDetailQtys);
            }

            DB::commit();
        } catch (\Throwable $th) {
            DB::rollBack();
            info($th->getMessage());
            return ['status' => 403, 'code' => 'order_update', 'message' => translate('messages.Order update failed')];
        }

        session()->forget(['order_cart', 'edit_tax_amount', 'edit_tax_included', 'discount_on_product_by_session', 'open_edit_offcanvas']);

        return ['status' => 200, 'message' => translate('Updated successfully')];
    }
    public function updateOrderFromCartRequest(Request $request, $order, string $editedBy)
    {
        if (!$order) {
            return $this->orderEditResult($request, ['status' => 404, 'code' => 'order_id', 'message' => translate('No data found')]);
        }

        if (!$order->is_editable) {
            return $this->orderEditResult($request, ['status' => 403, 'code' => 'status', 'message' => translate('messages.Order can not be edited')]);
        }

        $result = $this->commitEditedOrderFromCart($request, $order, $editedBy);

        return $this->orderEditResult($request, $result);
    }

    protected function primeEditCartRelations($cart)
    {
        $models = collect($cart)->filter(fn ($row) => $row instanceof OrderDetail);

        if ($models->isEmpty()) {
            return $cart;
        }

        (new EloquentCollection($models->all()))->loadMissing([
            'item' => fn ($query) => $query->withoutGlobalScope(StoreScope::class)->withStorage(),
            'campaign' => fn ($query) => $query->withoutGlobalScope(StoreScope::class)->withStorage(),
        ]);

        foreach (['item', 'campaign'] as $relation) {
            $related = $models
                ->filter(fn ($row) => $row->relationLoaded($relation) && $row->getRelation($relation) !== null)
                ->map(fn ($row) => $row->getRelation($relation))
                ->values();

            if ($related->isNotEmpty()) {
                (new EloquentCollection($related->all()))->loadMissing('storage');
            }
        }

        return $cart;
    }
    private function orderEditResult(Request $request, array $result)
    {
        $status = $result['status'] ?? 403;

        if ($request->expectsJson()) {
            if ($status === 200) {
                return response()->json(['message' => $result['message']], 200);
            }
            return response()->json(['errors' => [['code' => $result['code'] ?? 'order', 'message' => $result['message']]]], $status);
        }

        if ($status === 200) {
            Toastr::success($result['message']);
        } else {
            Toastr::error($result['message']);
        }
        return back();
    }
    private function adjustEditedStockFromCart($cart, array $originalDetailQtys): void
    {
        foreach ($cart as $c) {
            if (empty($c['item_id'])) {
                continue;
            }

            $stockProduct = app(ItemService::class)->findUnscopedWithModule($c['item_id']);
            if (!$stockProduct || !$stockProduct->module) {
                continue;
            }
            if (!data_get(config('module.' . $stockProduct->module->module_type), 'stock', false)) {
                continue;
            }

            $variationDecoded = is_string($c['variation']) ? (json_decode($c['variation'], true) ?: []) : (is_array($c['variation']) ? $c['variation'] : []);
            $variantType = (isset($variationDecoded[0]['type']) && $variationDecoded[0]['type'] !== '') ? $variationDecoded[0]['type'] : null;

            $wasKept = !empty($c['status']);
            $originalQty = (isset($c->id) && isset($originalDetailQtys[$c->id])) ? (int) $originalDetailQtys[$c->id] : 0;

            $delta = $wasKept ? (int) $c['quantity'] - $originalQty : -$originalQty;
            if ($delta === 0) {
                continue;
            }

            self::updateItemStock($stockProduct, $delta, $variantType)?->save();
            if ($delta > 0) {
                self::updateFlashSaleStock($stockProduct, $delta)?->save();
            } else {
                self::updateFlashSaleStock($stockProduct, abs($delta), true)?->save();
            }
        }
    }
}
