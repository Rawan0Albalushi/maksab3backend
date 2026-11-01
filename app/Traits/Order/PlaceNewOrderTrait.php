<?php

namespace App\Traits\Order;

use App\Support\Settings\BusinessRules;
use App\Traits\Item\ItemStockTrait;
use App\Services\Payment\WalletTransactionService;
use App\Models\CashBackHistory;
use App\Models\Item;
use App\Models\Order;
use App\Models\Store;
use Illuminate\Http\Request;
use App\CentralLogics\Helpers;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use App\Http\Resources\Common\Item\ProductResource;
use App\Traits\Item\ProductPayloadTrait;
use App\Services\Marketing\CouponService;
use Illuminate\Support\Facades\DB;
use App\Mail\OrderVerificationMail;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;
use App\Http\Requests\Customer\Order\PlaceOrderRequest;
use App\Mail\PlaceOrder;
use App\Services\Marketing\CashBackService;
use App\Models\ModuleZoneDeliveryOption;
use App\Services\Zone\SurgePriceService;
use App\Traits\Payment\ProCustomerSubscriptionTrait;
use Carbon\Carbon;
use App\Services\Order\OrderPaymentService;
use Illuminate\Support\Facades\Log;
use App\Services\Customer\UserService;
use App\Services\Order\CartService;
use App\Services\Order\OrderService;
use App\Services\Customer\UserFileService;
use App\Services\Order\DeliveryChargeService;
use App\Services\Order\OrderDetailService;
use App\Services\Promotion\BundleOrderService;
use App\Services\Promotion\BogoOrderService;
use App\Services\Promotion\StoreDiscountResolver;
use App\Services\Store\StoreService;
use App\Services\Order\OrderEditLogService;
use App\Services\DeliveryMan\DmVehicleService;
use App\Models\Zone;
use App\Services\Zone\ZoneService;
use App\Services\Item\ItemService;
use App\Services\Marketing\ItemCampaignService;
use App\Services\Item\AddonService;
use App\Services\Zone\ModuleZoneDeliveryOptionService;
use App\Traits\System\ReelsAddonTrait;
use App\Support\Notification\SendNotification;
use App\Services\System\BusinessSettingService;
use App\Services\System\CurrencyService;
use App\Support\Storage\FileStorage;

