<?php

namespace App\Traits\Order;

use App\CentralLogics\Helpers;
use App\Mail\AddFundToWallet;
use App\Models\BogoOffer;
use App\Models\DeliverymanReferralHistory;
use App\Models\HappyHour;
use App\Traits\Parcel\ParcelFeesTrait;
use App\Traits\Payment\CashCollectionTrait;
use App\Traits\Payment\CustomerTransactionsTrait;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use App\Services\System\UserNotificationService;
use App\Services\Admin\AdminWalletService;
use App\Services\Admin\AdminService;
use App\Services\Store\StoreWalletService;
use App\Services\DeliveryMan\DeliveryManWalletService;
use App\Services\DeliveryMan\DeliveryManService;
use App\Services\Order\OrderPaymentService;
use App\Services\Customer\UserService;
use App\Services\System\BusinessSettingService;
use App\Services\Order\OrderTransactionService;
use App\Support\Notification\SendNotification;
use App\Support\Notification\NotificationMessages;
use App\Services\Order\ExpenseService;
use Illuminate\Support\Facades\Log;

trait OrderTransactionsTrait
{
    use ParcelFeesTrait;
    use CashCollectionTrait;
    use CustomerTransactionsTrait;
    use OrderPaymentsTrait;

    public function createOrderTransaction($order, $receivedBy = false, $status = null)
    {
        $type = $order->order_type;
        $dm_tips_manage_status = app(BusinessSettingService::class)->value('dm_tips_status', false);
        $admin_subsidy = 0;
        $amount_admin = 0;
        $store_d_amount = 0;
        // Held back out of $order_amount and added only to the commission base, so a happy hour
        // is charged commission as if it had never happened -- see $commissionable_amount below.
        $happy_hour_discount_amount = 0;
        $admin_coupon_discount_subsidy = 0;
        $store_subsidy = 0;
        $store_coupon_discount_subsidy = 0;
        $store_discount_amount = 0;
        $flash_admin_discount_amount = 0;
        $flash_store_discount_amount = 0;
        $comission_on_store_amount = 0;
        $ref_bonus_amount = 0;
        $subscription_mode = 0;
        $commission_percentage = 0;
        $store_amount = 0;
        $extra_discount_amount = $order->extra_discount_amount ?? 0;
        $proDiscount = 0;
        $store = $order?->store;
        $store_sub = $order?->store?->store_sub;
        if ($order->free_delivery_by == 'admin') {
            $admin_subsidy = $order->original_delivery_charge;
            $free_delivery_expense_type = 'free_delivery';
            if ($order->orderProDiscount && $order->orderProDiscount?->benefit_type === 'delivery_fee') {
                $admin_subsidy = $order->orderProDiscount->delivery_fee_reduction_amount ?? $admin_subsidy;
                if ($order->orderProDiscount->delivery_offer_type === 'partial_free') {
                    $free_delivery_expense_type = 'pro_partial_free_delivery';
                } else {
                    $free_delivery_expense_type = 'pro_free_delivery';
                }
            }
            app(ExpenseService::class)->create([
                'amount' => $admin_subsidy,
                'type' => $free_delivery_expense_type,
                'created_by' => $order->free_delivery_by,
                'order_id' => $order->id,
            ]);
        }
        if ($order->free_delivery_by == 'vendor') {
            $store_subsidy = $order->original_delivery_charge;
            app(ExpenseService::class)->create([
                'amount' => $order->original_delivery_charge,
                'type' => 'free_delivery',
                'created_by' => $order->free_delivery_by,
                'order_id' => $order->id,
                'store_id' => $order->store->id,
            ]);
        }
        if ($order->coupon_created_by == 'admin') {
            $admin_coupon_discount_subsidy = $order->coupon_discount_amount;
            app(ExpenseService::class)->create([
                'amount' => $admin_coupon_discount_subsidy,
                'type' => 'coupon_discount',
                'created_by' => $order->coupon_created_by,
                'order_id' => $order->id,
            ]);
        }
        if ($order->ref_bonus_amount > 0) {
            $ref_bonus_amount = $order->ref_bonus_amount;
            app(ExpenseService::class)->create([
                'amount' => $ref_bonus_amount,
                'type' => 'referral_discount',
                'created_by' => 'admin',
                'order_id' => $order->id,
            ]);
        }
        if ($order->delivery_type == 'slightly_delay' && $order->delivery_type_charge > 0) {
            app(ExpenseService::class)->create([
                'amount' => $order->delivery_type_charge,
                'type' => 'slightly_delay_delivery_charge',
                'created_by' => 'admin',
                'order_id' => $order->id,
            ]);
        }
        if ($order->coupon_created_by == 'vendor') {
            $store_coupon_discount_subsidy = $order->coupon_discount_amount;
            app(ExpenseService::class)->create([
                'amount' => $store_coupon_discount_subsidy,
                'type' => 'coupon_discount',
                'created_by' => $order->coupon_created_by,
                'order_id' => $order->id,
                'store_id' => $order->store->id,
            ]);
        }

        if ($order->orderProDiscount && $order->orderProDiscount?->benefit_type === 'discount') {
            $proDiscount = $order?->orderProDiscount?->amount_saved ?? 0;
            app(ExpenseService::class)->create([
                'amount' => $proDiscount,
                'type' => 'pro_discount_on_product',
                'order_id' => $order->id,
                'created_by' => 'admin',
            ]);
        }

        if ($order?->cashback_history) {
            $this->creditCashbackToWallet($order);
        }

        if ($type == 'parcel') {
            $comission = app(BusinessSettingService::class)->value('parcel_commission_dm', false) ?? 0;
            $commission_percentage = $comission;
            $delivery_fee_reduction_amount = 0;
            if ($order->orderProDiscount && $order->orderProDiscount?->benefit_type === 'delivery_fee') {
                $delivery_fee_reduction_amount = $order->orderProDiscount?->delivery_fee_reduction_amount ?? 0;
            }
            $dm_tips = $dm_tips_manage_status ? $order->dm_tips : 0;
            $order_amount = $order->order_amount - $dm_tips - $order->additional_charge - $order->extra_packaging_amount - $order->total_tax_amount + $delivery_fee_reduction_amount;
            $dm_commission = $comission ? ($order_amount / 100) * $comission : 0;
            $comission_amount = $order_amount - $dm_commission;

        } else {
            $comission = isset($order->store->comission) == null ? app(BusinessSettingService::class)->value('admin_commission', false) : $order->store->comission;
            $dm_tips = $dm_tips_manage_status ? $order->dm_tips : 0;

            if ($order->store_discount_amount > 0 && $order->discount_on_product_by == 'vendor') {
                if ($order->happy_hour_id) {
                    // A happy hour is the vendor's own promotion and it bears the whole cost --
                    // which is what the vendor agrees to when it enrols. Bearing the whole cost
                    // means commission is charged on what the goods were worth BEFORE the window
                    // took its cut (see $commissionable_amount below), so no share falls to the
                    // admin and no admin expense is booked here.
                    //
                    // Charging commission on the discounted total instead would quietly make the
                    // admin a co-sponsor: it would forgo the commission on the discounted portion,
                    // and the store would carry less than the expense row claims.
                    //
                    // Its own expense type, not discount_on_product: a happy hour and a vendor's
                    // standing discount are different promotions with different levers, and filed
                    // under one type neither the expense report nor the earning breakdown could
                    // tell them apart. The description names the window and its percentage, which
                    // is what the breakdown builds its log from.
                    //
                    // Only the WINDOW's share of store_discount_amount is any of that, though. A
                    // bundle ordered during a happy hour now takes its own percentage off first
                    // and lets the window cut what is left (BundleOrderService::groupReduction()),
                    // so this figure is both promotions added together. Booking all of it as
                    // happy-hour spend would charge the store for a bundle discount under the
                    // wrong name, and -- worse -- add the bundle's share to $commissionable_amount
                    // below, billing commission on money the window never took. The bundle's share
                    // is what the order already recorded as bundle_discount_amount; the rest is
                    // the window's, which is also the whole amount on an order with no bundles.
                    $bundleShare = min((float) $order->bundle_discount_amount, (float) $order->store_discount_amount);
                    $windowShare = (float) $order->store_discount_amount - $bundleShare;

                    $store_d_amount = $windowShare;
                    $happy_hour_discount_amount = $windowShare;

                    if ($windowShare > 0) {
                        $happyHour = HappyHour::find($order->happy_hour_id);

                        app(ExpenseService::class)->create([
                            'amount' => $windowShare,
                            'type' => 'happy_hour_discount',
                            'created_by' => 'vendor',
                            'order_id' => $order->id,
                            'store_id' => $order->store->id,
                            'description' => trim(translate('messages.Happy_Hour_Offer').': '.($happyHour?->title ?? '')
                                .($happyHour?->discount ? ' - '.rtrim(rtrim(number_format((float) $happyHour->discount, 2, '.', ''), '0'), '.').'%' : '')),
                        ]);
                    }

                    // The bundle's own share keeps the treatment it gets on an order with no
                    // happy hour -- same expense type, same split with the admin. Who bears a
                    // bundle discount is a commercial question this branch has no business
                    // answering differently just because a window happened to be open.
                    if ($bundleShare > 0) {
                        $bundleSplit = $this->bookDiscountExpense(
                            $order, $bundleShare, 'bundle_discount', $comission,
                            $store->store_business_model == 'subscription' && isset($store_sub)
                        );

                        $store_d_amount += $bundleSplit['store'];
                        $amount_admin += $bundleSplit['admin'];
                    }
                } else {
                    // A bundle's reduction rides on store_discount_amount: the store-wide discount
                    // is skipped outright for an order carrying bundle lines, so when the order has
                    // a bundle this money IS the bundle's. It gets its own expense type for exactly
                    // the reason happy hour and BOGO do -- filed under discount_on_product, neither
                    // the expense report nor the earning breakdown can tell bundle spend from an
                    // ordinary product discount, so it cannot be filtered, totalled or costed.
                    //
                    // Only the LABEL moves. The split below is deliberately left alone: who bears a
                    // bundle discount, and in what proportion, is a commercial question and not one
                    // a reporting fix should answer quietly.
                    $discountExpenseType = $order->bundle_discount_amount > 0
                        ? 'bundle_discount'
                        : 'discount_on_product';

                    $discountSplit = $this->bookDiscountExpense(
                        $order, (float) $order->store_discount_amount, $discountExpenseType, $comission,
                        $store->store_business_model == 'subscription' && isset($store_sub)
                    );

                    $store_d_amount = $discountSplit['store'];
                    $amount_admin += $discountSplit['admin'];
                }
            }

            // A BOGO free item is the vendor's own promotion, exactly like a happy hour, so the
            // store carries its whole value and no share is charged to the admin. It moves no
            // money -- the customer was never billed for the item and the commission was never
            // taken on it, which is why it stays out of $commissionable_amount below -- so this
            // only records the cost against the store.
            //
            // Its own expense type for the same reason as above; the description names the offer
            // and what was given away.
            if ($order->bogo_discount_amount > 0) {
                $store_d_amount += $order->bogo_discount_amount;

                // loadMissing, not a bare read: preventLazyLoading() throws outside production and
                // not every caller of this method arrives with details already eager-loaded.
                $order->loadMissing('details');

                $bogoDetail = $order->details->firstWhere('bogo_group_id', '!=', null);
                // The offer can already have been deleted by the time this runs, so a missing
                // title is expected rather than exceptional.
                $bogoOffer = $bogoDetail?->bogo_offer_id ? BogoOffer::find($bogoDetail->bogo_offer_id) : null;
                $freeNames = $order->details->where('is_free_item', 1)
                    ->map(fn ($d) => Helpers::decodeJsonToArray($d->item_details)['name'] ?? null)
                    ->filter()->unique()->implode(', ');

                app(ExpenseService::class)->create([
                    'amount' => $order->bogo_discount_amount,
                    'type' => 'bogo_discount',
                    'created_by' => 'vendor',
                    'order_id' => $order->id,
                    'store_id' => $order->store->id,
                    'description' => trim(translate('BOGO offer').': '.($bogoOffer?->title ?? '')
                        .($freeNames !== '' ? ' - '.translate('Free item').': '.$freeNames : '')),
                ]);
            }

            if ($order->store_discount_amount > 0 && $order->discount_on_product_by == 'admin') {
                // store_discount_amount is the combined total now that PlaceNewOrderTrait lets an
                // admin store discount reach a cart's non-bundle lines alongside a bundle of its
                // own -- bundle_discount_amount is already carved out as that bundle's own
                // vendor-borne share (same split the 'vendor'+happy_hour branch above makes), so
                // only the remainder is really the admin's subsidy.
                $bundleShare = min((float) $order->bundle_discount_amount, (float) $order->store_discount_amount);
                $adminShare = (float) $order->store_discount_amount - $bundleShare;

                if ($adminShare > 0) {
                    $store_discount_amount = $adminShare;
                    app(ExpenseService::class)->create([
                        'amount' => $adminShare,
                        'type' => 'discount_on_product',
                        'created_by' => 'admin',
                        'order_id' => $order->id,
                    ]);
                }

                if ($bundleShare > 0) {
                    $bundleSplit = $this->bookDiscountExpense(
                        $order, $bundleShare, 'bundle_discount', $comission,
                        $store->store_business_model == 'subscription' && isset($store_sub)
                    );

                    $store_d_amount += $bundleSplit['store'];
                    $amount_admin += $bundleSplit['admin'];
                }
            }

            if ($order->flash_admin_discount_amount > 0) {
                $flash_admin_discount_amount = $order->flash_admin_discount_amount;
                app(ExpenseService::class)->create([
                    'amount' => $flash_admin_discount_amount,
                    'type' => 'flash_sale_discount',
                    'created_by' => 'admin',
                    'order_id' => $order->id,
                ]);
            }

            if ($order->flash_store_discount_amount > 0) {
                $flash_store_discount_amount = $order->flash_store_discount_amount;
                app(ExpenseService::class)->create([
                    'amount' => $flash_store_discount_amount,
                    'type' => 'flash_sale_discount',
                    'created_by' => 'vendor',
                    'order_id' => $order->id,
                    'store_id' => $order->store->id,
                ]);
            }
            if ($extra_discount_amount > 0) {
                app(ExpenseService::class)->create([
                    'amount' => $extra_discount_amount,
                    'type' => 'extra_discount',
                    'created_by' => 'vendor',
                    'order_id' => $order->id,
                    'store_id' => $order->store->id,
                ]);
            }

            $order_amount = $order->order_amount - $order->additional_charge - $order->extra_packaging_amount - $order->delivery_charge - $order->total_tax_amount - $dm_tips + $flash_admin_discount_amount + $order->coupon_discount_amount + $store_discount_amount + $flash_store_discount_amount + $ref_bonus_amount + $extra_discount_amount + $proDiscount;

            if ($order->delivery_type === 'express') {
                $order_amount -= $order->delivery_type_charge;
            } elseif ($order->delivery_type === 'slightly_delay') {
                $order_amount += $order->delivery_type_charge;
            }
            $delivery_charge_comission_percentage = app(BusinessSettingService::class)->value('delivery_charge_comission', false) ?? 0;
            $comission_on_delivery = $delivery_charge_comission_percentage * ($order->original_delivery_charge / 100);

            if ($order->store->sub_self_delivery) {
                $comission_on_actual_delivery_fee = 0;
            } else {

                $comission_on_actual_delivery_fee = ($order->delivery_charge > 0) ? $comission_on_delivery : 0;
            }

            if ($order->free_delivery_by == 'admin') {
                if ($order->store->sub_self_delivery) {
                    $comission_on_actual_delivery_fee = 0;
                    $store_amount = $admin_subsidy > 0 ? $admin_subsidy : $order->original_delivery_charge ?? 0;
                } else {
                    $comission_on_actual_delivery_fee = ($order->original_delivery_charge > 0) ? $comission_on_delivery : 0;
                }
            }

            // What commission is charged on, which is not the same as what the store is paid.
            // $order_amount is both the commission base and the payout below, so a happy hour has
            // to be added here rather than there: adding it to $order_amount would raise the
            // commission and hand the store the discount back at the same time. A BOGO give-away
            // is deliberately absent -- the customer was never billed for it, so no commission is
            // owed on it either.
            $commissionable_amount = $order_amount + $happy_hour_discount_amount;

            if ($store->store_business_model == 'subscription' && isset($store_sub)) {
                $comission_on_store_amount = 0;
                $subscription_mode = 1;
                $commission_percentage = 0;
            } else {
                $comission_on_store_amount = ($comission ? ($commissionable_amount / 100) * $comission : 0);
                $subscription_mode = 0;
                $commission_percentage = $comission;
            }

            $comission_amount = $comission_on_store_amount + $comission_on_actual_delivery_fee;
            $dm_commission = $order->original_delivery_charge - $comission_on_actual_delivery_fee;
        }
        $store_amount = $store_amount + $order_amount + $order->total_tax_amount + $order->extra_packaging_amount - $comission_on_store_amount - $store_coupon_discount_subsidy - $flash_store_discount_amount - $extra_discount_amount;
        try {
            app(OrderTransactionService::class)->insertMany([
                'vendor_id' => $type == 'parcel' ? null : $order->store->vendor->id,
                'delivery_man_id' => $order->delivery_man_id,
                'order_id' => $order->id,
                'order_amount' => $order->order_amount,
                'store_amount' => $type == 'parcel' ? 0 : $store_amount,
                'admin_commission' => $comission_amount + $order->additional_charge - $admin_subsidy - $admin_coupon_discount_subsidy - $ref_bonus_amount - $store_discount_amount,
                'delivery_charge' => $order->delivery_charge,
                'original_delivery_charge' => $dm_commission,
                // Mirrored from the order rather than derived later, the same reason
                // delivery_charge itself is copied here: the order's own columns can move
                // afterward (a refund, say), and the transaction keeps its own frozen record of
                // what was true the moment it was created (TC_354, TC_500).
                'delivery_type_charge' => $order->delivery_type_charge,
                'surge_amount' => $order->surge_amount,
                'tax' => $order->total_tax_amount,
                'received_by' => $receivedBy ? $receivedBy : 'admin',
                'zone_id' => $order->zone_id,
                'module_id' => $order->module_id,
                'admin_expense' => $admin_subsidy + $admin_coupon_discount_subsidy + $store_discount_amount + $flash_admin_discount_amount + $amount_admin + $ref_bonus_amount + $proDiscount + ($order->delivery_type === 'slightly_delay' ? $order->delivery_type_charge : 0),
                'store_expense' => $store_subsidy + $store_coupon_discount_subsidy + $flash_store_discount_amount + $extra_discount_amount,
                'status' => $status,
                'dm_tips' => $dm_tips,
                'created_at' => now(),
                'updated_at' => now(),
                'delivery_fee_comission' => isset($comission_on_actual_delivery_fee) ? $comission_on_actual_delivery_fee : 0,
                'discount_amount_by_store' => $store_coupon_discount_subsidy + $store_d_amount + $store_subsidy + $extra_discount_amount,
                'additional_charge' => $order->additional_charge,
                'extra_packaging_amount' => $order->extra_packaging_amount,
                'ref_bonus_amount' => $order->ref_bonus_amount,
                'pro_discount' => $order->orderProDiscount?->amount_saved ?? 0,
                'pro_delivery_discount' => $order->orderProDiscount?->delivery_fee_reduction_amount ?? 0,
                'is_subscribed' => $subscription_mode,
                'commission_percentage' => $commission_percentage,
            ]);
            $adminWallet = app(AdminWalletService::class)->findOrNew(app(AdminService::class)->findSuperAdmin()->id);

            $adminWallet->total_commission_earning = $adminWallet->total_commission_earning + $comission_amount + $order->additional_charge - $admin_subsidy - $admin_coupon_discount_subsidy - $store_discount_amount - $flash_admin_discount_amount - $ref_bonus_amount - $proDiscount;

            if ($order->delivery_type == 'express' && $order->delivery_type_charge > 0) {
                $adminWallet->total_commission_earning = $adminWallet->total_commission_earning + $order->delivery_type_charge;
            }

            if ($type != 'parcel') {
                $vendorWallet = app(StoreWalletService::class)->findOrNewForVendor($order->store->vendor->id);
                if ($order->store->sub_self_delivery) {
                    $vendorWallet->total_earning = $vendorWallet->total_earning + $order->delivery_charge + $dm_tips;
                } else {
                    $adminWallet->delivery_charge = $adminWallet->delivery_charge + $order->delivery_charge;
                }
                $vendorWallet->total_earning = $vendorWallet->total_earning + $store_amount;
            }
            if ($order->delivery_man && ($type == 'parcel' || ($order->store && ! $order->store->sub_self_delivery))) {
                $dmWallet = app(DeliveryManWalletService::class)->findOrNew($order->delivery_man_id);
                if ($order->delivery_man->earning == 1) {
                    $dmWallet->total_earning = $dmWallet->total_earning + $dm_commission + $dm_tips;
                } else {
                    $adminWallet->total_commission_earning = $adminWallet->total_commission_earning + $dm_commission + $dm_tips;
                }
            } else {
                $adminWallet->total_commission_earning = $adminWallet->total_commission_earning + $dm_commission + $dm_tips;
            }

            try {
                DB::beginTransaction();
                $unpaid_payment = app(OrderPaymentService::class)->findUnpaidForOrder($order->id)?->payment_method;
                $unpaid_pay_method = 'digital_payment';
                if ($unpaid_payment) {
                    $unpaid_pay_method = $unpaid_payment;
                }
                if ($receivedBy == 'admin') {
                    $adminWallet->digital_received = $adminWallet->digital_received + ($order->order_amount - $order->partially_paid_amount);
                } elseif ($receivedBy == 'store' && $type != 'parcel' && ($order->payment_method == 'cash_on_delivery' || $unpaid_pay_method == 'cash_on_delivery')) {
                    $store_over_flow = true;
                    $vendorWallet->collected_cash = $vendorWallet->collected_cash + ($order->order_amount - $order->partially_paid_amount);
                } elseif ($receivedBy == false) {
                    $adminWallet->manual_received = $adminWallet->manual_received + ($order->order_amount - $order->partially_paid_amount);
                } elseif ($receivedBy == 'deliveryman' && $order->delivery_man && $order->delivery_man->type == 'zone_wise') {
                    $dmWallet->collected_cash = $dmWallet->collected_cash + ($order->order_amount - $order->partially_paid_amount);
                    $dm_over_flow = true;
                }

                $adminWallet->save();
                if ($type != 'parcel') {
                    $vendorWallet->save();
                }
                if (isset($dmWallet)) {
                    $dmWallet->save();
                }

                if (isset($store_over_flow)) {
                    $this->recordCashCollection(oldCollectedCash: $vendorWallet->collected_cash, fromType: 'store', fromId: $order->store->vendor->id, amount: $order->order_amount - $order->partially_paid_amount, reference: $order->id);
                }
                if (isset($dm_over_flow)) {
                    $this->recordCashCollection(oldCollectedCash: $dmWallet->collected_cash, fromType: 'deliveryman', fromId: $order->delivery_man_id, amount: $order->order_amount - $order->partially_paid_amount, reference: $order->id);
                }

                $this->markUnpaidOrderPaymentPaid(orderId: $order->id, paymentMethod: $order->payment_method);

                DB::commit();

                if ($order->delivery_man_id && $order->delivery_man->earning == 1) {
                    $deliveryMan = $order->delivery_man;
                    if ($deliveryMan->ref_by && $deliveryMan->orders()->whereIn('order_status', ['delivered'])->count() == 0) {
                        $this->recordDeliverymanReferral(deliveryManId: $order->delivery_man_id, referType: 'referrerBonus', reference: $order->id, referrerId: $deliveryMan->ref_by);
                        $this->recordDeliverymanReferral(deliveryManId: $deliveryMan->ref_by, referType: 'referral', reference: $order->id, referrerId: $order->delivery_man_id);
                    }
                }
                if ($order->is_guest == 0) {
                    $ref_status = app(BusinessSettingService::class)->value('ref_earning_status', false);
                    if (isset($order->customer->ref_by) && $order->customer->order_count == 0 && $ref_status == 1
                        && ! storefront_wallet_disabled_for_user($order->user_id)) {
                        $ref_code_exchange_amt = app(BusinessSettingService::class)->value('ref_earning_exchange_rate', false);
                        $referar_user = app(UserService::class)->find($order->customer->ref_by);
                        $refer_wallet_transaction = $this->recordWalletTransaction($referar_user->id, $ref_code_exchange_amt, 'referrer', $order->customer->phone);

                        $notification_data = NotificationMessages::referralWalletCredit($ref_code_exchange_amt, $order?->customer?->f_name.' '.$order?->customer?->l_name, ['order_id' => 1]);

                        try {
                            if (SendNotification::channelEnabled('customer', 'customer_referral_bonus_earning', 'push_notification_status') && $referar_user?->cm_firebase_token) {
                                SendNotification::sendToDevice($referar_user?->cm_firebase_token, $notification_data);
                                app(UserNotificationService::class)->record($referar_user?->id, $notification_data);
                            }
                        } catch (\Throwable $e) {
                            Log::warning('order.order_transactions_trait.create_order_transaction_failed', [
                                'error' => $e->getMessage(),
                                'file' => $e->getFile().':'.$e->getLine(),
                            ]);
                        }

                        try {
                            app(UserService::class)->notifyFundAdded($referar_user->id);
                            if (SendNotification::canSendMail('add_fund_mail_status_user', 'customer', 'customer_add_fund_to_wallet')) {
                                SendNotification::mail($referar_user?->getRawOriginal('email'), new AddFundToWallet($refer_wallet_transaction));
                            }
                        } catch (\Exception $ex) {
                            Log::warning('order.order_transactions_trait.create_order_transaction_failed', [
                                'error' => $ex->getMessage(),
                                'file' => $ex->getFile().':'.$ex->getLine(),
                            ]);
                        }
                    }

                    $create_loyalty_point_transaction = $this->recordLoyaltyPointTransaction($order->user_id, $order->id, $order->order_amount, 'order_place');
                    if ($create_loyalty_point_transaction > 0) {
                        $notification_data = NotificationMessages::loyaltyPointsEarned($create_loyalty_point_transaction, ['order_id' => $order->id]);

                        try {
                            if (SendNotification::channelEnabled('customer', 'customer_loyalty_point_earning', 'push_notification_status') && $order->customer?->cm_firebase_token) {
                                SendNotification::sendToDevice($order->customer?->cm_firebase_token, $notification_data);
                                app(UserNotificationService::class)->record($order->user_id, $notification_data);
                            }
                        } catch (\Throwable $e) {
                            Log::warning('order.order_transactions_trait.create_order_transaction_failed', [
                                'error' => $e->getMessage(),
                                'file' => $e->getFile().':'.$e->getLine(),
                            ]);
                        }
                    }
                }
            } catch (\Exception $e) {
                DB::rollBack();

                return false;
            }
        } catch (\Exception $e) {
            return false;
        }

        return true;
    }

    public function createParcelCancelTransaction($order, $receivedBy = false)
    {
        $dm_tips_manage_status = app(BusinessSettingService::class)->value('dm_tips_status', false);
        $admin_subsidy = 0;
        $admin_coupon_discount_subsidy = 0;
        $store_discount_amount = 0;
        $flash_admin_discount_amount = 0;
        $ref_bonus_amount = 0;
        $proDiscount = 0;

        $return_fee = $order?->parcelCancellation?->return_fee ?? 0;
        if ($order->free_delivery_by == 'admin') {
            $admin_subsidy = $order->original_delivery_charge;
            $free_delivery_expense_type = 'free_delivery';
            if ($order->orderProDiscount && $order->orderProDiscount?->benefit_type === 'delivery_fee') {
                $admin_subsidy = $order->orderProDiscount->delivery_fee_reduction_amount ?? $admin_subsidy;
                if ($order->orderProDiscount->delivery_offer_type === 'partial_free') {
                    $free_delivery_expense_type = 'pro_partial_free_delivery';
                } else {
                    $free_delivery_expense_type = 'pro_free_delivery';
                }
            }
            app(ExpenseService::class)->create([
                'amount' => $admin_subsidy,
                'type' => $free_delivery_expense_type,
                'created_by' => $order->free_delivery_by,
                'order_id' => $order->id,
            ]);
        }

        if ($order->coupon_created_by == 'admin') {
            $admin_coupon_discount_subsidy = $order->coupon_discount_amount;
            app(ExpenseService::class)->create([
                'amount' => $admin_coupon_discount_subsidy,
                'type' => 'coupon_discount',
                'created_by' => $order->coupon_created_by,
                'order_id' => $order->id,
            ]);
        }
        if ($order->orderProDiscount && $order->orderProDiscount?->benefit_type === 'discount') {
            $proDiscount = $order?->orderProDiscount?->amount_saved ?? 0;
            app(ExpenseService::class)->create([
                'amount' => $proDiscount,
                'type' => 'pro_discount_on_product',
                'order_id' => $order->id,
                'created_by' => 'admin',
            ]);
        } else {
            $proDiscount = $order->orderProDiscount?->delivery_fee_reduction_amount ?? 0;
        }

        $comission = app(BusinessSettingService::class)->findValue('parcel_commission_dm');
        $dm_tips = $dm_tips_manage_status ? $order->dm_tips : 0;
        $comission = $comission ?? 0;
        $order_amount = $order->order_amount - $dm_tips - $order->additional_charge - $order->total_tax_amount + $proDiscount;

        $dm_commission = $comission ? ($order_amount / 100) * $comission : 0;
        $comission_amount = $order_amount - $dm_commission;

        DB::beginTransaction();

        $order->order_status = 'returned';
        $order->payment_status = 'paid';
        $order->save();

        $order->parcelCancellation->return_fee_payment_status = 'paid';
        $order->parcelCancellation->save();

        try {

            $adminWallet = app(AdminWalletService::class)->findOrNew(app(AdminService::class)->findSuperAdmin()->id);

            $adminWallet->total_commission_earning = $adminWallet->total_commission_earning + $comission_amount + $order->additional_charge - $admin_subsidy - $admin_coupon_discount_subsidy - $store_discount_amount - $flash_admin_discount_amount - $ref_bonus_amount - $proDiscount;

            if ($order->delivery_man) {
                $dmWallet = app(DeliveryManWalletService::class)->findOrNew($order->delivery_man_id);
                if ($order->delivery_man->earning == 1) {
                    $dmWallet->total_earning = $dmWallet->total_earning + $dm_commission + $dm_tips + $return_fee;
                    $dmWallet->collected_cash = $dmWallet->collected_cash + $return_fee;
                    $this->logParcelReturnFee($order, $return_fee);
                } else {
                    $adminWallet->total_commission_earning = $adminWallet->total_commission_earning + $dm_commission + $dm_tips + $return_fee;
                }
            } else {
                $adminWallet->total_commission_earning = $adminWallet->total_commission_earning + $dm_commission + $dm_tips + $return_fee;
            }

            if ($receivedBy == 'admin') {
                $adminWallet->digital_received = $adminWallet->digital_received + ($order->order_amount - $order->partially_paid_amount);
            } elseif ($receivedBy == false) {
                $adminWallet->manual_received = $adminWallet->manual_received + ($order->order_amount - $order->partially_paid_amount);
            } elseif ($receivedBy == 'deliveryman' && $order->delivery_man && $order->delivery_man->type == 'zone_wise') {
                $dmWallet->collected_cash = $dmWallet->collected_cash + ($order->order_amount - $order->partially_paid_amount);
                $dm_over_flow = true;
            }

            $adminWallet->save();

            app(OrderTransactionService::class)->insertMany([
                'vendor_id' => null,
                'delivery_man_id' => $order->delivery_man_id,
                'order_id' => $order->id,
                'order_amount' => $order->order_amount,
                'store_amount' => 0,
                'admin_commission' => $comission_amount + $order->additional_charge - $admin_subsidy - $admin_coupon_discount_subsidy - $ref_bonus_amount - $store_discount_amount,
                'delivery_charge' => $order->delivery_charge,
                'original_delivery_charge' => $dm_commission,
                'delivery_type_charge' => $order->delivery_type_charge,
                'surge_amount' => $order->surge_amount,
                'tax' => $order->total_tax_amount,
                'received_by' => $receivedBy ? $receivedBy : 'admin',
                'zone_id' => $order->zone_id,
                'module_id' => $order->module_id,
                'admin_expense' => $admin_subsidy + $admin_coupon_discount_subsidy + $store_discount_amount + $flash_admin_discount_amount + $ref_bonus_amount + $proDiscount,
                'store_expense' => 0,
                'status' => null,
                'dm_tips' => $dm_tips,
                'created_at' => now(),
                'updated_at' => now(),
                'delivery_fee_comission' => 0,
                'discount_amount_by_store' => 0,
                'additional_charge' => $order->additional_charge,
                'extra_packaging_amount' => $order->extra_packaging_amount ?? 0,
                'ref_bonus_amount' => $order->ref_bonus_amount ?? 0,
                'pro_discount' => $order->orderProDiscount?->amount_saved ?? 0,
                'pro_delivery_discount' => $order->orderProDiscount?->delivery_fee_reduction_amount ?? 0,
                'is_subscribed' => 0,
                'commission_percentage' => $comission,
            ]);

            if ($order->parcelCancellation->return_date) {
                $returnDate = Carbon::parse($order->parcelCancellation->return_date);
                if ($returnDate->isPast() && isset($dmWallet)) {
                    $dmWallet->collected_cash = $dmWallet->collected_cash + $order->parcelCancellation->dm_penalty_fee ?? 0;
                    $this->logParcelPenaltyFee($order, $order->parcelCancellation->dm_penalty_fee ?? 0);
                }
            }

            if (isset($dmWallet)) {
                $dmWallet->save();
            }

            if (isset($dm_over_flow)) {
                $this->recordCashCollection(oldCollectedCash: $dmWallet->collected_cash, fromType: 'deliveryman', fromId: $order->delivery_man_id, amount: $order->order_amount - $order->partially_paid_amount, reference: $order->id);
            }

            $this->markUnpaidOrderPaymentPaid(orderId: $order->id, paymentMethod: $order->payment_method);

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();

            return false;
        }

        return true;
    }