trait PlaceNewOrderTrait
{
    use ReelsAddonTrait;
    use ProductPayloadTrait;
    use DeliveryFeeTrait;
    use ItemStockTrait;
    use ProCustomerSubscriptionTrait;
    use GuestAccountTrait;
    public function placeNewOrder(Request $request, $is_prescription = false)
    {
        $validator = Validator::make($request->all(), PlaceOrderRequest::placementRules($request));

        if ($validator->fails()) {
            return response()->json(['errors' => Helpers::error_processor($validator)], 403);
        }
        try {
            DB::beginTransaction();
            $createNewUser =  $this->createNewUser($request);

            if (data_get($createNewUser, 'newUser') === true) {
                $request->merge([
                    'is_guest' => 0,
                    'user' => data_get($createNewUser, 'user'),
                ]);
            } elseif (data_get($createNewUser, 'status_code') === 403) {
                DB::rollBack();
                return response()->json([
                    'errors' => [
                        ['code' => data_get($createNewUser, 'code'), 'message' => data_get($createNewUser, 'message')]
                    ]
                ], data_get($createNewUser, 'status_code'));
            }

            $validationCheck =  $this->validationCheck($request);
            if (data_get($validationCheck, 'status_code') === 403) {
                DB::rollBack();
                return response()->json([
                    'errors' => [
                        ['code' => data_get($validationCheck, 'code'), 'message' => data_get($validationCheck, 'message')]
                    ]
                ], data_get($validationCheck, 'status_code'));
            }

            $schedule_at = $request->schedule_at ? Carbon::parse($request->schedule_at) : now();
            $zoneAndStore = $this->getZoneAndStore($request, $schedule_at);

            if (data_get($zoneAndStore, 'status_code') === 403) {
                DB::rollBack();
                return response()->json([
                    'errors' => [
                        ['code' => data_get($zoneAndStore, 'code'), 'message' => data_get($zoneAndStore, 'message')]
                    ]
                ], data_get($zoneAndStore, 'status_code'));
            }

            $store = $zoneAndStore['store'];
            $zone = $zoneAndStore['zone'];

            $zoneAndStoreValidationCheck = $this->zoneAndStoreValidationCheck($request, $schedule_at, $zone, $store);
            if (data_get($zoneAndStoreValidationCheck, 'status_code') === 403) {
                DB::rollBack();
                return response()->json([
                    'errors' => [
                        ['code' => data_get($zoneAndStoreValidationCheck, 'code'), 'message' => data_get($zoneAndStoreValidationCheck, 'message')]
                    ]
                ], data_get($zoneAndStoreValidationCheck, 'status_code'));
            }

            $zonePaymentCheck = $this->zonePaymentValidationCheck($request, $zone);
            if (data_get($zonePaymentCheck, 'status_code') === 403) {
                DB::rollBack();
                return response()->json([
                    'errors' => [
                        ['code' => data_get($zonePaymentCheck, 'code'), 'message' => data_get($zonePaymentCheck, 'message')]
                    ]
                ], data_get($zonePaymentCheck, 'status_code'));
            }

            $coupon = null;
            $coupon_created_by = null;
            $delivery_charge = null;
            $free_delivery_by = null;
            $taxMap = [];
            $orderTaxIds = [];

            if ($request->order_type !== 'parcel') {
                $couponData = $this->getCouponData($request);
                if (data_get($couponData, 'status_code') === 403) {
                    DB::rollBack();
                    return response()->json([
                        'errors' => [
                            ['code' => data_get($couponData, 'code'), 'message' => data_get($couponData, 'message')]
                        ]
                    ], data_get($couponData, 'status_code'));
                } else {
                    $coupon = data_get($couponData, 'coupon');
                    $coupon_created_by = data_get($couponData, 'coupon_created_by');
                    $delivery_charge = data_get($couponData, 'delivery_charge');
                    $free_delivery_by = data_get($couponData, 'free_delivery_by');
                }
            }

            $module_wise_delivery_charge = $zone->modules()->where('modules.id', getModuleId($request->header('moduleId')))->first();

            // A module with no module_zone row for this zone has never been priced at all — no
            // delivery rule, no pivot rate, nothing. getDeliveryCharge() used to fall through this
            // silently, returning whatever preset/coupon value it was handed (often null, read
            // downstream as 0), so an order in a genuinely unconfigured (zone, module) pair placed
            // successfully with a $0.00 delivery charge instead of being refused. Self-delivery
            // stores price from their own columns regardless (DeliveryChargeService::deliveryRates()
            // gives them first priority), so they are exempt; take-away and parcel never reach this
            // pricing path at all.
            //
            // S19 widened it from CONNECTED to AVAILABLE. A pivot row only says the module is
            // attached to the zone; it says nothing about whether the pair can be priced or
            // timed, and `module_zone` will price it regardless (DeliveryChargeService step 2d).
            // The zone resolver and the module list no longer offer such a pair, so an order for
            // one comes from a stale client or a hand-made request — and it is refused here
            // rather than quoted off a fallback the customer was never shown.
            //
            // Same message either way: "connected but unpriced" and "not connected at all" are
            // the same thing to the customer, and neither is theirs to fix.
            $moduleAvailableInZone = $module_wise_delivery_charge
                && in_array(
                    (int) getModuleId($request->header('moduleId')),
                    $zone->completeModuleIds(),
                    true
                );

            if (
                $request->order_type === 'delivery'
                && (int) ($store?->sub_self_delivery ?? 0) !== 1
                && ! $moduleAvailableInZone
            ) {
                DB::rollBack();
                return response()->json([
                    'errors' => [
                        ['code' => 'delivery_charge', 'message' => translate('messages.Delivery is not configured for this zone and module yet. Please contact support.')]
                    ]
                ], 403);
            }

            $deliveryChargeData = $this->getDeliveryCharge($request, $zone, $store, $module_wise_delivery_charge, $delivery_charge, getModuleId($request->header('moduleId')));

            $delivery_charge = data_get($deliveryChargeData, 'delivery_charge', 0);
            $original_delivery_charge = data_get($deliveryChargeData, 'original_delivery_charge', 0);
            $vehicle_id = data_get($deliveryChargeData, 'vehicle_id', null);
            $resolved_delivery_type = data_get($deliveryChargeData, 'delivery_type', 'standard');
            $resolved_delivery_type_charge = (float) data_get($deliveryChargeData, 'delivery_type_charge', 0);
            $resolved_surge_amount = (float) data_get($deliveryChargeData, 'surge_amount', 0);

            // Every field goes through sanitize_client_string(), because a client that
            // interpolates an unset variable posts the literal text "undefined" and PHP accepts
            // it: `?? ''` only catches a real null and a ternary reads six letters as present.
            // Left alone, it is written into delivery_address verbatim and every screen that
            // renders the address prints "undefined" back at staff and couriers as though the
            // customer had typed it. The fallbacks below are the point -- a name that arrives as
            // "undefined" now falls through to the customer's own name, as an absent one always
            // did.
            $address = [
                'contact_person_name' => sanitize_client_string(
                    $request->contact_person_name,
                    $request->user ? $request->user->f_name . ' ' . $request->user->l_name : ''
                ),
                'contact_person_number' => sanitize_client_string(
                    $request->contact_person_number,
                    $request->user ? (string) $request->user->phone : ''
                ),
                'contact_person_email' => sanitize_client_string(
                    $request->contact_person_email,
                    $request->user ? (string) $request->user->email : ''
                ),
                'address_type' => sanitize_client_string($request->address_type, 'Delivery'),
                'address' => sanitize_client_string($request?->address),
                'floor' => sanitize_client_string($request?->floor),
                'road' => sanitize_client_string($request?->road),
                'house' => sanitize_client_string($request?->house),
                'longitude' => (string)$request->longitude,
                'latitude' => (string)$request->latitude,
            ];

            $total_addon_price = 0;
            $product_price = 0;
            $store_discount_amount = 0;
            $flash_sale_vendor_discount_amount = 0;
            $flash_sale_admin_discount_amount = 0;
            $coupon_discount_amount = 0;

            $product_data = [];
            $order_details = [];

            $lastId = app(OrderService::class)->maxId() ?? 99999;
            $order = new Order();
            $order->id = $lastId + 1;

            $order_status = 'pending';
            if (($request->partial_payment && $request->payment_method != 'offline_payment') || $request->payment_method == 'wallet') {
                $order_status = 'confirmed';
            }
            if (in_array($request->payment_method, ['digital_payment', 'offline_payment'])) {
                $order_status = 'failed';
            }

            $order->bring_change_amount = $request['bring_change_amount'] ?? 0 ;

            $order->user_id = $request->user ? $request->user->id : $request['guest_id'];
            $order->order_amount = $request['order_amount'] ?? 0;
            $order->payment_status = ($request->partial_payment ? 'partially_paid' : ($request['payment_method'] == 'wallet' ? 'paid' : 'unpaid'));
            $order->order_status = $order_status;
            $order->coupon_code = $request['coupon_code'];
            $order->payment_method = $request->partial_payment ? 'partial_payment' : $request->payment_method;
            $order->transaction_reference = null;
            $order->order_note = $request['order_note'];
            $order->unavailable_item_note = $request['unavailable_item_note'];
            $order->delivery_instruction = $request['delivery_instruction'];
            $order->order_type = $request['order_type'];
            $order->store_id = $request['store_id'];
            $order->delivery_charge = round($delivery_charge, config('round_up_to_digit')) ?? 0;
            $order->original_delivery_charge = round($original_delivery_charge, config('round_up_to_digit'));
            // Snapshotted so panels identify a self-delivery order the same way for its whole
            // lifetime, even if the store's setting is changed later (TC_11/TC_19).
            $order->is_self_delivery = (int) ($store?->sub_self_delivery ?? 0) === 1;
            $order->delivery_type = $resolved_delivery_type;
            $order->delivery_type_charge = round($resolved_delivery_type_charge, config('round_up_to_digit'));
            // Frozen at placement: delivery_charge folds the surge component in and nothing else
            // records it, so after this row is saved there is no way to tell how much of the
            // delivery figure was surge. Recorded here so reports, refunds and exports can back
            // it out later instead of only seeing one combined number (TC_500, TC_505).
            $order->surge_amount = round($resolved_surge_amount, config('round_up_to_digit'));
            $order->delivery_address = json_encode($address);
            $order->schedule_at = $schedule_at;
            $order->scheduled = $request->schedule_at ? 1 : 0;
            $order->cutlery = $request->cutlery ? 1 : 0;
            $order->is_guest = $request->user ? 0 : 1;
            $order->otp = rand(1000, 9999);
            $order->zone_id = isset($zone) ? $zone->id : end(json_decode($request->header('zoneId'), true));
            $order->module_id = getModuleId($request->header('moduleId'));
            $order->parcel_category_id = $request->parcel_category_id;
            // The other two additive tiers, recorded beside the category so the delivery charge
            // can be accounted for afterwards. Stored raw rather than only when the tier is
            // switched on: the engine already decided whether they cost anything, and writing
            // down what the customer actually chose is the more honest record of the order.
            $order->weight_id = $request->weight_id ?: null;
            $order->dimension_id = $request->dimension_id ?: null;
            $order->receiver_details = json_decode($request->receiver_details ?? '');

            $order->delivery_duration = is_numeric($request['delivery_duration'])
                ? (int) $request['delivery_duration']
                : null;

            if ($order_status == 'confirmed') {
                $order->confirmed = now();
            }
            $order->dm_vehicle_id = $vehicle_id;
            $order->pending = now();
            if (!empty($request->file('order_attachment')) && is_array($request->file('order_attachment'))) {
                $img_names = [];
                if (!empty($request->file('order_attachment'))) {
                    $images = [];
                    foreach ($request->order_attachment as $img) {
                        $image_name = FileStorage::upload('order/', $img);
                        array_push($img_names, ['img' => $image_name, 'storage' => FileStorage::getDisk()]);
                    }
                    $images = $img_names;
                }
            } else {
                $img_names = [];
                if (!empty($request->file('order_attachment'))) {
                    $images = [];
                    $image_name = FileStorage::upload('order/', $request->file('order_attachment'));
                    array_push($img_names, ['img' => $image_name, 'storage' => FileStorage::getDisk()]);

                    $images = $img_names;
                }
            }
            if ($request->saved_images && is_array($request->saved_images) && count($request->saved_images) > 0) {
                $saved_image_names = [];
                foreach ($request->saved_images as $saved_image) {
                    if (!is_string($saved_image) || $saved_image === '') {
                        continue;
                    }

                    $savedFile = app(UserFileService::class)->findByName($request->user?->id, $saved_image);

                    if ($savedFile) {
                        $disk = $savedFile->storage ?? FileStorage::getDisk();
                        $sourcePath = 'order/saved_files/' . $savedFile->file_name;
                        $extension = pathinfo($savedFile->file_name, PATHINFO_EXTENSION);
                        $newFileName = Carbon::now()->toDateString() . '-' . uniqid() . '.' . $extension;
                        $destinationPath = 'order/' . $newFileName;

                        if (Storage::disk($disk)->exists($sourcePath)) {
                            if (!Storage::disk($disk)->exists('order')) {
                                Storage::disk($disk)->makeDirectory('order');
                            }

                            Storage::disk($disk)->copy($sourcePath, $destinationPath);
                            $saved_image_names[] = ['img' => $newFileName, 'storage' => $disk];
                        }
                    }
                }

                if (!empty($saved_image_names)) {
                    if (!isset($images)) {
                        $images = [];
                    }

                    foreach ($saved_image_names as $saved_image_name) {
                        $images[] = $saved_image_name;
                    }
                }
            }
            if (isset($images)) {
                $order->order_attachment = json_encode($images);
            }
            $order->distance = (float) ($request->distance ?? 0);
            $order->created_at = now();
            $order->updated_at = now();
            $order->charge_payer = $request->charge_payer;
            $order->prescription_order = $is_prescription ? 1 : 0;
            $additionalCharges = [];

            $settings = app(BusinessSettingService::class)->valuesFor([
                'dm_tips_status',
                'additional_charge_status',
                'additional_charge',
                'extra_packaging_data',
            ]);

            $dm_tips_manage_status     = $settings['dm_tips_status'] ?? null;
            $additional_charge_status  = $settings['additional_charge_status'] ?? null;
            $additional_charge         = $settings['additional_charge'] ?? null;

            $extra_packaging_data_raw  = $settings['extra_packaging_data'] ?? '';
            $extra_packaging_data      = json_decode($extra_packaging_data_raw, true) ?? [];

            $order->dm_tips = 0;
            if ($dm_tips_manage_status == 1) {
                $order->dm_tips = $request->dm_tips ?? 0;
            }

            $order->additional_charge = 0;

            if ($additional_charge_status == 1) {
                $order->additional_charge = $additional_charge ?? 0;
            }

            $order->extra_packaging_amount =  (!empty($extra_packaging_data) && $request?->extra_packaging_amount > 0 && $store && ($extra_packaging_data[$store->module->module_type] == '1') && ($store?->storeConfig?->extra_packaging_status == '1')) ? $store?->storeConfig?->extra_packaging_amount : 0;

            if ($order->extra_packaging_amount > 0) {
                $additionalCharges['tax_on_packaging_charge'] =  $order->extra_packaging_amount;
            }

            if ($request->order_type !== 'parcel') {
                if ($is_prescription === false) {
                    $carts = app(CartService::class)->getCheckoutList(
                        $order->user_id,
                        $order->is_guest,
                        $request->store_id,
                        getModuleId($request->header('moduleId')),
                        (isset($request->is_buy_now) && $request->is_buy_now == 1 && $request->cart_id) ? $request->cart_id : null
                    );

                    if (isset($request->is_buy_now) && $request->is_buy_now == 1) {
                        $carts = is_array($request['cart']) ? $request['cart'] : (json_decode($request['cart'], true) ?? []);
                    }

                    if (count($carts) == 0 && !$is_prescription) {
                        DB::rollBack();
                        return response()->json([
                            'errors' => [
                                ['code' => 'empty_cart', 'message' => translate('messages.You cannot place empty orders')]
                            ]
                        ], 403);
                    }

                    $bogoRefusal = app(BogoOrderService::class)->blockingReason(
                        ['user_id' => $order->user_id, 'is_guest' => (int) $order->is_guest],
                        (int) $order->store_id,
                        $request['contact_person_number'],
                        $schedule_at ?? null,
                        $request['order_type'] ?? null
                    );

                    if ($bogoRefusal) {
                        DB::rollBack();
                        return response()->json([
                            'errors' => [['code' => 'bogo_offer', 'message' => $bogoRefusal]]
                        ], 403);
                    }

                    $bundleRefusal = app(BundleOrderService::class)->blockingReason(
                        ['user_id' => $order->user_id, 'is_guest' => (int) $order->is_guest],
                        (int) $order->store_id,
                        $schedule_at ?? null
                    );

                    if ($bundleRefusal) {
                        DB::rollBack();
                        return response()->json([
                            'errors' => [['code' => 'bundle', 'message' => $bundleRefusal]]
                        ], 403);
                    }

                    $order_details = $this->makeOrderDetails($carts, $request, $order, $store);

                    if (data_get($order_details, 'status_code') === 403) {
                        DB::rollBack();
                        return response()->json([
                            'errors' => [
                                ['code' => data_get($order_details, 'code'), 'message' => data_get($order_details, 'message')]
                            ]
                        ], data_get($order_details, 'status_code'));
                    }

                    $total_addon_price = $order_details['total_addon_price'];
                    $product_price = $order_details['product_price'];
                    $store_discount_amount = $order_details['store_discount_amount'];
                    $flash_sale_admin_discount_amount = $order_details['flash_sale_admin_discount_amount'];
                    $flash_sale_vendor_discount_amount = $order_details['flash_sale_vendor_discount_amount'];
                    $product_data = $order_details['product_data'];

                    $order->bogo_discount_amount = round($order_details['bogo_free_value'] ?? 0, config('round_up_to_digit'));
                    $order->happy_hour_id = $order_details['happy_hour_id'] ?? null;

                    $order->bundle_discount_amount = round($order_details['bundle_discount_amount'] ?? 0, config('round_up_to_digit'));

                    // Read HERE, with the rest of the top-level keys, and not after the line
                    // below -- which narrows $order_details to the lines array, where no such key
                    // exists. Read there, the `??` fell through on every order and the bearer
                    // makeOrderDetails() had just computed was thrown away, so
                    // orders.discount_on_product_by was always 'vendor'.
                    //
                    // A happy hour IS vendor-borne, so that case stayed accidentally right and
                    // hid this; a vendor's own standing store discount is admin-borne
                    // (StoreDiscountResolver::bearerFor()) and was being billed to the store.
                    $discount_on_product_by = $order_details['discount_on_product_by'] ?? 'vendor';

                    $order_details = $order_details['order_details'];
                }

                // `??` rather than a bare read: a parcel or prescription order never enters the
                // block above, so the variable is undefined there -- and neither carries a
                // store-wide discount for a bearer to be wrong about.
                $order->discount_on_product_by = $discount_on_product_by ?? 'vendor';

                $coupon_discount_amount = $coupon ? app(CouponService::class)->calculateDiscount($coupon, $product_price + $total_addon_price - $store_discount_amount - $flash_sale_admin_discount_amount - $flash_sale_vendor_discount_amount) : 0;

                $total_price = $product_price + $total_addon_price - $store_discount_amount - $flash_sale_admin_discount_amount - $flash_sale_vendor_discount_amount  - $coupon_discount_amount;

                if ($order->is_guest  == 0 && $order->user_id) {
                    $user = app(UserService::class)->findWithOrderCount($order->user_id);
                    $discount_data = Helpers::getCusromerFirstOrderDiscount(order_count: $user->orders_count, user_creation_date: $user->created_at,  refby: $user->ref_by, price: $total_price);
                    if (data_get($discount_data, 'is_valid') == true &&  data_get($discount_data, 'calculated_amount') > 0) {
                        $total_price = $total_price - data_get($discount_data, 'calculated_amount');
                        $order->ref_bonus_amount = data_get($discount_data, 'calculated_amount');
                    }
                }

                $total_price = max($total_price, 0);

                $proApply    = $this->applyProCustomerDiscount(
                    $order->user_id,
                    $product_price + $total_addon_price,
                    $total_price,
                    $store?->module?->module_type ?? 'parcel',
                );
                $pro_offer   = $proApply['offer'];
                $proDiscount = $proApply['discount'];
                $total_price = $proApply['total_price'];

                $order->tax_status = 'excluded';

                $totalDiscount = $store_discount_amount + $flash_sale_admin_discount_amount + $flash_sale_vendor_discount_amount  + $coupon_discount_amount +  $order->ref_bonus_amount + $proDiscount;

                $finalCalculatedTax =  Helpers::getFinalCalculatedTax(
                    $order_details,
                    $additionalCharges,
                    $totalDiscount,
                    $total_price,
                    $store->id
                );

                $taxType=  data_get($finalCalculatedTax ,'taxType');
                $tax_amount = $finalCalculatedTax['tax_amount'];
                $tax_status = $finalCalculatedTax['tax_status'];
                $taxMap = $finalCalculatedTax['taxMap'];
                $orderTaxIds = data_get($finalCalculatedTax, 'taxData.orderTaxIds', []);

                $order->tax_status = $tax_status;
                $order->tax_type = $taxType;

                if (!$is_prescription  && $store->minimum_order > $product_price + $total_addon_price) {
                    DB::rollBack();
                    return response()->json([
                        'errors' => [
                            ['code' => 'order_time', 'message' => translate('messages.Minimum order amount') . ': ' . $store->minimum_order . ' ' . app(CurrencyService::class)->code()]
                        ]
                    ], 406);
                }

                $eligibleAmount = max(0, $product_price + $total_addon_price - $coupon_discount_amount - $store_discount_amount - $flash_sale_admin_discount_amount - $flash_sale_vendor_discount_amount);

                $couponCodeForFree = ($coupon && $coupon->coupon_type === 'free_delivery') ? $coupon->code : null;
                $effective = $this->effectiveFee(
                    (float) $order->delivery_charge,
                    $store,
                    (float) $eligibleAmount,
                    $couponCodeForFree,
                );
                if ($effective['is_free']) {
                    $order->delivery_charge = 0;
                    // Nothing was charged, so nothing of it was surge either — a stored
                    // surge_amount that outlives a zeroed delivery_charge would overstate what a
                    // report can back out of this order.
                    $order->surge_amount = 0;
                    if ($effective['free_by'] === self::FREE_BY_COUPON && $coupon) {
                        $free_delivery_by = $coupon->created_by;
                    } else {
                        $free_delivery_by = $effective['free_by'];
                    }
                }

                // Resolved against the real base fee, before the Pro customer's delivery-fee
                // benefit below can touch it — matching Admin/Vendor POS's own place_order(),
                // which applies applySaverToOrder() before applyProCustomerDeliveryFee() for
                // exactly this reason (see POSController::place_order()'s comment on the same
                // ordering). Resolving it after the benefit had already reduced delivery_charge
                // clamped Slightly Delay's reduction against the discounted fee instead of the
                // real one, understating what the address modal and delivery-type picker showed.
                $saver = $this->resolveSaverDeliveryType($request, $zone, $store, $module_wise_delivery_charge, getModuleId($request->header('moduleId')), (float) $order->delivery_charge);
                $order->delivery_type = $saver['delivery_type'];
                $order->delivery_type_charge = round($saver['delivery_type_charge'], config('round_up_to_digit'));

                // Builder orders keep the Pro customer's item-level discount (applyProCustomerDiscount,
                // above) but are excluded from the delivery-fee benefit specifically (TC_14) — the
                // 'order_source' request key is set only by App\Builder\CheckoutProvider and is never
                // persisted onto the order.
                if(!$free_delivery_by && $request->input('order_source') !== 'builder'){
                    // The discount's percentage is taken against the fee as the customer actually
                    // sees it -- Express premium or Slightly Delay reduction already folded in --
                    // not the pre-saver base, or a Slightly Delay order gets discounted as if it
                    // were still charging the undiscounted fee.
                    $netDeliveryFee = (float) $order->delivery_charge + match ($order->delivery_type) {
                        ModuleZoneDeliveryOption::TYPE_EXPRESS => (float) $order->delivery_type_charge,
                        ModuleZoneDeliveryOption::TYPE_SLIGHTLY_DELAY => -(float) $order->delivery_type_charge,
                        default => 0.0,
                    };
                    $proDelivery            = $this->applyProCustomerDeliveryFee(
                        $pro_offer,
                        $netDeliveryFee,
                        (float) $total_price,
                        $free_delivery_by,
                        $store?->module?->module_type ?? 'parcel',
                    );
                    // Savings taken off the pre-saver base, NOT $proDelivery['delivery_charge']
                    // itself (which is net-of-savings) -- delivery_type_charge stays a separate,
                    // untouched amount that applyDeliveryTypeToAmount() still applies to
                    // order_amount below, so assigning the net figure here would double-count it.
                    $order->delivery_charge = (float) $order->delivery_charge - (float) $proDelivery['savings'];
                    $free_delivery_by       = $proDelivery['free_delivery_by'];
                    $proDeliverySavings     = $proDelivery['savings'];
                }

                // Set here, ahead of applyDeliveryTypeToAmount() below, rather than only at the
                // bottom of this block — that call reads $order->free_delivery_by to decide
                // whether a zeroed delivery_charge means "free delivery ran" or "no delivery
                // pricing applies at all", and needs the answer before it runs, not after.
                $order->free_delivery_by = $free_delivery_by;

                if ($coupon) {
                    $coupon->increment('total_uses');
                }
                $order->coupon_created_by = $coupon_created_by;
                $order->coupon_discount_amount = round($coupon_discount_amount, config('round_up_to_digit'));
                $order->coupon_discount_title = $coupon ? $coupon->title : '';

                $order->store_discount_amount = round($store_discount_amount, config('round_up_to_digit'));
                $order->tax_percentage = 0;
                $order->total_tax_amount = round($tax_amount, config('round_up_to_digit'));
                $order->order_amount = round($total_price + $tax_amount + $order->delivery_charge, config('round_up_to_digit'));
                $order->order_amount = $this->applyDeliveryTypeToAmount($order, (float) $order->order_amount);
            } else {
                $order->delivery_charge = round($original_delivery_charge, config('round_up_to_digit')) ?? 0;
                $pro_offer   = $this->getProCustomerOffer($order->user_id, false, true, 'parcel');
                $proDiscount = 0;
                $proDelivery = $this->applyProCustomerDeliveryFee(
                    $pro_offer,
                    (float) $order->delivery_charge,
                    (float) $order->delivery_charge,
                    $free_delivery_by,
                    'parcel',
                );
                $proDeliverySavings = $proDelivery['savings'];
                $free_delivery_by   = $proDelivery['free_delivery_by'];

                $order->delivery_charge  = round($proDelivery['delivery_charge'], config('round_up_to_digit')) ?? 0;
                $order->free_delivery_by = $free_delivery_by;
                $order->original_delivery_charge = round($original_delivery_charge, config('round_up_to_digit'));
                $order->order_amount = round($order->delivery_charge, config('round_up_to_digit'));
                // TC_288 — getDeliveryCharge() already resolves Express/Slightly-Delay for parcel
                // orders too (it calls resolveSaverDeliveryType() unconditionally), and
                // $order->delivery_type_charge was set from that result above. Only the non-parcel
                // branch was folding it into order_amount; a parcel order with Express or Slightly
                // Delay configured recorded the adjustment on the order but never actually billed
                // it. Same call the non-parcel branch makes, on the parcel total.
                $order->order_amount = $this->applyDeliveryTypeToAmount($order, (float) $order->order_amount);

                $productIds[] = [
                    'id' => 1,
                    'original_price' => $order->order_amount,
                    'quantity' => 1,
                    'category_id' =>  $request->parcel_category_id,
                    'discount' => 0,
                    'discount_type' => '',
                    'after_discount_final_price' => $order->order_amount,
                    'is_campaign_item' => false,
                ];

                $taxData =  \Modules\TaxModule\Services\CalculateTaxService::getCalculatedTax(
                    amount: $order->order_amount,
                    productIds: $productIds,
                    taxPayer: 'parcel',
                    storeData: true,
                    additionalCharges: $additionalCharges,
                    addonIds: [],
                    orderId: null,
                    storeId: null
                );

                $tax_amount = $taxData['totalTaxamount'];
                $tax_included = $taxData['include'];
                $orderTaxIds = $taxData['orderTaxIds'] ?? [];
                $tax_status = $tax_included ?  'included' : 'excluded';
                $order->total_tax_amount = round($tax_amount, config('round_up_to_digit'));

                $order->tax_status = $tax_status;
                $order->order_amount = round($order->delivery_charge + $tax_amount, config('round_up_to_digit'));
            }
            $order->flash_admin_discount_amount = round($flash_sale_admin_discount_amount, config('round_up_to_digit'));
            $order->flash_store_discount_amount = round($flash_sale_vendor_discount_amount, config('round_up_to_digit'));

            $order->order_amount = $order->order_amount + $order->dm_tips + $order->additional_charge + $order->extra_packaging_amount;
            if ($request->payment_method == 'wallet' && $request->user->wallet_balance < $order->order_amount) {
                DB::rollBack();
                return response()->json([
                    'errors' => [
                        ['code' => 'order_amount', 'message' => translate('messages.Insufficient balance')]
                    ]
                ], 203);
            }
            if ($request->partial_payment && $request->user->wallet_balance > $order->order_amount) {
                DB::rollBack();
                return response()->json([
                    'errors' => [
                        ['code' => 'partial_payment', 'message' => translate('messages.Order amount must be greater than wallet amount')]
                    ]
                ], 203);
            }
            if (isset($module_wise_delivery_charge) && $request->payment_method == 'cash_on_delivery' && $module_wise_delivery_charge->pivot->maximum_cod_order_amount && $order->order_amount > $module_wise_delivery_charge->pivot->maximum_cod_order_amount) {
                DB::rollBack();
                return response()->json([
                    'errors' => [
                        ['code' => 'order_amount', 'message' => translate('Amount crossed maximum COD order amount')]
                    ]
                ], 406);
            }

            $order->save();

            if ($order->user_id && ($pro_offer['status'] ?? false)) {
                $amountSaved = match ($pro_offer['benefit']['type']) {
                    'discount'     => (float) ($proDiscount ?? 0),
                    'delivery_fee' => (float) ($proDeliverySavings ?? 0),
                    default        => 0.0,
                };

                $this->recordOrderProDiscount(
                    orderId: $order->id,
                    userId: (int) $order->user_id,
                    proOffer: $pro_offer,
                    amountSaved: $amountSaved,
                    originalDeliveryCharge: (float) $order->original_delivery_charge,
                    moduleType: $store?->module?->module_type ?? 'parcel',
                );

                $this->getProCustomerOffer(userId: $order->user_id, incrementCount: true);
            }

            if ($request->order_type !== 'parcel') {
                $taxMapCollection = collect($taxMap);
                foreach ($order_details as $key => $item) {
                    $order_details[$key]['order_id'] = $order->id;

                    if ($item['item_id']) {
                        $item_id = $item['item_id'];
                    } else {
                        $item_id = $item['item_campaign_id'];
                    }
                    $index = $taxMapCollection->search(function ($tax) use ($item_id) {
                        return $tax['product_id'] == $item_id;
                    });
                    if ($index !== false) {
                        $matchedTax = $taxMapCollection->pull($index);
                        $order_details[$key]['tax_status'] = $matchedTax['include'] == 1 ? 'included' : 'excluded';
                        $order_details[$key]['tax_amount'] = $matchedTax['totalTaxamount'];
                    }
                }

                app(OrderDetailService::class)->insertMany($order_details);

                app(BogoOrderService::class)->recordUsage(
                    $order,
                    (int) $order->store_id,
                    $request['contact_person_number'] ?? null
                );

                if (count($product_data) > 0) {
                    // BOGO/Bundle can put the same item on two cart lines (a "buy 1 get 1" of the
                    // identical product is the classic case). Each line held its own clone of the
                    // item read before any decrement happened, so decrementing and saving them
                    // independently made the second save's stale in-memory value overwrite the
                    // first's -- a bundle needing -2 stock only ever lost -1. Summing quantity per
                    // item (+variant) before decrementing applies one write per item instead.
                    $stockAdjustments = [];

                    foreach ($product_data as $item) {
                        $key = get_class($item['item']).'|'.$item['item']->getKey().'|'.($item['variant'] ?? '');

                        if (isset($stockAdjustments[$key])) {
                            $stockAdjustments[$key]['quantity'] += $item['quantity'];
                        } else {
                            $stockAdjustments[$key] = $item;
                        }
                    }

                    foreach ($stockAdjustments as $item) {
                        self::updateItemStock($item['item'], $item['quantity'], $item['variant'])?->save();
                        if ($item['item'] instanceof Item) {
                            self::updateFlashSaleStock($item['item'], $item['quantity'])?->save();
                        }
                    }
                }
                $store->increment('total_order');

                if (isset($carts)) {
                    $this->recordReelSales($carts, $order);
                }
            }
            if (count($orderTaxIds)) {
                \Modules\TaxModule\Services\CalculateTaxService::updateOrderTaxData(
                    orderId: $order->id,
                    orderTaxIds: $orderTaxIds,
                );
            }
            if (!isset($request->is_buy_now) || (isset($request->is_buy_now) && $request->is_buy_now == 0)) {
                foreach ($carts ?? [] as $cart) {
                    $cart?->delete();
                }
            }
            if ($request->user) {
                $customer = $request->user;
                $customer->zone_id = $order->zone_id;
                $customer->save();
                if ($request->payment_method == 'wallet') app(WalletTransactionService::class)->recordWalletTransaction($order->user_id, $order->order_amount, 'order_place', $order->id);

                if ($request->partial_payment) {
                    if ($request->user->wallet_balance <= 0) {
                        DB::rollBack();
                        return response()->json([
                            'errors' => [
                                ['code' => 'order_amount', 'message' => translate('messages.Insufficient balance for partial amount')]
                            ]
                        ], 203);
                    }
                    $p_amount = min($request->user->wallet_balance, $order->order_amount);
                    $unpaid_amount = $order->order_amount - $p_amount;
                    $order->partially_paid_amount = $p_amount;
                    $order->save();
                    app(WalletTransactionService::class)->recordWalletTransaction($order->user_id, $p_amount, 'partial_payment', $order->id);
                    app(OrderPaymentService::class)->createOrderPayment(orderId: $order->id, amount: $p_amount, paymentStatus: 'paid', paymentMethod: 'wallet');
                    app(OrderPaymentService::class)->createOrderPayment(orderId: $order->id, amount: $unpaid_amount, paymentStatus: 'unpaid', paymentMethod: $request->payment_method);
                }
            }
            if ($order->is_guest  == 0 && $order->user_id) {
                $this->createCashBackHistory($order->order_amount, $order->user_id, $order->id);
            }

            DB::commit();

            $this->sentOrderPlaceNotification($request, $order, $store);

            try {
                app(\App\Services\Order\MonthlyOrderReminderService::class)->scheduleForOrder(
                    $order,
                    $request->boolean('monthly_subscribe', false)
                );
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::error('Monthly reminder schedule failed (place_order)', ['order_id' => $order->id, 'error' => $e->getMessage()]);
            }

            return response()->json([
                'message' => translate('messages.Order placed successfully'),
                'order_id' => $order->id,
                'total_ammount' => $order->order_amount,
                'status' => $order->order_status,
                'created_at' => $order->created_at,
                'user_id' => (int) $order->user_id,
            ], 200);
        } catch (\Exception $exception) {
            Log::error('PlaceNewOrder', [$exception->getFile(), $exception->getLine(), $exception->getMessage()]);
            DB::rollBack();
            return response()->json([$exception], 403);
        }

        return response()->json([
            'errors' => [
                ['code' => 'order_time', 'message' => translate('messages.Failed to place order')]
            ]
        ], 403);
    }
    public function getCalculatedTax($request, $skipStockCheck = false, $skipPrescriptionCheck = false)
    {
        if (gettype($request->is_prescription) == "string") {
            $request->merge(['is_prescription' => $request->is_prescription == "true"]);
        }
        $product_price = $request->order_amount ?? 0;
        $coupon = null;
        $ref_bonus_amount = 0;
        $total_addon_price = 0;
        $store_discount_amount = 0;
        $flash_sale_admin_discount_amount = 0;
        $flash_sale_vendor_discount_amount = 0;
        $coupon_discount_amount = 0;
        $order_details = [];

        $order = new Order();
        $order->user_id = $request->user ? $request->user->id : $request['guest_id'];
        $order->is_guest = $request->user ? 0 : 1;
        $order->store_id = $request['store_id'];

        $additionalCharges = [];
        $settings = app(BusinessSettingService::class)->valuesFor([
            'additional_charge_status',
            'additional_charge',
            'extra_packaging_data',
        ]);

        $additional_charge_status  = $settings['additional_charge_status'] ?? null;

        $extra_packaging_data_raw  = $settings['extra_packaging_data'] ?? '';
        $extra_packaging_data      = json_decode($extra_packaging_data_raw, true) ?? [];

        if ($additional_charge_status == 1) {
        }

        if ($request->order_type !== 'parcel') {
            $store = app(StoreService::class)->findWithDiscountAndSubscription($request->store_id);

            $couponData = $this->getCouponData($request);
            if (data_get($couponData, 'status_code') === 403) {
                return response()->json([
                    'errors' => [
                        ['code' => data_get($couponData, 'code'), 'message' => data_get($couponData, 'message')]
                    ]
                ], data_get($couponData, 'status_code'));
            } else {
                $coupon = data_get($couponData, 'coupon');
            }

            if (!$request->is_prescription) {
                $extra_packaging_amount =  (!empty($extra_packaging_data) && $request?->extra_packaging_amount > 0 && $store && ($extra_packaging_data[$store->module->module_type] == '1') && ($store?->storeConfig?->extra_packaging_status == '1')) ? $store?->storeConfig?->extra_packaging_amount : 0;

                if ($extra_packaging_amount > 0) {
                    $additionalCharges['tax_on_packaging_charge'] =  $extra_packaging_amount;
                }

                $carts = app(CartService::class)->getCheckoutList(
                        $order->user_id,
                        $order->is_guest,
                        $request->store_id,
                        getModuleId($request->header('moduleId')),
                        (isset($request->is_buy_now) && $request->is_buy_now == 1 && $request->cart_id) ? $request->cart_id : null
                    );

                if (isset($request->is_buy_now) && $request->is_buy_now == 1) {
                    $carts = is_array($request['cart']) ? $request['cart'] : (json_decode($request['cart'], true) ?? []);
                }

                $order_details = $this->makeOrderDetails($carts, $request, $order, $store, $skipStockCheck, $skipPrescriptionCheck);
                if (data_get($order_details, 'status_code') === 403) {
                    return response()->json([
                        'errors' => [
                            ['code' => data_get($order_details, 'code'), 'message' => data_get($order_details, 'message')]
                        ]
                    ], data_get($order_details, 'status_code'));
                }

                $total_addon_price = $order_details['total_addon_price'];
                $product_price = $order_details['product_price'];
                $store_discount_amount = $order_details['store_discount_amount'];
                $flash_sale_admin_discount_amount = $order_details['flash_sale_admin_discount_amount'];
                $flash_sale_vendor_discount_amount = $order_details['flash_sale_vendor_discount_amount'];
                $order_details = $order_details['order_details'];
            }

            $coupon_discount_amount = $coupon ? app(CouponService::class)->calculateDiscount($coupon, $product_price + $total_addon_price - $store_discount_amount - $flash_sale_admin_discount_amount - $flash_sale_vendor_discount_amount) : 0;
        }

        $total_price = $product_price + $total_addon_price - $store_discount_amount - $flash_sale_admin_discount_amount - $flash_sale_vendor_discount_amount  - $coupon_discount_amount;

        if ($order->is_guest  == 0 && $order->user_id) {
            $user = app(UserService::class)->findWithOrderCount($order->user_id);
            $discount_data = Helpers::getCusromerFirstOrderDiscount(order_count: $user->orders_count, user_creation_date: $user->created_at,  refby: $user->ref_by, price: $total_price);
            if (data_get($discount_data, 'is_valid') == true &&  data_get($discount_data, 'calculated_amount') > 0) {
                $total_price = $total_price - data_get($discount_data, 'calculated_amount');
                $ref_bonus_amount = data_get($discount_data, 'calculated_amount');
            }
        }

        $pro_discount_preview = 0.0;

        if ($request->order_type == 'parcel') {
            // Unlike every other order type, a parcel quote's client never knows the real fee up
            // front -- it posts order_amount: 0 and expects the backend to price it -- so
            // `$total_price` above is still 0 here, not the real amount. Resolve it the same way
            // placement does (getZoneAndStore() + getDeliveryCharge()) before taxing it, or a
            // parcel quote taxes $0 regardless of the configured rate. Re-running the zone lookup
            // here duplicates the one buildCheckoutSummary() does right after for validation, but
            // keeps this fix contained to the tax step rather than threading a resolved zone
            // through every caller of getCalculatedTax().
            $parcelZone = data_get(
                $this->getZoneAndStore($request, $request->schedule_at ? Carbon::parse($request->schedule_at) : now()),
                'zone'
            );
            $parcelDeliveryCharge = 0.0;

            if ($parcelZone) {
                $parcelModuleId = getModuleId($request->header('moduleId'));
                $parcelModulePivot = $parcelZone->modules()->where('modules.id', $parcelModuleId)->first();
                $parcelDeliveryCharge = (float) data_get(
                    $this->getDeliveryCharge($request, $parcelZone, null, $parcelModulePivot, null, $parcelModuleId),
                    'delivery_charge',
                    0
                );
            }

            $pro_offer   = $this->getProCustomerOffer($order->user_id, false, true, 'parcel');
            $proDelivery = $this->applyProCustomerDeliveryFee(
                $pro_offer,
                $parcelDeliveryCharge,
                $parcelDeliveryCharge,
                null,
                'parcel',
            );
            $total_price = $proDelivery['delivery_charge'];
        } else {
            $proApply = $this->applyProCustomerDiscount(
                $order->user_id,
                $request->is_prescription == false ? $product_price + $total_addon_price : $product_price,
                $request->is_prescription == false ? $total_price : $product_price,
                $store?->module?->module_type ?? 'parcel',
            );
            $pro_discount_preview = $proApply['discount'];
            $total_price          = $proApply['total_price'];
        }

        $totalDiscount = $store_discount_amount + $flash_sale_admin_discount_amount + $flash_sale_vendor_discount_amount  + $coupon_discount_amount +  $ref_bonus_amount + $pro_discount_preview;

        if ($request->order_type != 'parcel' && $request->is_prescription == false) {
            $finalCalculatedTax =  Helpers::getFinalCalculatedTax($order_details, $additionalCharges, $totalDiscount, $total_price, $order->store_id, false);
            $data = [
                'tax_amount' => $finalCalculatedTax['tax_amount'],
                'tax_status' => $finalCalculatedTax['tax_status'],
                'tax_included' => $finalCalculatedTax['tax_included'],

                'total_price' => round((float) $total_price, config('round_up_to_digit')),

                'pro_discount' => round((float) $pro_discount_preview, config('round_up_to_digit')),

                // The store-wide cut, and which promotion it came from. This is the ONLY place a
                // happy hour is shown: every item payload reports its base price while a window is
                // open, because the rate applies to the basket and carries its own minimum spend,
                // so it cannot be promised per item. `discount_source` is what lets the checkout
                // label the row "Happy Hour discount" rather than a generic one -- the two are
                // mutually exclusive and the amount alone cannot tell them apart.
                'store_discount_amount' => round((float) $store_discount_amount, config('round_up_to_digit')),
                'discount_source' => $store_discount_amount > 0
                    ? (Helpers::get_store_discount($store ?? null)['source'] ?? null)
                    : null,

                'ref_bonus_amount' => round((float) $ref_bonus_amount, config('round_up_to_digit')),
            ];
        }

        if ($request->order_type == 'parcel' || $request->is_prescription == true) {
            if ($request->order_type == 'parcel') {
                $productIds[] = [
                    'id' => 1,
                    'original_price' => $total_price,
                    'quantity' => 1,
                    'category_id' =>  $request->parcel_category_id,
                    'discount' => 0,
                    'discount_type' => '',
                    'after_discount_final_price' => $total_price,
                    'is_campaign_item' => false,
                ];
            }

            $finalCalculatedTax =  \Modules\TaxModule\Services\CalculateTaxService::getCalculatedTax(
                amount: $total_price,
                productIds: $productIds ?? [],
                taxPayer: $request->is_prescription == true ? 'prescription' : 'parcel',
                storeData: true,
                additionalCharges: $additionalCharges,
                addonIds: [],
                orderId: null,
                storeId: null
            );
            $data = [
                'tax_amount' => $finalCalculatedTax['totalTaxamount'],
                'tax_included' => $finalCalculatedTax['include'],
                'tax_status' => $finalCalculatedTax['include'] ?  'included' : 'excluded'
            ];
        }

        return response()->json($data, 200);
    }
    public function setPosCalculatedTax($store, $storeData = false)
    {
        $additionalCharges = [];
        $settings = app(BusinessSettingService::class)->valuesFor([
            'additional_charge_status',
            'additional_charge',
            'extra_packaging_data',
        ]);

        $carts = session()->get('cart');
        $order_details = $this->makePosOrderDetails($carts, null, $store);

        if (data_get($order_details, 'status_code') === 403) {
            return response()->json([
                'errors' => [
                    ['code' => data_get($order_details, 'code'), 'message' => data_get($order_details, 'message')]
                ]
            ], data_get($order_details, 'status_code'));
        }

        $total_addon_price = $order_details['total_addon_price'];
        $product_price = $order_details['product_price'];
        $store_discount_amount = $order_details['store_discount_amount'];
        $flash_sale_admin_discount_amount = $order_details['flash_sale_admin_discount_amount'];
        $flash_sale_vendor_discount_amount = $order_details['flash_sale_vendor_discount_amount'];
        $order_details = $order_details['order_details'];

        if(session()->get('extra_discount_type')){
            $this->updateExtraDiscount(session()->get('extra_discount_type'),session()->get('extra_discount'));
        }

        $extra_discount_amount=session()->get('extra_discount_amount') ?? 0;

        $totalDiscount = $store_discount_amount + $flash_sale_admin_discount_amount + $flash_sale_vendor_discount_amount + $extra_discount_amount;

        $price = $product_price + $total_addon_price - $totalDiscount ?? 0;

        $pos_customer_id = session()->get('customer_id');
        $pos_customer    = $pos_customer_id ? app(UserService::class)->find($pos_customer_id) : null;
        $isPosProCustomer = $pos_customer && (int) $pos_customer->pro_status === 1;
        if ($isPosProCustomer) {
            $proApply = $this->applyProCustomerDiscount(
                (int) $pos_customer->id,
                $product_price + $total_addon_price,
                $price,
                $store?->module?->module_type,
            );
            if ($proApply['discount'] > 0) {
                $totalDiscount += $proApply['discount'];
                $price          = $proApply['total_price'];
            }
            session()->put('pos_pro_discount', (float) $proApply['discount']);
            session()->put('pos_pro_benefit_type', $proApply['offer']['benefit']['type'] ?? null);
            session()->put('pos_pro_delivery_offer_type', $proApply['offer']['benefit']['offer_type'] ?? null);
            session()->put('pos_pro_delivery_percentage', $proApply['offer']['benefit']['charge_discount_percentage'] ?? null);
            session()->put('pos_pro_min_order_amount', $proApply['offer']['benefit']['min_order_amount'] ?? null);
            session()->put('pos_pro_min_order_status', $proApply['offer']['benefit']['min_order_status'] ?? 0);
        } else {
            session()->forget(['pos_pro_discount', 'pos_pro_benefit_type', 'pos_pro_delivery_offer_type', 'pos_pro_delivery_percentage', 'pos_pro_min_order_amount', 'pos_pro_min_order_status']);
        }

        $finalCalculatedTax =  Helpers::getFinalCalculatedTax(
            $order_details,
            $additionalCharges,
            $totalDiscount,
            $price,
            $store->id,
            $storeData
        );

        session()->put('tax_amount', $finalCalculatedTax['tax_amount']);
        session()->put('tax_included', $finalCalculatedTax['tax_included']);

        $data = [
            'tax_amount' => $finalCalculatedTax['tax_amount'],
            'tax_status' => $finalCalculatedTax['tax_status'],
            'tax_included' => $finalCalculatedTax['tax_included'],
        ];
        return response()->json($data, 200);
    }
    public function setOrderEditCalculatedTax($store, $storeData = false, $order_id = null)
    {
        $originalDetailQtys = [];
        if ($order_id) {
            $order = app(OrderService::class)->findWithDetails($order_id);
            if ($order) {
                $originalDetailQtys = $order->details->pluck('quantity', 'id')->all();
            }
        }
        $coupon = null;
        $additionalCharges = [];
        $settings = app(BusinessSettingService::class)->valuesFor([
            'additional_charge_status',
            'additional_charge',
            'extra_packaging_data',
        ]);

        $additional_charge_status  = $settings['additional_charge_status'] ?? null;

        if ($additional_charge_status == 1) {
        }

        $carts = session()->get('order_cart');

        $order_details = $this->makeEditOrderDetails($carts, null, $store, $originalDetailQtys);

        if (data_get($order_details, 'status_code') === 403) {
            return response()->json([
                'errors' => [
                    ['code' => data_get($order_details, 'code'), 'message' => data_get($order_details, 'message')]
                ]
            ], data_get($order_details, 'status_code'));
        }

        $total_addon_price = $order_details['total_addon_price'];
        $product_price = $order_details['product_price'];
        $store_discount_amount = $order_details['store_discount_amount'];
        $flash_sale_admin_discount_amount = $order_details['flash_sale_admin_discount_amount'];
        $flash_sale_vendor_discount_amount = $order_details['flash_sale_vendor_discount_amount'];

        $discount_on_product_by= $order_details['discount_on_product_by'];
        $order_details = $order_details['order_details'];
        if ($order?->coupon_code) {
            $coupon = app(CouponService::class)->findByCode($order->coupon_code);
        }

        $coupon_discount_amount = $coupon ? app(CouponService::class)->calculateDiscount($coupon, $product_price + $total_addon_price - $store_discount_amount) : 0;

        $totalDiscount = $store_discount_amount + $flash_sale_admin_discount_amount + $flash_sale_vendor_discount_amount + $coupon_discount_amount;

        $price = $product_price + $total_addon_price - $totalDiscount;

        $editCustomerId = ($order ?? null) ? $order->user_id : null;
        $isProCustomerPreview = $editCustomerId
            && app(UserService::class)->isProCustomer($editCustomerId);
        if ($isProCustomerPreview) {
            $proApply = $this->applyProCustomerDiscount(
                (int) $editCustomerId,
                $product_price + $total_addon_price,
                $price,
                $store?->module?->module_type,
            );
            if ($proApply['discount'] > 0) {
                $totalDiscount += $proApply['discount'];
                $price          = $proApply['total_price'];
            }
        }

        $finalCalculatedTax =  Helpers::getFinalCalculatedTax(
            $order_details,
            $additionalCharges,
            $totalDiscount,
            $price,
            $store->id,
            $storeData
        );
        session()->put('edit_tax_amount', $finalCalculatedTax['tax_amount']);
        session()->put('edit_tax_included', $finalCalculatedTax['tax_included']);
        session()->put('discount_on_product_by_session', $discount_on_product_by == 'admin' ? 'store_discount' : 'vendor');

        $data = [
            'tax_amount' => $finalCalculatedTax['tax_amount'],
            'tax_status' => $finalCalculatedTax['tax_status'],
            'tax_included' => $finalCalculatedTax['tax_included'],
            'store_discount_amount'=>$store_discount_amount,
            'discount_on_product_by'=>$discount_on_product_by == 'admin' ? 'store_discount' : 'vendor',
        ];
        return response()->json($data, 200);
    }
    public function getSurgePrice($zoneId, $moduleId, $datetime) {
        $data = $this->getSurgePriceValue($zoneId, $moduleId, $datetime);

        return response()->json($data, 200);
    }

    public function getSurgePriceValue($zoneId, $moduleId, $datetime)
    {
        return app(SurgePriceService::class)->resolve($zoneId, $moduleId, $datetime);
    }
    public function makeEditOrderLogs(int $orderId, string $log, string $editedBy): void
    {
        app(OrderEditLogService::class)->record($orderId, $log, $editedBy);
    }
    public function collectEditLogs($cart, array $originalDetailQtys = []): array
    {
        $logs = [];
        foreach ($cart as $c) {
            if (isset($c['status']) && $c['status'] === false) {
                $logs[] = 'delete_item';
            } elseif (!isset($c->id)) {
                $logs[] = 'add_new_item';
            } elseif (isset($originalDetailQtys[$c->id]) && (int) $originalDetailQtys[$c->id] !== (int) $c->quantity) {
                $logs[] = 'edited_item_quantity';
            }
        }
        return $logs;
    }
    protected function refreshPosAddressDeliveryFee($store, ?int $userId): void
    {
        if (!$store) {
            return;
        }
        $address = session()->get('address');
        if (!is_array($address)) {
            return;
        }
        $distance = (float) ($address['distance'] ?? 0);
        $areaId = $address['area_id'] ?? null;
        $zipCodeId = $address['zip_code_id'] ?? null;
        // Previously this bailed out whenever distance <= 0 and no area/zip was picked, on the
        // assumption that those were the only three things a fee could be priced from. That
        // missed a fourth: a `fixed_amount` delivery rule (or a pivot's flat `fixed` charge type)
        // prices the whole zone at one amount regardless of distance, area or zip. Whenever the
        // map's distance lookup failed or measured 0 (a Google Maps hiccup, or the pin landing on
        // the store's own coordinates), that guard skipped the recompute entirely and left the
        // address's delivery_fee at whatever the client had last posted -- 0 on a first save --
        // silently under-charging a store like Organic Shop that is priced by a fixed-amount
        // rule and reading as "free delivery" to anyone looking at the total.
        //
        // DeliveryChargeService::quote() already floors every pricing branch correctly at
        // distance 0 (a flat/fixed rate ignores distance entirely; a per-km rate floors at its
        // configured minimum), and an area/zip-priced zone's address save is already refused
        // upstream in addDeliveryInfo() when neither was picked -- so there is no longer a case
        // where recomputing here can produce a worse answer than skipping it.
        // $userId is accepted (and forwarded by every caller) to document that this recomputes
        // for whichever customer is currently selected, but calculatePosDeliveryFee() itself no
        // longer needs it -- see that method's own docblock for why a Pro discount is resolved
        // separately now instead of baked into this quote.
        $delivery_calc = $this->calculatePosDeliveryFee(
            $store,
            $distance,
            $areaId,
            $zipCodeId,
        );
        // The plain base+surge quote, undiscounted -- this is what the address modal and the
        // delivery-type picker both read as "the fee," and neither should show a Pro customer's
        // discount before the admin has even chosen Standard/Express/Slightly Delay. A Pro
        // discount is applied once, as the LAST step, only in the cart summary
        // (PosCartSummary::build() / admin's _cart.blade.php) and at order placement
        // (POSController::place_order(), after applySaverToOrder()).
        $address['delivery_fee'] = (float) $delivery_calc['original_delivery_charge'];
        $address['surge_note']  = $delivery_calc['surge_note'];
        session()->put('address', $address);
    }
    private function createCashBackHistory($order_amount, $user_id, $order_id)
    {
        $cashBack = app(CashBackService::class)->calculateForCustomer(amount: $order_amount, customerId: $user_id);

        if (data_get($cashBack, 'calculated_amount') > 0) {
            $CashBackHistory = new CashBackHistory();
            $CashBackHistory->user_id = $user_id;
            $CashBackHistory->order_id = $order_id;
            $CashBackHistory->calculated_amount = data_get($cashBack, 'calculated_amount');
            $CashBackHistory->cashback_amount = data_get($cashBack, 'cashback_amount');
            $CashBackHistory->cash_back_id = data_get($cashBack, 'id');
            $CashBackHistory->cashback_type = data_get($cashBack, 'cashback_type');
            $CashBackHistory->min_purchase = data_get($cashBack, 'min_purchase');
            $CashBackHistory->max_discount = data_get($cashBack, 'max_discount');
            $CashBackHistory->save();

            $CashBackHistory?->order()->update([
                'cash_back_id' => $CashBackHistory->id,
            ]);
        }

        return true;
    }
    private function recordReelSales($carts, $order): void
    {
        try {
            $reelService = $this->reelEngagementService();

            if (!$reelService) {
                return;
            }

            foreach ($carts ?? [] as $cart) {
                $reelId = (int) data_get($cart, 'reel_id');

                if ($reelId <= 0) {
                    continue;
                }

                $reelService->recordOrderSale(
                    reelId: $reelId,
                    amount: (float) (data_get($cart, 'price', 0) * data_get($cart, 'quantity', 0)),
                    userId: $order->is_guest ? null : (int) $order->user_id,
                    guestId: $order->is_guest ? (string) $order->user_id : null,
                );
            }
        } catch (\Throwable $exception) {
            \Illuminate\Support\Facades\Log::error('Reel sale tracking failed', [
                'order_id' => $order->id ?? null,
                'error' => $exception->getMessage(),
            ]);
        }
    }
    private function validationCheck($request)
    {
        $validationError = match (true) {
            $request->is_guest && !app(BusinessSettingService::class)->value('guest_checkout_status') => [
                'code'    => 'is_guest',
                'message' => translate('messages.Guest order is not active'),
                'status_code' => 403,
            ],

            $request->order_type === 'delivery' && !app(BusinessSettingService::class)->value('home_delivery_status') => [
                'code'    => 'order_type',
                'message' => translate('messages.Home delivery is not active'),
                'status_code' => 403,
            ],

            $request->order_type === 'take_away' && !app(BusinessSettingService::class)->value('takeaway_status') => [
                'code'    => 'order_type',
                'message' => translate('messages.Take away is not active'),
                'status_code' => 403,
            ],

            $request->partial_payment && !app(BusinessSettingService::class)->value('partial_payment_status') => [
                'code'    => 'order_method',
                'message' => translate('messages.Partial payment is not active'),
                'status_code' => 403,
            ],

            $request->payment_method === 'offline_payment' && !app(BusinessSettingService::class)->value('offline_payment_status') => [
                'code'    => 'offline_payment_status',
                'message' => translate('messages.Offline payment for the order not available at this time'),
                'status_code' => 403,
            ],

            $request->payment_method === 'digital_payment' && !app(BusinessSettingService::class)->value('digital_payment')['status'] => [
                'code'    => 'digital_payment',
                'message' => translate('messages.Digital payment for the order not available at this time'),
                'status_code' => 403,
            ],
            $request->payment_method === 'cash_on_delivery' && !app(BusinessSettingService::class)->value('cash_on_delivery')['status'] => [
                'code'    => 'digital_payment',
                'message' => translate('messages.Cash on delivery for the order not available at this time'),
                'status_code' => 403,
            ],

            default => null,
        };

        if ($validationError) {
            return $validationError;
        }

        return null;
    }