    public function refundOrderTransaction($order)
    {
        $order_transaction = $order->transaction;
        if ($order_transaction == null || $order->store == null) {
            return false;
        }
        $received_by = $order_transaction->received_by;

        $adminWallet = app(AdminWalletService::class)->findOrNew(app(AdminService::class)->findSuperAdmin()->id);

        $vendorWallet = app(StoreWalletService::class)->findOrNewForVendor($order->store->vendor->id);

        $adminWallet->total_commission_earning = $adminWallet->total_commission_earning - $order_transaction->admin_commission + $order_transaction->delivery_fee_comission;

        $vendorWallet->total_earning = $vendorWallet->total_earning - $order_transaction->store_amount;

        $refund_amount = $order->order_amount - $order->additional_charge - $order->extra_packaging_amount;

        $status = 'refunded_with_delivery_charge';
        if ($order->order_status == 'delivered' || $order->order_status == 'refund_requested') {
            // Withhold the WHOLE delivery figure, not just the base/surge column — the
            // express/slightly-delay premium lives in its own delivery_type_charge and was
            // previously refunded back to the customer despite the delivery having happened
            // (TC_357). Same fix as RefundService::create().
            $refund_amount = $order->order_amount - $order->additional_charge - $order->extra_packaging_amount - $order->delivery_charge - $order->delivery_type_charge - $order->dm_tips;
            $status = 'refunded_without_delivery_charge';
        } else {
            $adminWallet->delivery_charge = $adminWallet->delivery_charge - $order_transaction->delivery_charge;
        }
        try {
            DB::beginTransaction();
            $partially_paid = app(OrderPaymentService::class)->hasCashOnDeliveryForOrder($order->id) ?? false;

            if ($partially_paid) {
                $refund_amount = $refund_amount - $order->partially_paid_amount;
            }
            if ($received_by == 'admin') {
                if ($order->delivery_man_id && $order->payment_method != 'cash_on_delivery') {
                    $adminWallet->digital_received = $adminWallet->digital_received - $refund_amount;
                } else {
                    $adminWallet->manual_received = $adminWallet->manual_received - $refund_amount;
                }
            } elseif ($received_by == 'store') {
                $vendorWallet->collected_cash = $vendorWallet->collected_cash - $refund_amount;
            }

            $order_transaction->status = $status;
            $order_transaction->save();
            $adminWallet->save();
            $vendorWallet->save();
            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();

            return false;
        }

        return true;
    }