    private function zonePaymentValidationCheck($request, $zone): ?array
    {
        $method = $request->payment_method;

        if (! $zone || ! in_array($method, ['cash_on_delivery', 'digital_payment', 'offline_payment'], true)) {
            return null;
        }

        if (app(ZoneService::class)->allowedPaymentMethods($zone)[$method]) {
            return null;
        }

        return [
            'code' => $method,
            'message' => match ($method) {
                'cash_on_delivery' => translate('messages.Cash on delivery is not available in this area'),
                'digital_payment' => translate('messages.Digital payment is not available in this area'),
                default => translate('messages.Offline payment is not available in this area'),
            },
            'status_code' => 403,
        ];
    }

    private function getZoneAndStore($request, $schedule_at)
    {
        if ($request->latitude && $request->longitude) {
            if ($request->order_type == 'parcel') {
                // Pickup can be anywhere the parcel module operates, not just the zone(s) the
                // customer's own app session is currently browsing in -- so unlike every other
                // order type, this must search across all zones, not just the `zoneId` header.
                $zone = app(ZoneService::class)->findSmallestContainingInZones(null, $request->latitude, $request->longitude, 'parcel');

                $receiver_zone_id =  json_decode($request->receiver_details, true)['zone_id'];
                $receiverZone = app(ZoneService::class)->findContaining($receiver_zone_id, json_decode($request->receiver_details, true)['latitude'], json_decode($request->receiver_details, true)['longitude']);
                if (!$receiverZone || !$zone) {
                    return [
                        'status_code' => 403,
                        'code' => 'receiverZone',
                        // Same wording DeliveryRuleService::coverageSelectionError() uses for its
                        // own "picked a real place this module doesn't operate in" case -- one
                        // sentence for "we don't serve there" everywhere it's said, rather than
                        // this generic "Out of coverage" naming nothing the customer can act on.
                        'message' => translate('messages.This_service_is_not_available_in_the_desired_location.'),
                    ];
                }
            } else {
                $store = app(StoreService::class)->findWithOpenStateAt($request->store_id, $schedule_at);
                if ($store) {
                    $zone = app(ZoneService::class)->findContaining($store->zone_id, $request->latitude, $request->longitude);
                }
            }
        }

        $resolvedByCoordinates = (bool) ($request->latitude && $request->longitude);

        if (! $resolvedByCoordinates && $request->order_type !== 'parcel' && $request->store_id) {
            $store = app(StoreService::class)->findWithOpenStateAt($request->store_id, $schedule_at);
            $zone = $store ? Zone::find($store->zone_id) : null;
        }

        return ['zone' => $zone ?? null, 'store' => $store ?? null];
    }

    private function zoneAndStoreValidationCheck($request, $schedule_at, $zone, $store, bool $allowClosed = false)
    {
        $store_sub = $store?->store_sub;
        $validationError = match (true) {
            !$zone => [
                'code'    => 'zone',
                'message' => translate('messages.Out of coverage area'),
                'status_code' => 403,
            ],
            default => null,
        };

        if ($request->order_type !== 'parcel') {
            $validationError = match (true) {
                !$store => [
                    'code'    => 'store',
                    'message' => translate('No data found'),
                    'status_code' => 403,
                ],
                $request->schedule_at && $schedule_at < now() => [
                    'code'    => 'order_time',
                    'message' => translate('messages.You can not schedule a order in past'),
                    'status_code' => 403,
                ],
                $request->schedule_at && !$store->schedule_order => [
                    'code'    => 'schedule_at',
                    'message' => translate('messages.Schedule order not available'),
                    'status_code' => 403,
                ],
                ! $allowClosed && $store->open == false => [
                    'code'    => 'order_time',
                    'message' => translate('messages.Store is closed at order time'),
                    'status_code' => 403,
                ],
                $store->store_business_model == 'unsubscribed' => [
                    'code'    => 'order-confirmation-model',
                    'message' => translate('messages.Sorry the store is unable to take any order !'),
                    'status_code' => 403,
                ],

                $store->is_valid_subscription && $store_sub && $store_sub->max_order != "unlimited" && $store_sub->max_order <= 0 => [
                    'code'    => 'order-confirmation-error',
                    'message' => translate('messages.Sorry the store is unable to take any order !'),
                    'status_code' => 403,
                ],
                default => null,
            };
        }

        if ($validationError) {
            return $validationError;
        }

        return null;
    }
    private function getCouponData($request)
    {
        if ($request['coupon_code']) {
            $coupon = app(CouponService::class)->findActiveByCode($request['coupon_code']);

            if (!$coupon) {
                return [
                    'status_code' => 403,
                    'code' => 'coupon',
                    'message' => translate('messages.Coupon expire'),
                ];
            }

            $status = $request->is_guest
                ? app(CouponService::class)->validateForGuest($coupon, $request['store_id'])
                : app(CouponService::class)->validateForCustomer($coupon, $request->user->id, $request['store_id']);

            $validationError = match ($status) {
                407 => [
                    'status_code' => 403,
                    'code' => 'coupon',
                    'message' => translate('messages.Coupon expire'),
                ],
                408 => [
                    'status_code' => 403,
                    'code' => 'coupon',
                    'message' => translate('messages.You are not eligible for this coupon'),
                ],
                409 => [
                    'status_code' => 403,
                    'code' => 'coupon',
                    'message' => translate('messages.Coupon not valid for this zone'),
                ],
                406 => [
                    'status_code' => 403,
                    'code' => 'coupon',
                    'message' => translate('messages.Coupon usage limit over'),
                ],
                404 => [
                    'status_code' => 403,
                    'code' => 'coupon',
                    'message' => translate('No data found'),
                ],
                410 => [
                    'status_code' => 403,
                    'code' => 'coupon',
                    'message' => translate('messages.Free delivery already covered by pro'),
                ],
                default => null,
            };

            if ($validationError) {
                return $validationError;
            }

            $coupon_created_by = $coupon->created_by;

            if ($coupon->coupon_type === 'free_delivery') {
                $delivery_charge = 0;
                $free_delivery_by = $coupon_created_by;
                $coupon_created_by = null;
            }
        }

        return [
            'coupon' => $coupon ?? null,
            'coupon_created_by' => $coupon_created_by ?? null,
            'delivery_charge' => $delivery_charge ?? null,
            'free_delivery_by' => $free_delivery_by ?? null,
        ];
    }