    /**
     * Book one vendor discount as expense rows, and answer what the store bears of it.
     *
     * A commission store splits it: the admin reimburses its commission percentage of the
     * discount and the store carries the rest, so two rows are written. A subscription store
     * pays no commission, so there is nothing for the admin to reimburse and the whole amount
     * is the store's.
     *
     * Extracted so the happy-hour branch can put a bundle's share of store_discount_amount
     * through exactly the rule the no-happy-hour branch puts the whole figure through, rather
     * than a second copy of it that could drift. The split itself is unchanged -- who bears a
     * bundle discount, and in what proportion, is a commercial question, not one to answer here.
     *
     * Returns BOTH shares, not just the store's. The admin's share is an expense row here AND a
     * term in order_transactions.admin_expense; returning only the store's left the caller's
     * $amount_admin at its initial 0, so the row was written but the column never counted it --
     * admin_expense came out short by the commission share of every item, bundle and store
     * discount that passed through here.
     *
     * @return array{store: float, admin: float} the shares of $amount each side carries
     */
    private function bookDiscountExpense($order, float $amount, string $type, $comission, bool $subscription): array
    {
        if ($subscription) {
            app(ExpenseService::class)->create([
                'amount' => $amount,
                'type' => $type,
                'created_by' => 'vendor',
                'order_id' => $order->id,
                'store_id' => $order->store->id,
            ]);

            return ['store' => $amount, 'admin' => 0.0];
        }

        $amount_admin = $comission ? ($amount / 100) * $comission : 0;
        $store_share = $amount - $amount_admin;

        app(ExpenseService::class)->create([
            'amount' => $store_share,
            'type' => $type,
            'created_by' => 'vendor',
            'order_id' => $order->id,
            'store_id' => $order->store->id,
        ]);

        app(ExpenseService::class)->create([
            'amount' => $amount_admin,
            'type' => $type,
            'created_by' => 'admin',
            'order_id' => $order->id,
        ]);

        return ['store' => $store_share, 'admin' => (float) $amount_admin];
    }