    private function getDeliveryCharge($request, $zone, $store, $module_wise_delivery_charge, $delivery_charge, $moduleId)
    {
        $schedule_at = $request->schedule_at ? Carbon::parse($request->schedule_at) : now();
        $surge = $this->getSurgePriceValue($zone->id, $moduleId, $schedule_at);
        $isParcel = $request->order_type === 'parcel';

        if (! $isParcel) {
            if ($request['order_type'] === 'take_away') {
                return [
                    'vehicle_id' => null,
                    'original_delivery_charge' => 0,
                    'delivery_charge' => 0,
                ];
            }

            if ($store?->sub_self_delivery != 1 && ! $module_wise_delivery_charge) {
                return [
                    'vehicle_id' => null,
                    'original_delivery_charge' => 0,
                    'delivery_charge' => $delivery_charge,
                ];
            }
        }

        $quote = app(DeliveryChargeService::class)->quote([
            'order_type' => $request->order_type,
            'distance' => (float) ($request->distance ?? 0),
            'store' => $store,
            'module_zone_pivot' => $module_wise_delivery_charge?->pivot,

            'area_id' => $request->area_id,
            'zip_code_id' => $request->zip_code_id,
            // The three ADDITIVE parcel tiers. Each is optional: a (zone, module) whose rule does
            // not price by weight or size never offers a pick, and the engine ignores an id
            // posted at a tier whose switch is off.
            'parcel_category_id' => $request->parcel_category_id,
            'weight_id' => $request->weight_id,
            'dimension_id' => $request->dimension_id,
            'preset_delivery_charge' => $delivery_charge,
            'surge' => $surge,

        ]);

        $original_delivery_charge = $quote['original_delivery_charge'];
        $vehicle_id = $quote['vehicle_id'];

        // Parcel takes the quote like every other order type.
        //
        // It used to be excluded here, under a comment reading "preserved until S5 wires parcel
        // onto delivery rules, which is when it starts quoting like every other order type". S5
        // and S14 both landed — the engine prices a parcel from the active rule and adds the
        // category, weight and dimension tiers on top — but this line was never removed, so
        // every one of those amounts was computed and then thrown away. `$delivery_charge` is
        // null for a parcel (placement skips coupons for it entirely), which the caller reads as
        // 0.00: the checkout summary quoted, and placement stored, ZERO delivery on every parcel
        // order.
        //
        // A preset from a free-delivery coupon is still honoured — the engine applies it inside
        // quote() and returns it here, so taking the quote does not lose it.
        $delivery_charge = $quote['delivery_charge'];

        $saver = $this->resolveSaverDeliveryType($request, $zone, $store, $module_wise_delivery_charge, $moduleId, (float) ($delivery_charge ?? 0));

        return [
            'delivery_charge' => $delivery_charge,
            'original_delivery_charge' => $original_delivery_charge ?? 0,
            'vehicle_id' => $vehicle_id ?? null,
            'delivery_type' => $saver['delivery_type'],
            'delivery_type_charge' => $saver['delivery_type_charge'],

            'base_delivery_charge' => $quote['base_delivery_charge'] ?? 0,
            'surge_amount' => $quote['surge_amount'] ?? 0,
            // The minimum whichever branch of step 2 actually priced this order: the active
            // delivery rule's Minimum Delivery Charge, the module_zone pivot's where the zone has
            // no rule, or a self-delivery store's own. Passed through rather than resolved again
            // by whoever needs it -- the engine is the only thing that knows which branch ran, and
            // a caller asking a different resolver would report a floor this order is not held to.
            'floor' => $quote['floor'] ?? 0,
        ];
    }
    private function resolveSaverDeliveryType($request, $zone, $store, $module_wise_delivery_charge, $moduleId, float $delivery_charge): array
    {
        $default = ['delivery_type' => 'standard', 'delivery_type_charge' => 0.0];

        if (!$zone || !$module_wise_delivery_charge) {
            return $default;
        }
        if ($store?->sub_self_delivery == 1) {
            return $default;
        }
        if (($request->order_type ?? null) !== 'delivery') {
            return $default;
        }
        // No `$delivery_charge <= 0` gate here on purpose: the base fee being zero — because free
        // delivery applied, or because the rule's own minimum is zero — says nothing about
        // whether express or slightly-delay should be offered. Express is a flat premium that
        // does not depend on the base at all (TC_445); slightly-delay's own reduction below is
        // already capped at max(0, $delivery_charge - $floor), so a zero base yields a $0
        // reduction on its own without needing a separate guard here.
        // Kept for the charge floor further down, no longer asked whether the option is ON --
        // that column belongs to the predecessor feature and nothing writes it, so placement
        // refused to charge for an Express the checkout had just offered.
        $pivot = $module_wise_delivery_charge->pivot ?? null;
        if (!$pivot) {
            return $default;
        }

        $requested = $request->delivery_type ?? null;
        if (!in_array($requested, [ModuleZoneDeliveryOption::TYPE_EXPRESS, ModuleZoneDeliveryOption::TYPE_SLIGHTLY_DELAY], true)) {
            return $default;
        }

        $option = app(ModuleZoneDeliveryOptionService::class)->findForModuleZoneType($moduleId, $zone->id, $requested);
        if (!$option) {
            return $default;
        }

        if ($requested === ModuleZoneDeliveryOption::TYPE_EXPRESS) {
            $charge = (float) ($option->extra_charge ?? 0);
            return ['delivery_type' => ModuleZoneDeliveryOption::TYPE_EXPRESS, 'delivery_type_charge' => max(0, $charge)];
        }

        $reduce = (float) ($option->reduce_charge ?? 0);
        $floor = (float) (app(DeliveryChargeService::class)->deliveryFloor($zone->id, $moduleId, $pivot) ?? 0);
        $maxReducible = max(0, $delivery_charge - $floor);
        $applied = min($reduce, $maxReducible);

        return ['delivery_type' => ModuleZoneDeliveryOption::TYPE_SLIGHTLY_DELAY, 'delivery_type_charge' => $applied];
    }
    private function sentOrderPlaceNotification($request, $order, $store)
    {
        $payments = $order->payments()->where('payment_method', 'cash_on_delivery')->exists();
        try {
            if (!in_array($order->payment_method, ['digital_payment', 'partial_payment', 'offline_payment'])  || $payments) {
                if ($store?->is_valid_subscription == 1 && $store?->store_sub?->max_order != "unlimited" && $store?->store_sub?->max_order > 0) {
                    $store?->store_sub?->decrement('max_order', 1);
                }
                SendNotification::sendOrderNotifications($order);

                $email = $order->is_guest == 1 ? $request->contact_person_email : $request->user?->email;
                $name = $order->is_guest == 1 ? $request->contact_person_name : $request->user?->f_name;
                if (config('mail.status') && $email && $order->order_status == 'pending') {
                    if ($order->order_status == 'pending' && SendNotification::canSendMail('place_order_mail_status_user', 'customer', 'customer_order_notification')) {
                        SendNotification::mail($email, new PlaceOrder($order->id));
                    }
                    if (BusinessRules::deliveryVerificationEnabled() && SendNotification::canSendMail('order_verification_mail_status_user', 'customer', 'customer_delivery_verification')) {
                        SendNotification::mail($email, new OrderVerificationMail($order->otp, $name));
                    }
                }
            }
        } catch (\Exception $exception) {
            Log::error('place order notification error', [$exception->getFile(), $exception->getLine(), $exception->getMessage()]);
        }
        return true;
    }
    private function makeOrderDetails($carts, $request, $order, $store, $skipStockCheck = false, $skipPrescriptionCheck = false)
    {
        $total_addon_price = 0;
        $product_price = 0;
        $store_discount_amount = 0;
        $flash_sale_vendor_discount_amount = 0;
        $flash_sale_admin_discount_amount = 0;
        $product_data = [];
        $order_details = [];
        $discount_type = '';
        $discount_on_product_by = 'vendor';

        $bogoService = app(BogoOrderService::class);
        $bogo_frozen_prices = $bogoService->frozenPrices($carts, (int) $order->store_id);
        $bundleService = app(BundleOrderService::class);
        $bundle_frozen_prices = $bundleService->frozenPrices($carts, (int) $order->store_id);

        $bogo_free_value = 0;

        $isHappyHour = $bogoService->isHappyHourRunning($store);
        $discountable_price = 0;
        foreach (($carts ?? []) as $c) {
            $variations = [];
            $isCampaign = false;
            if ($c['item_type'] === 'App\Models\ItemCampaign' || $c['item_type'] === 'AppModelsItemCampaign') {
                $product = app(ItemCampaignService::class)->findActiveWithModule($c['item_id']);
                $isCampaign = true;
            } else {
                $product = app(ItemService::class)->findActiveWithModule($c['item_id']);
            }
            if ($product) {
                if ($product->store_id != $order->store_id) {
                    return [
                        'status_code' => 403,
                        'code' => 'different_stores',
                        'message' => translate('messages.Please select items from the same store'),
                    ];
                }

                if (!$skipPrescriptionCheck && $product?->pharmacy_item_details?->is_prescription_required == '1' && empty($request->file('order_attachment'))) {
                    return [
                        'status_code' => 403,
                        'code' => 'prescription',
                        'message' => translate('messages.Prescription is required for this order'),
                    ];
                }

                if ($product?->maximum_cart_quantity && $c['quantity'] > $product?->maximum_cart_quantity) {
                    return [
                        'status_code' => 403,
                        'code' => 'quantity',
                        'message' => translate('messages.Maximum cart quantity limit over'),
                    ];
                }

                $foodVariation = false;
                if ($product?->module?->module_type == 'food') {
                    $foodVariation = true;
                    $product_variations = Helpers::decodeJsonToArray($product->food_variations);

                    if ($product_variations && count($product_variations)) {
                        $variation_data = Helpers::get_varient($product_variations, $c['variation']);
                        $price = $product['price'] + $variation_data['price'];
                        $variations = $variation_data['variations'];
                    } else {
                        $price = $product['price'];
                    }
                } else {
                    if (count(json_decode($product['variations'], true)) > 0 && count($c['variation']) > 0) {
                        $variant_data = Helpers::variation_price($product, json_encode($c['variation']));
                        $price = $variant_data['price'];
                        $stock = $variant_data['stock'];
                    } else {
                        $price = $product['price'];
                        $stock = $product?->stock;
                    }

                    if (config('module.' . $product->module->module_type)['stock']) {
                        if (!$skipStockCheck && $c['quantity'] > $stock) {
                            return [
                                'status_code' => 403,
                                'code' => 'stock',
                                'message' => $isCampaign ? $product?->title : $product?->name . ' ' . translate('messages.Is out of stock')
                            ];
                        }
                        $product_data[] = [
                            'item' => clone $product,
                            'quantity' => $c['quantity'],
                            'variant' => Helpers::variationType($c['variation'][0] ?? null)
                        ];
                    }
                }

                $product = $this->productPayload($product);
                [$lineAddonIds, $lineAddonQtys] = $this->itemOwnAddons($product, $c['add_on_ids'], $c['add_on_qtys']);
                $addon_data = Helpers::calculate_addon_price(app(AddonService::class)->getByIds($lineAddonIds), $lineAddonQtys);

                $bogo_unit_worth = null;
                $isBogoLine = (bool) data_get($c, 'bogo_group_id');
                $isBundleLine = (bool) data_get($c, 'bundle_group_id');

                if ($isBundleLine) {
                    $price = $bundleService->linePrice($bundle_frozen_prices, $c, $price);
                    $addon_data['total_add_on_price'] = 0;
                }

                if ($isBogoLine) {
                    [$price, $gave_away, $bogo_unit_worth] = app(BogoOrderService::class)
                        ->linePrice($bogo_frozen_prices, $c, $price);

                    $bogo_free_value += $gave_away;

                    $addon_data['total_add_on_price'] = 0;
                }

                $product_discount = Helpers::product_discount_calculate($product, $price, $store, false);

                if ($isBogoLine && ! $bogoService->isDiscountable($c, $isHappyHour)) {
                    $product_discount['discount_amount'] = 0;
                    $product_discount['discount_percentage'] = 0;

                    $product = $bogoService->clearUnappliedRates($product);
                }

                if ($isBundleLine) {
                    $product_discount = $bundleService->clearLineDiscount($product_discount);
                    $product = $bogoService->clearUnappliedRates($product);
                }

                $discount_type = $product_discount['discount_type'];

                $or_d = $bundleService->detailColumns($c) + app(BogoOrderService::class)->detailColumns($c, $bogo_unit_worth) + [
                    'item_id' => $isCampaign ?  null : $c['item_id'],
                    'item_campaign_id' => $isCampaign ? $c['item_id'] : null,
                    'item_details' => json_encode($product),
                    'quantity' => $c['quantity'],
                    'price' => round($price, config('round_up_to_digit')),

                    'category_id' => collect(Helpers::decodeJsonToArray(data_get($product, 'category_ids')))->firstWhere('position', 1)['id'] ?? null,
                    'tax_amount' => 0,
                    'tax_status' => null,

                    'discount_on_product_by' => $product_discount['discount_type'],
                    'discount_type' => $product_discount['discount_type'],
                    'discount_on_item' => $product_discount['discount_amount'],
                    'discount_percentage' => $product_discount['discount_percentage'],

                    'variant' => json_encode($c['variant']),
                    'variation' => $foodVariation ? json_encode($variations) : json_encode($c['variation']),
                    'add_ons' => json_encode($addon_data['addons']),

                    'total_add_on_price' => round($addon_data['total_add_on_price'], config('round_up_to_digit')),
                    'addon_discount' => 0,

                    'created_at' => now(),
                    'updated_at' => now()
                ];

                $total_addon_price += $or_d['total_add_on_price'];
                $product_price += $price * $or_d['quantity'];

                if ($bogoService->isDiscountable($c, $isHappyHour) && $bundleService->isDiscountable($c)) {
                    $discountable_price += ($price * $or_d['quantity']) + $or_d['total_add_on_price'];
                }
                $store_discount_amount += $or_d['discount_type'] != 'flash_sale' ? $or_d['discount_on_item'] * $or_d['quantity'] : 0;
                $flash_sale_admin_discount_amount += $or_d['discount_type'] == 'flash_sale' ? $product_discount['admin_discount_amount'] * $or_d['quantity'] : 0;
                $flash_sale_vendor_discount_amount += $or_d['discount_type'] == 'flash_sale' ? $product_discount['vendor_discount_amount'] * $or_d['quantity'] : 0;
                $order_details[] = $or_d;
                $addon_data[] = $addon_data['addons'];
            } else {
                return [
                    'status_code' => 403,
                    'code' => 'not_found',
                    'message' => translate('messages.Item currently unavailable'),
                ];
            }
        }

        $discount = $store_discount_amount;
        $storeDiscount = Helpers::get_store_discount($store);

        $storeWideWon = false;

        // A bundle line is never itself eligible for a store-wide rate (BundleOrderService::
        // isDiscountable()) and gets its own reduction from distributeReduction() below instead --
        // that carve-out already keeps $discountable_price and the per-line loop below scoped to
        // this cart's non-bundle lines, so a bundle elsewhere in the cart must not stop the rate
        // from reaching them. Whether that remainder is admin-borne (store_discount) or
        // vendor-borne (happy_hour) is reconciled downstream in OrderTransactionsTrait, which reads
        // bundle_discount_amount back out of store_discount_amount to bill each payer their own
        // share, regardless of which one produced the rest of the figure.
        if (isset($storeDiscount) && $discount_type != 'flash_sale') {
            $admin_discount = Helpers::checkAdminDiscount(price: $discountable_price, discount: $storeDiscount['discount'], max_discount: $storeDiscount['max_discount'], min_purchase: $storeDiscount['min_purchase']);

            // A running store-wide discount always takes the order over the items' own discounts --
            // it does not have to beat them first. Item discounts only apply when no store-wide rate
            // is qualified here (e.g. the cart is under its min_purchase, so $admin_discount is 0).
            $storeWideWon = $admin_discount > 0;
            $discount = $storeWideWon ? $admin_discount : $discount;

            if ($storeWideWon) {
                $discount_on_product_by = 'store_discount';
                foreach ($order_details as $key => $detail_data) {
                    if (! $bogoService->isDiscountable($detail_data, $isHappyHour) || ! $bundleService->isDiscountable($detail_data)) {
                        continue;
                    }

                    $order_details[$key]['discount_on_product_by'] = $discount_on_product_by;
                    $order_details[$key]['discount_type'] = 'precentage';
                    $order_details[$key]['discount_percentage'] = $storeDiscount['discount'];

                    $order_details[$key]['discount_on_item'] =  Helpers::checkAdminDiscount(price: $discountable_price, discount: $storeDiscount['discount'], max_discount: $storeDiscount['max_discount'], min_purchase: $storeDiscount['min_purchase'], item_wise_price: $detail_data['price']);
                }
            }
        }

        $bundleReduction = $bundleService->distributeReduction($order_details, $store);

        foreach ($bundleReduction['lines'] as $bundleIndex => $perUnitDiscount) {
            $order_details[$bundleIndex]['discount_on_item'] = $perUnitDiscount;
            $order_details[$bundleIndex]['discount_type'] = 'amount';
            $order_details[$bundleIndex]['discount_on_product_by'] = 'vendor';
        }

        $bundleTotal = $bundleReduction['bundle_discount'] + $bundleReduction['happy_hour_discount'];
        $discount += $bundleTotal;

        return [
            'order_details' => $order_details,
            'total_addon_price' => $total_addon_price,
            'product_price' => $product_price,
            'store_discount_amount' => $discount,

            'discount_on_product_by' => $storeWideWon
                ? app(StoreDiscountResolver::class)->bearerFor($storeDiscount)
                : 'vendor',

            'bundle_discount_amount' => $bundleReduction['bundle_discount'],
            'flash_sale_admin_discount_amount' => $flash_sale_admin_discount_amount,
            'flash_sale_vendor_discount_amount' => $flash_sale_vendor_discount_amount,
            'product_data' => $product_data,

            'bogo_free_value' => $bogo_free_value,

            'happy_hour_id' => $this->happyHourIdForOrder(
                $storeWideWon, $bundleReduction, $discount, $store
            ),

        ];
    }
    private function makePosOrderDetails($carts, $request, $store)
    {
        $total_addon_price = 0;
        $product_price = 0;
        $store_discount_amount = 0;
        $flash_sale_vendor_discount_amount = 0;
        $flash_sale_admin_discount_amount = 0;
        $product_data = [];
        $order_details = [];
        $discount_on_product_by = 'vendor';
        $discount_type = '';
        $carts = is_iterable($carts) ? $carts : [];
        foreach ($carts as $c) {
            $variations = [];
            if (is_array($c)) {
                $isCampaign = false;
                if (isset($c['item_type']) && ($c['item_type'] === 'App\Models\ItemCampaign' || $c['item_type'] === 'AppModelsItemCampaign')) {
                    $product = app(ItemCampaignService::class)->findActiveWithModule($c['item_id']);
                    $isCampaign = true;
                } else {
                    $product = app(ItemService::class)->findActiveWithModule($c['item_id'] ?? $c['id'], ['module', 'pharmacy_item_details']);
                }

                if ($product) {
                    if ($product->store_id != $store->id) {
                        return [
                            'status_code' => 403,
                            'code' => 'different_stores',
                            'message' => translate('messages.Please select items from the same store'),
                        ];
                    }

                    if ($request && $product?->pharmacy_item_details?->is_prescription_required == '1' && empty($request->file('order_attachment'))) {
                        return [
                            'status_code' => 403,
                            'code' => 'prescription',
                            'message' => translate('messages.Prescription is required for this order'),
                        ];
                    }

                    if ($product?->maximum_cart_quantity && $c['quantity'] > $product?->maximum_cart_quantity) {
                        return [
                            'status_code' => 403,
                            'code' => 'quantity',
                            'message' => translate('messages.Maximum cart quantity limit over'),
                        ];
                    }

                    $foodVariation = false;
                    if ($product?->module?->module_type == 'food') {
                        $foodVariation = true;
                        $product_variations = Helpers::decodeJsonToArray($product->food_variations);

                        if ($product_variations && count($product_variations)) {
                            $variation_data = Helpers::get_varient($product_variations, $c['variations']);
                            $price = $product['price'] + $variation_data['price'];
                            $variations = $variation_data['variations'];
                        } else {
                            $price = $product['price'];
                        }
                    } else {
                        if (count(json_decode($product['variations'], true)) > 0 && count($c['variations']) > 0) {
                            $variant_data = Helpers::pos_variation_price($product, json_encode($c['variations']));
                            $price = $variant_data['price'];
                            $stock = $variant_data['stock'];
                        } else {
                            $price = $product['price'];
                            $stock = $product?->stock;
                        }

                        if (config('module.' . $product->module->module_type)['stock']) {
                            if ($c['quantity'] > $stock) {
                                return [
                                    'status_code' => 403,
                                    'code' => 'stock',
                                    'message' => $isCampaign ? $product?->title : $product?->name . ' ' . translate('messages.Is out of stock')
                                ];
                            }

                            $product_data[] = [
                                'item' => clone $product,
                                'quantity' => $c['quantity'],
                                'variant' => Helpers::variationType($c['variations'] ?? null)
                            ];
                        }
                    }

                    $product = $this->productPayload($product);
                    [$lineAddonIds, $lineAddonQtys] = $this->itemOwnAddons($product, $c['add_ons'], $c['add_on_qtys']);
                    $addon_data = Helpers::calculate_addon_price(app(AddonService::class)->getByIds($lineAddonIds), $lineAddonQtys);
                    $product_discount = Helpers::product_discount_calculate($product, $price, $store, false);

                    $discount_type = $product_discount['discount_type'];

                    $or_d = [
                        'item_id' => $isCampaign ? null : $c['id'],
                        'item_campaign_id' => $isCampaign ? $c['id'] : null,
                        'item_details' => json_encode($product),
                        'quantity' => $c['quantity'],
                        'price' => round($price, config('round_up_to_digit')),

                        'category_id' => collect(Helpers::decodeJsonToArray(data_get($product, 'category_ids')))->firstWhere('position', 1)['id'] ?? null,
                        'tax_amount' => 0,
                        'tax_status' => null,

                        'discount_on_product_by' => $product_discount['discount_type'],
                        'discount_type' => $product_discount['discount_type'],
                        'discount_on_item' => $product_discount['discount_amount'],
                        'discount_percentage' => $product_discount['discount_percentage'],

                        'variant' => json_encode($c['variant']),
                        'variation' => $foodVariation ? json_encode($variations) : json_encode($c['variations']),
                        'add_ons' => json_encode($addon_data['addons']),

                        'total_add_on_price' => round($addon_data['total_add_on_price'], config('round_up_to_digit')),
                        'addon_discount' => 0,

                        'created_at' => now(),
                        'updated_at' => now()
                    ];

                    $total_addon_price += $or_d['total_add_on_price'];
                    $product_price += $price * $or_d['quantity'];
                    $store_discount_amount += $or_d['discount_type'] != 'flash_sale' ? $or_d['discount_on_item'] * $or_d['quantity'] : 0;
                    $flash_sale_admin_discount_amount += $or_d['discount_type'] == 'flash_sale' ? $product_discount['admin_discount_amount'] * $or_d['quantity'] : 0;
                    $flash_sale_vendor_discount_amount += $or_d['discount_type'] == 'flash_sale' ? $product_discount['vendor_discount_amount'] * $or_d['quantity'] : 0;
                    $order_details[] = $or_d;
                    $addon_data[] = $addon_data['addons'];
                } else {
                    return [
                        'status_code' => 403,
                        'code' => 'not_found',
                        'message' => translate('No data found'),
                    ];
                }
            }
        }

        $discount = $store_discount_amount;

        // Was app(StoreDiscountResolver::class)->vendorDiscount($store) — calling straight into
        // the vendor's own standing discount, bypassing resolve()'s happy_hour > store_discount
        // precedence entirely (StoreDiscountResolver's own docblock states that precedence
        // explicitly). Helpers::get_store_discount() is the same happy-hour-aware call
        // makeOrderDetails() and makeEditOrderDetails() already use — this was the one place a
        // POS order priced a running happy hour as if it were not running at all (TC_147).
        $storeDiscount = Helpers::get_store_discount($store);
        $storeWideWon = false;
        if (isset($storeDiscount) && $discount_type != 'flash_sale') {
            $admin_discount = Helpers::checkAdminDiscount(price: $product_price, discount: $storeDiscount['discount'], max_discount: $storeDiscount['max_discount'], min_purchase: $storeDiscount['min_purchase']);

            $discount = max($discount, $admin_discount);
            $storeWideWon = $admin_discount > 0 && abs($discount - $admin_discount) < 0.001;

            if ($storeWideWon) {
                $discount_on_product_by = 'store_discount';
                foreach ($order_details as $key => $detail_data) {
                    $order_details[$key]['discount_on_product_by'] = $discount_on_product_by;
                    $order_details[$key]['discount_type'] = 'precentage';
                    $order_details[$key]['discount_percentage'] = $storeDiscount['discount'];
                    $order_details[$key]['discount_on_item'] =  Helpers::checkAdminDiscount(price: $product_price, discount: $storeDiscount['discount'], max_discount: $storeDiscount['max_discount'], min_purchase: $storeDiscount['min_purchase'], item_wise_price: $detail_data['price']);
                }
            }
        }

        return [
            'order_details' => $order_details,
            'total_addon_price' => $total_addon_price,
            'product_price' => $product_price,
            'store_discount_amount' => $discount,

            'discount_on_product_by' => $storeWideWon
                ? app(StoreDiscountResolver::class)->bearerFor($storeDiscount)
                : 'vendor',
            'flash_sale_admin_discount_amount' => $flash_sale_admin_discount_amount,
            'flash_sale_vendor_discount_amount' => $flash_sale_vendor_discount_amount,
            'product_data' => $product_data

        ];
    }
    /**
     * The add-ons a cart line may be priced with: the ones its item offers. The ids came from the
     * request as they were, so any store's add-on could be put on an order line. A line whose
     * add-ons are all the item's own is passed through untouched -- ids and quantities in the
     * order they came, exactly as before -- and a foreign id is dropped with its quantity.
     */
    private function itemOwnAddons(mixed $product, mixed $ids, mixed $qtys): array
    {
        $ids = is_array($ids) ? $ids : (json_decode((string) $ids, true) ?: []);
        // productPayload() hands back an array, the POS path a model: data_get reads either.
        $offered = data_get($product, 'add_ons');
        $offered = is_array($offered) ? $offered : (json_decode((string) $offered, true) ?: []);
        $offered = array_map(fn ($addon) => (string) (is_array($addon) || is_object($addon) ? data_get($addon, 'id') : $addon), $offered);

        $keep = array_keys(array_filter($ids, fn ($id) => in_array((string) $id, $offered, true)));
        if (count($keep) === count($ids)) {
            return [$ids, $qtys];
        }

        $qtys = is_array($qtys) ? $qtys : null;

        return [
            array_values(array_intersect_key($ids, array_flip($keep))),
            $qtys === null ? null : array_values(array_intersect_key($qtys, array_flip($keep))),
        ];
    }

    private function makeEditOrderDetails($carts, $request, $store, $originalDetailQtys = [])
    {
        $total_addon_price = 0;
        $product_price = 0;
        $store_discount_amount = 0;
        $flash_sale_vendor_discount_amount = 0;
        $flash_sale_admin_discount_amount = 0;
        $product_data = [];
        $order_details = [];
        $discount_on_product_by = 'vendor';
        if($carts->isEmpty()){
            return [
                'status_code' => 403,
                'code' => 'not_found',
                'message' => translate('Cart is empty'),
            ];
        }

        $bogoService = app(BogoOrderService::class);
        $bogo_frozen_prices = $bogoService->frozenPrices($carts, (int) $store->id);
        $bogo_free_value = 0;
        $bundleService = app(BundleOrderService::class);
        $bundle_frozen_prices = $bundleService->frozenPrices($carts, (int) $store->id);
        $isHappyHour = $bogoService->isHappyHourRunning($store);
        $discountable_price = 0;
        $editCartProducts = $this->editCartProducts($carts, $originalDetailQtys);

        foreach ($carts as $c) {
            $variations = [];

            if (!isset($c['status']) || $c['status'] !== false) {
                if (isset($c['variation']) && is_string($c['variation'])) {
                    $c['variation'] = json_decode($c['variation'], true) ?? [];
                }
                if (isset($c['variant']) && is_string($c['variant'])) {
                    $c['variant'] = json_decode($c['variant'], true) ?? [];
                }

                $cartId        = $c['id'] ?? null;
                $isPreexisting = $cartId && isset($originalDetailQtys[$cartId]);

                $isCampaign = (isset($c['item_type']) && ($c['item_type'] === 'App\Models\ItemCampaign' || $c['item_type'] === 'AppModelsItemCampaign'))
                    || !empty($c['item_campaign_id']);

                if ($isCampaign) {
                    $campaignId = $c['item_campaign_id'] ?? $c['item_id'];
                    $product = $editCartProducts[$isPreexisting ? 'campaign_any' : 'campaign_active'][$campaignId] ?? null;
                } else {
                    $itemId = $c['item_id'] ?? $c['id'];
                    $product = $editCartProducts[$isPreexisting ? 'item_any' : 'item_active'][$itemId] ?? null;
                }

                if ($product) {
                    if ($product->store_id != $store->id) {
                        return [
                            'status_code' => 403,
                            'code' => 'different_stores',
                            'message' => translate('messages.Please select items from the same store'),
                        ];
                    }

                    if ($request && $product?->pharmacy_item_details?->is_prescription_required == '1' && empty($request->file('order_attachment'))) {
                        return [
                            'status_code' => 403,
                            'code' => 'prescription',
                            'message' => translate('messages.Prescription is required for this order'),
                        ];
                    }

                    if ($product?->maximum_cart_quantity && $c['quantity'] > $product?->maximum_cart_quantity) {
                        return [
                            'status_code' => 403,
                            'code' => 'quantity',
                            'message' => translate('messages.Maximum cart quantity limit over'),
                        ];
                    }

                    if (!$isPreexisting && !$isCampaign && $product?->module?->module_type == 'food' && !$product->is_available_now) {
                        return [
                            'status_code' => 403,
                            'code' => 'not_available',
                            'message' => $product?->name . ' ' . translate('messages.Is not available right now'),
                        ];
                    }

                    $foodVariation = false;
                    if ($product?->module?->module_type == 'food') {
                        $foodVariation = true;
                        $product_variations = Helpers::decodeJsonToArray($product->food_variations);

                        if ($product_variations && count($product_variations)) {
                            $variation_data = Helpers::get_edit_varient($product_variations, $c['variation'] ?? []);
                            $price = $product['price'] + $variation_data['price'];
                            $variations = $variation_data['variations'];
                        } else {
                            $price = $product['price'];
                        }
                    } else {
                        if (
                            is_array(json_decode($product['variations'], true)) && count(json_decode($product['variations'], true)) > 0 &&
                            is_array($c['variation']) && count($c['variation']) > 0
                        ) {
                            $variant_data = Helpers::variation_price($product, json_encode($c['variation']));
                            $price = $variant_data['price'];
                            $stock = $variant_data['stock'];
                        } else {
                            $price = $product['price'];
                            $stock = $product?->stock;
                        }

                        if (config('module.' . $product->module->module_type)['stock']) {
                            $cartId = $c['id'] ?? null;
                            $alreadyReserved = ($cartId && isset($originalDetailQtys[$cartId])) ? (int) $originalDetailQtys[$cartId] : 0;
                            if ($c['quantity'] > ($stock + $alreadyReserved)) {
                                return [
                                    'status_code' => 403,
                                    'code' => 'stock',
                                    'message' => $isCampaign ? $product?->title : $product?->name . ' ' . translate('messages.Is out of stock')
                                ];
                            }
                            $product_data[] = [
                                'item' => clone $product,
                                'quantity' => $c['quantity'],
                                'variant' => is_array($c['variation']) ? Helpers::variationType($c['variation'][0] ?? null) : null
                            ];
                        }
                    }

                    $product = $this->productPayload($product);

                    $input = $c['add_ons'] ?? null;

                    $addonIds = [];
                    $addonQuantities = [];

                    if (is_string($input)) {
                        $decoded = json_decode($input, true);

                        if (is_array($decoded)) {
                            if (is_numeric(data_get($decoded,0))) {
                                $addonIds = $decoded;
                                $addonQuantities = $c['add_on_qtys'] ?? [];
                            } else {
                                $addonIds = array_column($decoded, 'id');
                                $addonQuantities = array_column($decoded, 'quantity');
                            }
                        }
                    } elseif (is_array($input)) {
                        if (is_numeric(data_get($input,0))) {
                            $addonIds = $input;
                            $addonQuantities = $c['add_on_qtys'] ?? [];
                        } else {
                            $addonIds = array_column($input, 'id');
                            $addonQuantities = array_column($input, 'quantity');
                        }
                    }

                    $addonIds = array_unique($addonIds);
                    $addon_data = Helpers::calculate_addon_price(
                        app(AddonService::class)->getByIdsForStore($addonIds, $store->id),
                        $addonQuantities
                    );

                    $bogo_unit_worth = null;
                    $isBogoLine = (bool) data_get($c, 'bogo_group_id');
                    $isBundleLine = (bool) data_get($c, 'bundle_group_id');

                    if ($isBundleLine) {
                        $price = $bundleService->linePrice($bundle_frozen_prices, $c, $price);
                        $addon_data['total_add_on_price'] = 0;
                    }

                    if ($isBogoLine) {
                        [$price, $gave_away, $bogo_unit_worth] = $bogoService->linePrice($bogo_frozen_prices, $c, $price);

                        $bogo_free_value += $gave_away;

                        $addon_data['total_add_on_price'] = 0;
                    }

                    $product_discount = Helpers::product_discount_calculate($product, $price, $store, false);

                    if ($isBogoLine && ! $bogoService->isDiscountable($c, $isHappyHour)) {
                        $product_discount['discount_amount'] = 0;
                        $product_discount['discount_percentage'] = 0;

                        $product = $bogoService->clearUnappliedRates($product);
                    }

                    if ($isBundleLine) {
                        $product_discount = $bundleService->clearLineDiscount($product_discount);
                        $product = $bogoService->clearUnappliedRates($product);
                    }

                    $discount_type = $product_discount['discount_type'];

                    $or_d = $bundleService->detailColumns($c) + $bogoService->detailColumns($c, $bogo_unit_worth) + [
                        'cart_id' => $c['id'],
                        'item_id' => $isCampaign ? null : $c['item_id'],
                        'item_campaign_id' => $isCampaign ? $c['item_id'] : null,
                        'item_details' => json_encode($product),
                        'quantity' => $c['quantity'],
                        'price' => round($price, config('round_up_to_digit')),

                        'category_id' => collect(Helpers::decodeJsonToArray(data_get($product, 'category_ids')))->firstWhere('position', 1)['id'] ?? null,
                        'tax_amount' => 0,
                        'tax_status' => null,

                        'discount_on_product_by' => $product_discount['discount_type'],
                        'discount_type' => $product_discount['discount_type'],
                        'discount_on_item' => $product_discount['discount_amount'],
                        'discount_percentage' => $product_discount['discount_percentage'],

                        'variant' => json_encode($c['variant']),
                        'variation' => $foodVariation ? json_encode($variations) : json_encode($c['variation']),
                        'add_ons' => json_encode($addon_data['addons']),

                        'total_add_on_price' => round($addon_data['total_add_on_price'], config('round_up_to_digit')),
                        'addon_discount' => 0,

                        'created_at' => now(),
                        'updated_at' => now()
                    ];

                    $total_addon_price += $or_d['total_add_on_price'];
                    $product_price += $price * $or_d['quantity'];

                    if ($bogoService->isDiscountable($c, $isHappyHour) && $bundleService->isDiscountable($c)) {
                        $discountable_price += ($price * $or_d['quantity']) + $or_d['total_add_on_price'];
                    }
                    $store_discount_amount += $or_d['discount_type'] != 'flash_sale' ? $or_d['discount_on_item'] * $or_d['quantity'] : 0;
                    $flash_sale_admin_discount_amount += $or_d['discount_type'] == 'flash_sale' ? $product_discount['admin_discount_amount'] * $or_d['quantity'] : 0;
                    $flash_sale_vendor_discount_amount += $or_d['discount_type'] == 'flash_sale' ? $product_discount['vendor_discount_amount'] * $or_d['quantity'] : 0;
                    $order_details[] = $or_d;
                    $addon_data[] = $addon_data['addons'];
                } else {
                    return [
                        'status_code' => 403,
                        'code' => 'not_found',
                        'message' => translate('No data found'),
                    ];
                }
            }
        }
        $discount = $store_discount_amount;
        $storeDiscount = Helpers::get_store_discount($store);
        $storeWideWon = false;
        // Same rule as makeOrderDetails(): a bundle line is excluded from the store-wide rate on its
        // own (BundleOrderService::isDiscountable()) and gets its reduction from distributeReduction()
        // below instead, so a bundle elsewhere in the cart must not stop the rate from reaching this
        // cart's non-bundle lines. OrderTransactionsTrait reconciles who bears which part afterward.
        if (isset($storeDiscount) && $discount_type != 'flash_sale') {
            $admin_discount = Helpers::checkAdminDiscount(price: $discountable_price, discount: $storeDiscount['discount'], max_discount: $storeDiscount['max_discount'], min_purchase: $storeDiscount['min_purchase']);

            // Same rule as makeOrderDetails(): a running store-wide discount always wins outright,
            // it does not need to beat the items' own discounts first.
            $storeWideWon = $admin_discount > 0;
            $discount = $storeWideWon ? $admin_discount : $discount;

            if ($storeWideWon) {
                $discount_on_product_by = 'store_discount';
                foreach ($order_details as $key => $detail_data) {
                    if (! $bogoService->isDiscountable($detail_data, $isHappyHour) || ! $bundleService->isDiscountable($detail_data)) {
                        continue;
                    }

                    $order_details[$key]['discount_on_product_by'] = $discount_on_product_by;
                    $order_details[$key]['discount_type'] = 'precentage';
                    $order_details[$key]['discount_percentage'] = $storeDiscount['discount'];
                    $order_details[$key]['discount_on_item'] =  Helpers::checkAdminDiscount(price: $discountable_price, discount: $storeDiscount['discount'], max_discount: $storeDiscount['max_discount'], min_purchase: $storeDiscount['min_purchase'], item_wise_price: $detail_data['price']);
                }
            }
        }

        $bundleReduction = $bundleService->distributeReduction($order_details, $store);

        foreach ($bundleReduction['lines'] as $bundleIndex => $perUnitDiscount) {
            $order_details[$bundleIndex]['discount_on_item'] = $perUnitDiscount;
            $order_details[$bundleIndex]['discount_type'] = 'amount';
            $order_details[$bundleIndex]['discount_on_product_by'] = 'vendor';
        }

        $discount += $bundleReduction['bundle_discount'] + $bundleReduction['happy_hour_discount'];

        return [
            'order_details' => $order_details,
            'total_addon_price' => $total_addon_price,
            'product_price' => $product_price,
            'store_discount_amount' => $discount,
            'bundle_discount_amount' => $bundleReduction['bundle_discount'],
            'discount_on_product_by' => $storeWideWon
                ? app(StoreDiscountResolver::class)->bearerFor($storeDiscount)
                : 'vendor',
            'flash_sale_admin_discount_amount' => $flash_sale_admin_discount_amount,
            'flash_sale_vendor_discount_amount' => $flash_sale_vendor_discount_amount,
            'product_data' => $product_data,

            'bogo_free_value' => $bogo_free_value,
            'happy_hour_id' => $this->happyHourIdForOrder(
                $storeWideWon, $bundleReduction, $discount, $store
            )

        ];
    }
    // A Pro customer's delivery-fee benefit is deliberately NOT applied inside this method —
    // it used to be, before the base+surge amount had gone through Express/Slightly Delay's own
    // adjustment, so the two composed in the wrong order (a customer choosing Express against an
    // already-Pro-discounted base got a smaller premium than the plain price list promised, and
    // the address modal / delivery-type picker both showed the Pro-discounted number instead of
    // the real, undiscounted one they need for their own previews). This now returns the plain
    // base+surge quote only; callers that need to apply a Pro discount do so themselves, as the
    // LAST step, against whatever the delivery charge has become after every other adjustment —
    // see applyProCustomerDeliveryFee(), called directly from place_order() after
    // applySaverToOrder(), and PosCartSummary::build()'s own equivalent step.
    private function calculatePosDeliveryFee($store, $distance = 1, $areaId = null, $zipCodeId = null)
    {
        $emptyResult = [
            'original_delivery_charge' => 0.0,
            'delivery_fee'             => 0.0,
            'free_delivery_by'         => null,
            'surge_amount'             => 0.0,
            'surge_note'               => null,
        ];

        $store = $store instanceof Store
            ? $store->loadMissing(['zone'])
            : app(StoreService::class)->findWithZone($store);

        if (!$store) {
            return $emptyResult;
        }

        $module_wise_delivery_charge = $store->zone->modules()->where('modules.id', $store->module_id)->first();

        // POS is an immediate order, not a scheduled one, so surge is resolved for "now" —
        // there is no schedule_at slot to surge against like the customer checkout flow.
        $surge = $this->getSurgePriceValue($store->zone->id, $store->module_id, now());

        // area_id/zip_code_id are only meaningful when the zone/module's active DeliveryRule
        // prices by area or zip code — the engine ignores whichever one doesn't match the
        // active rule's pricing_method (DeliveryRuleChargeService::chargeForArea/chargeForZipCode
        // both return 0 on an empty id), the same way the customer checkout request is handled.
        $quote = app(DeliveryChargeService::class)->quote([
            'order_type' => 'delivery',
            'distance' => (float) $distance,
            'store' => $store,
            'module_zone_pivot' => $module_wise_delivery_charge?->pivot,
            'area_id' => $areaId,
            'zip_code_id' => $zipCodeId,
            'surge' => $surge,
        ]);

        $original_delivery_charge = $quote['original_delivery_charge'];

        return [
            'original_delivery_charge' => (float) $original_delivery_charge,
            // Kept alongside original_delivery_charge (identical value) rather than dropped, so
            // every existing caller reading ['delivery_fee'] for "the base+surge quote" keeps
            // working unchanged — only the ones that used to read it expecting a Pro-discounted
            // number had to change, and they now read original_delivery_charge explicitly instead.
            'delivery_fee'             => (float) $original_delivery_charge,
            'free_delivery_by'         => null,
            'surge_amount'             => (float) ($quote['surge_amount'] ?? 0),
            'surge_note'               => $this->posSurgeNote((float) ($quote['surge_amount'] ?? 0), $surge, (float) $original_delivery_charge),
        ];
    }