    private function creditCashbackToWallet($order)
    {

        $refer_wallet_transaction = $this->recordWalletTransaction($order?->cashback_history?->user_id, $order?->cashback_history?->calculated_amount, 'CashBack', $order->id);
        if ($refer_wallet_transaction != false) {
            app(ExpenseService::class)->create([
                'amount' => $order?->cashback_history?->calculated_amount,
                'type' => 'CashBack',
                'created_by' => 'admin',
                'order_id' => $order->id,
                'user_id' => $order->customer?->id,
            ]);
            $order?->cashback_history?->cashBack?->increment('total_used');

            $notification_data = NotificationMessages::cashbackCredited($order?->cashback_history?->calculated_amount, ['order_id' => $order->id]);

            try {
                if ($order->customer?->cm_firebase_token && SendNotification::channelEnabled('customer', 'customer_cashback', 'push_notification_status')) {
                    SendNotification::sendToDevice($order->customer?->cm_firebase_token, $notification_data);
                    app(UserNotificationService::class)->record($order->customer?->id, $notification_data);
                }
            } catch (\Throwable $e) {
                Log::warning('order.order_transactions_trait.credit_cashback_to_wallet_failed', [
                    'error' => $e->getMessage(),
                    'file' => $e->getFile().':'.$e->getLine(),
                ]);
            }
        }

        return true;
    }