    /**
     * The tooltip text for a surged POS delivery fee.
     *
     * Visible whenever a surge is actually applied, not only when the admin wrote a customer
     * note — an unexplained jump in the delivery fee is worse than one explained by a plain
     * "Surge price X". SurgePriceService::customerNote() is still the single gate on
     * customer_note_status; when it returns null (or the admin left it off), the line falls
     * back to the amount alone instead of disappearing.
     */
    private function posSurgeNote(float $surgeAmount, array $surge, float $originalDeliveryCharge): ?string
    {
        $surgeAmount = round($surgeAmount, config('round_up_to_digit'));

        if ($surgeAmount <= 0) {
            return null;
        }

        $line = translate('messages.surge_price').' '.Helpers::format_currency($surgeAmount);

        $adminNote = trim((string) (app(SurgePriceService::class)->customerNote($surge, $originalDeliveryCharge) ?? ''));

        if ($adminNote !== '') {
            $line .= ' - '.$adminNote;
        }

        return $line;
    }
    private function updateExtraDiscount($type,$discount){
        $subtotal = 0;
        $addon_price = 0;
        $discount_on_product = 0;

        $cart = session()->get('cart', []);

        foreach ($cart as $cartItem) {
            if (is_array($cartItem)) {
                $subtotal += $cartItem['price'] * $cartItem['quantity'];
                $addon_price += $cartItem['addon_price'] ?? 0;
                $discount_on_product += ($cartItem['discount'] ?? 0) * $cartItem['quantity'];
            }
        }

        $total = ($subtotal + $addon_price) - $discount_on_product;

        $base_total = $total;

        session()->put('extra_discount_amount',0 );
        session()->put('extra_discount_type',$type);

        if($type == 'amount'){
            session()->put('extra_discount_amount', $discount);
        } else{
            session()->put('extra_discount_amount', $base_total * $discount / 100);
        }
            session()->put('extra_discount', $discount );

        return true;
    }
    private function happyHourIdForOrder(bool $storeWideWon, array $bundleReduction, float $discount, $store): ?int
    {
        if ($storeWideWon) {
            return app(BogoOrderService::class)->happyHourIdFor($store);
        }

        $window = (float) ($bundleReduction['happy_hour_discount'] ?? 0);

        // Compared against the WHOLE bundle reduction, not the window's share of it. A bundle now
        // takes its own percentage off first and lets the window cut what is left, so on a
        // bundle-only order the recorded discount is both halves -- measuring the window alone
        // against it never matched, and the order lost its happy_hour_id. Still refused when the
        // figures disagree, which means something outside the bundles also discounted this order
        // and the window cannot be named as the whole reason for it.
        $fromBundles = $window + (float) ($bundleReduction['bundle_discount'] ?? 0);

        if ($window <= 0 || abs($fromBundles - $discount) > 0.01) {
            return null;
        }

        return app(BogoOrderService::class)->happyHourIdFor($store);
    }

    private function editCartProducts($carts, array $originalDetailQtys): array
    {
        $buckets = ['item_any' => [], 'item_active' => [], 'campaign_any' => [], 'campaign_active' => []];

        foreach ($carts as $c) {
            if (isset($c['status']) && $c['status'] === false) {
                continue;
            }

            $cartId = $c['id'] ?? null;
            $preexisting = $cartId && isset($originalDetailQtys[$cartId]);
            $isCampaign = (isset($c['item_type']) && ($c['item_type'] === 'App\\Models\\ItemCampaign' || $c['item_type'] === 'AppModelsItemCampaign'))
                || ! empty($c['item_campaign_id']);

            $key = ($isCampaign ? 'campaign' : 'item').($preexisting ? '_any' : '_active');
            $buckets[$key][] = $isCampaign ? ($c['item_campaign_id'] ?? $c['item_id']) : ($c['item_id'] ?? $c['id']);
        }

        return [
            'item_any' => $buckets['item_any']
                ? app(ItemService::class)->getByIdsWithModule($buckets['item_any']) : [],
            'item_active' => $buckets['item_active']
                ? app(ItemService::class)->getByIdsWithModule($buckets['item_active'], true) : [],
            'campaign_any' => $buckets['campaign_any']
                ? app(ItemCampaignService::class)->getByIdsWithModule($buckets['campaign_any']) : [],
            'campaign_active' => $buckets['campaign_active']
                ? app(ItemCampaignService::class)->getByIdsWithModule($buckets['campaign_active'], true) : [],
        ];
    }
    private function productPayload(Model $product): array
    {
        $this->loadProductRelations(new EloquentCollection([$product]));

        return (new ProductResource($product))->toArray(request());
    }
}