    private function recordDeliverymanReferral($deliveryManId, $referType, $referrerId, $reference)
    {

        $settings = app(BusinessSettingService::class)->valuesFor(['dm_referal_status', 'dm_referal_amount', 'dm_referal_bonus']);

        if (data_get($settings, 'dm_referal_status') != 1) {
            return ['status_code' => 403, 'code' => 'Referal', 'message' => translate('Referal option is not enabled')];
        } elseif ($referType == 'referral' && data_get($settings, 'dm_referal_amount') <= 0) {
            return ['status_code' => 403, 'code' => 'Referal', 'message' => translate('Referal option is not enabled')];
        } elseif ($referType == 'referrerBonus' && data_get($settings, 'dm_referal_bonus') <= 0) {
            return ['status_code' => 403, 'code' => 'Referal', 'message' => translate('Referal option is not enabled')];
        }

        $deliveryMan = app(DeliveryManService::class)->findBasic($deliveryManId);
        if (! $deliveryMan) {
            return ['status_code' => 403, 'code' => 'wallet', 'message' => translate('No data found')];
        } elseif ($deliveryMan->earning != 1) {
            return ['status_code' => 403, 'code' => 'Referal', 'message' => translate('Wallet not enabled')];
        }

        $referralHistory = new DeliverymanReferralHistory;
        $referralHistory->delivery_man_id = $deliveryMan->id;

        $amount = $referType == 'referrerBonus' ? data_get($settings, 'dm_referal_bonus', 0) : data_get($settings, 'dm_referal_amount', 0);

        $referralHistory->amount = $amount;

        $referralHistory->referrer_id = $referrerId;
        $referralHistory->refer_type = $referType;
        $referralHistory->reference = $reference;
        $referralHistory->transaction_id = Str::uuid();

        $dmWallet = app(DeliveryManWalletService::class)->findOrNew($deliveryMan->id);
        $dmWallet->total_earning = $dmWallet->total_earning + $amount;

        try {
            DB::beginTransaction();
            $referralHistory->save();
            $referralHistory->transaction_id = Helpers::generate_transaction_id($referralHistory);
            $referralHistory->save();
            $dmWallet->save();

            app(ExpenseService::class)->create([
                'amount' => $amount,
                'type' => 'dm_'.$referType,
                'created_by' => 'admin',
                'order_id' => null,
                'delivery_man_id' => $deliveryManId,
            ]);

            DB::commit();

        } catch (\Exception $exception) {
            DB::rollback();

            return ['status_code' => 403, 'code' => 'loyalty_point', 'message' => translate('messages.Something went wrong')];
        }

        try {
            $data = NotificationMessages::deliveryManReferralBonus($amount, $referralHistory->id);
            if (SendNotification::channelEnabled('deliveryman', 'deliveryman_referral_bonus', 'push_notification_status') && $deliveryMan->fcm_token) {
                SendNotification::sendToDevice($deliveryMan->fcm_token, $data);
                app(UserNotificationService::class)->recordForDeliveryMan($deliveryMan->id, $data);
            }

        } catch (\Exception $exception) {
            Log::warning('order.order_transactions_trait.record_deliveryman_referral_failed', [
                'error' => $exception->getMessage(),
                'file' => $exception->getFile().':'.$exception->getLine(),
            ]);
        }

        return true;
    }
}
