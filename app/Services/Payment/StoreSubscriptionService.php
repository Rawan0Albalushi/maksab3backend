<?php

namespace App\Services\Payment;

use App\CentralLogics\Helpers;
use App\Models\StoreSubscription;
use App\Library\Payer;
use App\Library\Payment as PaymentInfo;
use App\Library\Receiver;
use App\Mail\SubscriptionRenewOrShift;
use App\Mail\SubscriptionSuccessful;
use App\Models\BusinessSetting;
use App\Models\Item;
use App\Models\Store;
use App\Models\StoreWallet;
use App\Models\SubscriptionBillingAndRefundHistory;
use App\Models\SubscriptionPackage;
use App\Models\SubscriptionTransaction;
use App\Scopes\StoreScope;
use App\Services\Payment\PaymentLinkService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Modules\Rental\Emails\ProviderSubscriptionRenewOrShift;
use Modules\Rental\Emails\ProviderSubscriptionSuccessful;
use Modules\Rental\Entities\Vehicle;
use Modules\Service\Emails\ProviderSubscriptionRenewOrShift as ServiceProviderSubscriptionRenewOrShift;
use Modules\Service\Emails\ProviderSubscriptionSuccessful as ServiceProviderSubscriptionSuccessful;
use Modules\Service\Entities\Service as ServiceEntity;

use App\Mail\SubscriptionCancel;
use App\Services\BaseService;
use Modules\Rental\Emails\ProviderSubscriptionCancel;
use Modules\Service\Emails\ProviderSubscriptionCancel as ServiceProviderSubscriptionCancel;
use App\Services\Payment\SubscriptionPackageService;
use App\Services\Payment\SubscriptionBillingAndRefundHistoryService;
use App\Services\Store\StoreWalletService;
use App\Services\Store\StoreService;
use App\Support\Notification\SendNotification;
use App\Support\Notification\NotificationMessages;
use App\Services\System\BusinessSettingService;
use Illuminate\Support\Facades\Log;

class StoreSubscriptionService extends BaseService
{
    public function choosePlan(mixed $store, array $data): array
    {
        if (($data['business_plan'] ?? null) === 'commission') {
            $store->store_business_model = 'commission';
            $store->save();
            $this->deactivateForStore($store->id);

            return $this->planChosenPayload($store, 'commission');
        }

        $package = app(SubscriptionPackageService::class)->findWithTranslations($data['package_id'] ?? null);

        if (! $package) {
            return ['unresolved' => true];
        }

        $pendingBill = app(SubscriptionBillingAndRefundHistoryService::class)->pendingBillTotal($store->id);

        if (! in_array($data['payment_gateway'] ?? null, ['wallet', 'free_trial'], true)) {
            return ['redirect_link' => $this->generatePaymentLink(
                store_id: $store->id,
                package_id: $package->id,
                payment_gateway: $data['payment_gateway'] ?? null,
                payment_platform: $data['payment_platform'] ?? 'web',
                url: $data['callback'] ?? null,
                pending_bill: $pendingBill,
                type: $data['type'] ?? null
            )];
        }

        if (($data['payment_gateway'] ?? null) === 'wallet') {
            $wallet = app(StoreWalletService::class)->findOrNewForVendor($store->vendor_id);
            $balance = app(BusinessSettingService::class)->value('wallet_status', false) == 1 ? ($wallet->balance ?? 0) : 0;

            if ($balance <= $package->price) {
                return ['insufficient_balance' => true];
            }

            if ($this->applyPlan(store_id: $store->id, package_id: $package->id, payment_method: 'wallet',
                discount: 0, pending_bill: $pendingBill, reference: 'wallet_payment_by_vendor', type: $data['type'] ?? null) != false) {
                $wallet->total_withdrawn = ($wallet->total_withdrawn ?? 0) + $package->price;
                $wallet->save();
            }
        }

        if (($data['payment_gateway'] ?? null) === 'free_trial') {
            $this->applyPlan(store_id: $store->id, package_id: $package->id, payment_method: 'free_trial',
                discount: 0, pending_bill: $pendingBill, reference: 'free_trial', type: 'new_join');
        }

        return $this->planChosenPayload($store, 'subscription');
    }

    public function cancel(array $filters = []): bool
    {
        return (bool) StoreSubscription::where([
            'id' => $filters['subscription_id'] ?? null,
            'store_id' => $filters['store_id'] ?? null,
        ])->update([
            'is_canceled' => 1,
            'canceled_by' => 'store',
        ]);
    }

    public function notifyCancellation(mixed $store): void
    {
        try {
            [$actor, $key, $mailKey, $mailable] = $this->cancellationChannel($store);

            if ($this->cancellationPushEnabled($actor, $key, $store) && $store->vendor?->firebase_token) {
                $data = NotificationMessages::storeSubscriptionCanceled();
                SendNotification::pushToVendor($store->vendor_id, $store->vendor->firebase_token, $data);
            }

            if (SendNotification::canSendMail($mailKey)
                && $this->cancellationMailEnabled($actor, $key, $store)) {
                SendNotification::mail($store->getRawOriginal('email'), new $mailable($store->name));
            }
        } catch (\Exception $exception) {
            Log::error('payment.store_subscription_service.notify_cancellation_failed', [
                'error' => $exception->getMessage(),
                'file' => $exception->getFile().':'.$exception->getLine(),
            ]);
        }
    }

    public function notifyPlanCancellation(mixed $store): void
    {
        try {
            [$actor, $key, $mailKey, $mailable] = $this->cancellationChannel($store);

            if ($this->cancellationPushEnabled($actor, $key, $store) && $store?->vendor?->firebase_token) {
                $data = NotificationMessages::subscriptionCanceled();
                SendNotification::pushToVendor($store?->vendor_id, $store?->vendor?->firebase_token, $data);
            }

            $mailGate = match ($key) {
                'provider_subscription_cancel' => 'canSendRentalMail',
                'service_provider_subscription_cancel' => 'canSendServiceMail',
                default => 'canSendMail',
            };

            if (SendNotification::$mailGate($mailKey, $actor, $key, $store?->id)) {
                SendNotification::mail($store?->getRawOriginal('email'), new $mailable($store->name));
            }
        } catch (\Exception $exception) {
            Log::error('payment.store_subscription_service.notify_plan_cancellation_failed', [
                'error' => $exception->getMessage(),
                'file' => $exception->getFile().':'.$exception->getLine(),
            ]);
        }
    }

    public function productLimitSummary(array $filters = []): array
    {
        $conditions = Helpers::subscriptionConditionsCheck(
            store_id: $filters['store_id'] ?? null,
            package_id: $filters['package_id'] ?? null
        );

        $disableItemCount = (int) data_get($conditions, 'disable_item_count', 0);

        $cashBacks = $this->refundPreview($filters);

        return [
            'disable_item_count' => max(0, $disableItemCount),
            'back_amount' => (float) data_get($cashBacks, 'back_amount', 0),
            'days' => (int) data_get($cashBacks, 'days', 0),
        ];
    }

    public function closeExpired(mixed $currentDate): int
    {
        return StoreSubscription::withoutGlobalScopes()
            ->where('status', 1)
            ->whereDate('expiry_date', '<=', $currentDate)
            ->update(['status' => 0]);
    }

    private function planChosenPayload(mixed $store, string $model): array
    {
        return [
            'store_business_model' => $model,
            'logo' => $store->logo,
            'message' => translate('messages.Application placed successfully'),
        ];
    }

    private function cancellationChannel(mixed $store): array
    {
        $moduleType = $store->module?->module_type;

        if ($moduleType === 'rental' && addon_published_status('Rental')) {
            return ['provider', 'provider_subscription_cancel', 'rental_subscription_cancel_mail_status_provider', ProviderSubscriptionCancel::class];
        }

        if ($moduleType === 'service' && addon_published_status('Service')) {
            return ['provider', 'service_provider_subscription_cancel', 'service_subscription_cancel_mail_status_provider', ServiceProviderSubscriptionCancel::class];
        }

        return ['store', 'store_subscription_cancel', 'subscription_cancel_mail_status_store', SubscriptionCancel::class];
    }

    private function cancellationPushEnabled(string $actor, string $key, mixed $store): bool
    {
        return $this->cancellationChannelEnabled($actor, $key, $store, 'push_notification_status');
    }

    private function cancellationMailEnabled(string $actor, string $key, mixed $store): bool
    {
        return $this->cancellationChannelEnabled($actor, $key, $store, 'mail_status');
    }

    private function cancellationChannelEnabled(string $actor, string $key, mixed $store, string $channel): bool
    {
        return (bool) match ($key) {
            'provider_subscription_cancel' => SendNotification::rentalChannelEnabled($actor, $key, $channel, $store->id),
            'service_provider_subscription_cancel' => SendNotification::serviceChannelEnabled($actor, $key, $channel, $store->id),
            default => SendNotification::channelEnabled($actor, $key, $channel, $store->id),
        };
    }

    private function deactivateForStore(mixed $storeId): void
    {
        StoreSubscription::where(['store_id' => $storeId])->update(['status' => 0]);
    }

    private function refundPreview(array $filters): array
    {
        $store = app(StoreService::class)->findWithSubscriptionRelations($filters['store_id'] ?? null, ['store_sub_update_application']);

        $application = $store?->store_sub_update_application;

        $eligible = $store
            && $store->store_business_model === 'subscription'
            && $application
            && (int) $application->status === 1
            && (int) $application->is_canceled === 0
            && (int) $application->is_trial === 0
            && $application->package_id != ($filters['package_id'] ?? null);

        return $eligible
            ? (array) Helpers::calculateSubscriptionRefundAmount(store: $store, return_data: true)
            : [];
    }


    public function conditionsCheck($store_id, $package_id)
    {
        $store = Store::findOrFail($store_id);
        $package = SubscriptionPackage::withoutGlobalScope('translate')->with('translations')->find($package_id);
        if (! $package) {
            return null;
        }
        if ($store->module_type == 'rental') {
            $total_food = $store->vehicles()->count();
        } elseif ($store->module_type == 'service') {
            $total_food = $store->services()->count();
        } else {
            $total_food = $store->items()->withoutGlobalScope(StoreScope::class)->count();
        }
        if ($package->max_product != 'unlimited' && $total_food >= $package->max_product) {
            return ['disable_item_count' => $total_food - $package->max_product];
        }

        return null;
    }

    public function applyPlan($store_id, $package_id, $payment_method, $discount = 0, $pending_bill = 0, $reference = null, $type = null)
    {
        $store = Store::find($store_id);
        $package = SubscriptionPackage::withoutGlobalScope('translate')->with('translations')->find($package_id);
        $add_days = 0;
        $add_orders = 0;

        try {
            $store_subscription = $store->store_sub;
            $store_old_subscription = $store->store_sub_update_application;
            if (isset($store_subscription) && $type == 'renew') {
                $store_subscription->total_package_renewed = $store_subscription->total_package_renewed + 1;

                $day_left = $store_subscription->expiry_date_parsed->format('Y-m-d');
                if (Carbon::now()->diffInDays($day_left, false) > 0 && $store_subscription->is_canceled != 1) {
                    $add_days = Carbon::now()->subDays(1)->diffInDays($day_left, false);
                }
                if ($store_subscription->max_order != 'unlimited' && $store_subscription->max_order > 0) {
                    $add_orders = $store_subscription->max_order;
                }

            } elseif ($store_old_subscription && $store_old_subscription->package_id == $package->id && $type == 'renew') {
                $store_subscription = $store_old_subscription;
                $store_subscription->total_package_renewed = $store_subscription->total_package_renewed + 1;
            } else {
                $this->calculateRefundAmount($store);
                StoreSubscription::where('store_id', $store->id)->update([
                    'status' => 0,
                ]);
                $store_subscription = new StoreSubscription;
                $store_subscription->total_package_renewed = 0;

            }

            $store_subscription->is_trial = 0;
            $store_subscription->renewed_at = now();
            $store_subscription->package_id = $package->id;
            $store_subscription->store_id = $store->id;
            if ($payment_method == 'free_trial') {

                $free_trial_period = (int) Helpers::get_business_settings('subscription_free_trial_days') ?? 1;

                $store_subscription->expiry_date = Carbon::now()->addDays($free_trial_period)->format('Y-m-d');
                $store_subscription->validity = $free_trial_period;
            } else {
                $store_subscription->expiry_date = Carbon::now()->addDays((int) ($package->validity + $add_days))->format('Y-m-d');
                $store_subscription->validity = $package->validity + $add_days;
            }
            if ($package->max_order != 'unlimited') {
                $store_subscription->max_order = $package->max_order + $add_orders;
            } else {
                $store_subscription->max_order = $package->max_order;
            }

            $store_subscription->max_product = $package->max_product;
            $store_subscription->pos = $package->pos;
            $store_subscription->mobile_app = $package->mobile_app;
            $store_subscription->chat = $package->chat;
            $store_subscription->review = $package->review;
            $store_subscription->self_delivery = $package->self_delivery;
            $store_subscription->is_canceled = 0;
            $store_subscription->canceled_by = 'none';

            $store->item_section = 1;
            $store->pos_system = 1;
            if ($type == 'new_join' && $store->vendor?->status == 0) {
                $store->status = 0;
                $store_subscription->status = 0;

            } else {
                $store->status = 1;
                $store_subscription->status = 1;

            }

            if ($store->free_delivery == 1 && $package->self_delivery == 1) {
                $store->free_delivery = 1;
            } else {
                $store->free_delivery = 0;
                $store->coupon()->where('created_by', 'vendor')->where('coupon_type', 'free_delivery')->delete();
            }

            $store->package_id = $package->id;
            $store->reviews_section = 1;
            $store->self_delivery_system = 1;
            $store->store_business_model = 'subscription';

            $subscription_transaction = new SubscriptionTransaction;

            $subscription_transaction->package_id = $package->id;
            $subscription_transaction->store_id = $store->id;
            $subscription_transaction->price = $package->price;

            $subscription_transaction->validity = $package->validity;
            $subscription_transaction->paid_amount = $package->price - (($package->price * $discount) / 100) + $pending_bill;

            $subscription_transaction->payment_status = 'success';
            $subscription_transaction->created_by = in_array($payment_method, ['wallet_payment_by_admin', 'manual_payment_by_admin', 'plan_shift_by_admin']) ? 'Admin' : 'Store';

            if ($payment_method == 'free_trial') {
                $subscription_transaction->validity = $free_trial_period;
                $subscription_transaction->paid_amount = 0;
                $subscription_transaction->is_trial = 1;
                $store_subscription->is_trial = 1;
            } elseif ($payment_method == 'pay_now') {
                $subscription_transaction->payment_status = 'on_hold';
                $subscription_transaction->transaction_status = 0;
                $store_subscription->status = 0;
            }

            $subscription_transaction->payment_method = $payment_method;
            $subscription_transaction->reference = $reference ?? null;
            $subscription_transaction->discount = $discount ?? 0;
            if (in_array($type, ['renew', 'free_trial'])) {
                $subscription_transaction->plan_type = $type;
            } elseif (StoreSubscription::where('store_id', $store->id)->where('is_trial', 0)->count() > 0 || $reference == 'plan_shift_by_admin') {
                $subscription_transaction->plan_type = 'new_plan';
            }

            $subscription_transaction->package_details = [
                'pos' => $package->pos,
                'review' => $package->review,
                'self_delivery' => $package->self_delivery,
                'chat' => $package->chat,
                'mobile_app' => $package->mobile_app,
                'max_order' => $package->max_order,
                'max_product' => $package->max_product,
            ];
            DB::beginTransaction();
            $store->save();
            $subscription_transaction->save();
            $store_subscription->save();
            DB::commit();
            $subscription_transaction->store_subscription_id = $store_subscription->id;
            $subscription_transaction->save();

            SubscriptionBillingAndRefundHistory::where([
                'store_id' => $store->id,
                'transaction_type' => 'pending_bill',
                'is_success' => 0,
            ])->update([
                'is_success' => 1,
                'reference' => 'payment_via_'.$payment_method.' _transaction_id_'.$subscription_transaction->id,
            ]);

            if ($reference == 'plan_shift_by_admin') {
                $billing = new SubscriptionBillingAndRefundHistory;
                $billing->store_id = $store->id;
                $billing->subscription_id = $store_subscription->id;
                $billing->package_id = $store_subscription->package_id;
                $billing->transaction_type = 'pending_bill';
                $billing->is_success = 0;
                $billing->amount = $package->price;
                $billing->save();
            }

        } catch (\Exception $e) {
            DB::rollBack();
            info(["line___{$e->getLine()}", $e->getMessage()]);

            return false;
        }

        if (data_get($this->conditionsCheck(store_id: $store->id, package_id: $package->id), 'disable_item_count') > 0) {
            $disable_item_count = data_get($this->conditionsCheck(store_id: $store->id, package_id: $package->id), 'disable_item_count');
            $store->item_section = 0;
            $store->save();
            if ($store->module_type == 'rental') {
                Vehicle::where('provider_id', $store->id)->oldest()->take($disable_item_count)->update([
                    'status' => 0,
                ]);
            } elseif ($store->module_type == 'service' && service_addon_active()) {
                ServiceEntity::where('store_id', $store->id)->oldest()->take($disable_item_count)->update([
                    'status' => 0,
                ]);
            } else {
                Item::where('store_id', $store->id)->oldest()->take($disable_item_count)->update([
                    'status' => 0,
                ]);
            }
        }

        if (! (in_array($payment_method, ['manual_payment_by_admin', 'plan_shift_by_admin']) && $store_old_subscription == null)) {
            $this->sendPlanNotifications($store, $type, $subscription_transaction);
        }

        return $subscription_transaction->id;
    }

    private function sendPlanNotifications($store, $type, $subscription_transaction)
    {
        try {
            $module_type = $store->module->module_type;

            $pushSpec = match (true) {
                $module_type == 'rental' => [
                    'gate' => 'rentalChannelEnabled',
                    'audience' => 'provider',
                    'renew' => 'provider_subscription_renew',
                    'shift' => 'provider_subscription_shift',
                    'success' => 'provider_subscription_success',
                ],
                $module_type == 'service' => [
                    'gate' => 'serviceChannelEnabled',
                    'audience' => 'provider',
                    'renew' => 'service_provider_subscription_renew',
                    'shift' => 'service_provider_subscription_shift',
                    'success' => 'service_provider_subscription_success',
                ],
                default => [
                    'gate' => 'channelEnabled',
                    'audience' => 'store',
                    'renew' => 'store_subscription_renew',
                    'shift' => 'store_subscription_shift',
                    'success' => 'store_subscription_success',
                ],
            };

            $pushGate = $pushSpec['gate'];

            if ($type == 'renew') {
                $push_notification_status = SendNotification::$pushGate($pushSpec['audience'], $pushSpec['renew'], 'push_notification_status', $store->id);
                $title = translate('Subscription Renewed');
                $des = translate('Your subscription successfully renewed');
            } elseif ($type != 'renew') {
                $des = translate('Your subscription successfully shifted');
                $title = translate('Subscription shifted');
                $push_notification_status = SendNotification::$pushGate($pushSpec['audience'], $pushSpec['shift'], 'push_notification_status', $store->id);
            }

            if ($push_notification_status && $store?->vendor?->firebase_token) {
                $data = NotificationMessages::storeSubscription($title ?? '', $des ?? '');
                SendNotification::pushToVendor($store?->vendor_id, $store?->vendor?->firebase_token, $data);
            }

            $mailSpec = match (true) {
                $module_type == 'rental' => [
                    'gate' => 'canSendRentalMail',
                    'renew' => ['rental_subscription_renew_mail_status_provider', 'provider', 'provider_subscription_renew'],
                    'shift' => ['rental_subscription_shift_mail_status_provider', 'provider', 'provider_subscription_shift'],
                    'success' => ['rental_subscription_successful_mail_status_provider', 'provider', 'provider_subscription_success'],
                    'renewOrShiftMail' => ProviderSubscriptionRenewOrShift::class,
                    'successMail' => ProviderSubscriptionSuccessful::class,
                ],
                $module_type == 'service' && service_addon_active() => [
                    'gate' => 'canSendServiceMail',
                    'renew' => ['service_subscription_renew_mail_status_provider', 'provider', 'service_provider_subscription_renew'],
                    'shift' => ['service_subscription_shift_mail_status_provider', 'provider', 'service_provider_subscription_shift'],
                    'success' => ['service_subscription_successful_mail_status_provider', 'provider', 'service_provider_subscription_success'],
                    'renewOrShiftMail' => ServiceProviderSubscriptionRenewOrShift::class,
                    'successMail' => ServiceProviderSubscriptionSuccessful::class,
                ],
                default => [
                    'gate' => 'canSendMail',
                    'renew' => ['subscription_renew_mail_status_store', 'store', 'store_subscription_renew'],
                    'shift' => ['subscription_shift_mail_status_store', 'store', 'store_subscription_shift'],
                    'success' => ['subscription_successful_mail_status_store', 'store', 'store_subscription_success'],
                    'renewOrShiftMail' => SubscriptionRenewOrShift::class,
                    'successMail' => SubscriptionSuccessful::class,
                ],
            };

            if (config('mail.status')) {
                $gate = $mailSpec['gate'];
                $email = $store?->getRawOriginal('email');
                $renewOrShiftKey = $type == 'renew' ? 'renew' : 'shift';
                [$template, $audience, $key] = $mailSpec[$renewOrShiftKey];

                if (SendNotification::$gate($template, $audience, $key, $store->id)) {
                    SendNotification::mail($email, new $mailSpec['renewOrShiftMail']($type, $store->name));
                }

                [$template, $audience, $key] = $mailSpec['success'];

                if (SendNotification::$gate($template, $audience, $key, $store->id)) {
                    $url = route('subscription_invoice', ['id' => base64_encode($subscription_transaction->id)]);
                    SendNotification::mail($email, new $mailSpec['successMail']($store->name, $url));
                }
            }

            $success_push_status = SendNotification::$pushGate($pushSpec['audience'], $pushSpec['success'], 'push_notification_status', $store->id);
            if ($success_push_status && $store?->vendor?->firebase_token) {
                $data = NotificationMessages::subscriptionSuccessful();
                SendNotification::pushToVendor($store?->vendor_id, $store?->vendor?->firebase_token, $data);
            }

        } catch (\Exception $ex) {
            Log::error('payment.store_subscription_service.send_plan_notifications_failed', [
                'error' => $ex->getMessage(),
                'file' => $ex->getFile().':'.$ex->getLine(),
            ]);
        }

        return true;
    }

    public function generatePaymentLink($store_id, $package_id, $payment_gateway, $url, $pending_bill = 0, $type = 'payment', $payment_platform = 'web')
    {
        $store = Store::where('id', $store_id)->first();
        $package = SubscriptionPackage::where('id', $package_id)->first();
        $type == null ? 'payment' : $type;

        $payer = new Payer(
            $store->name,
            $store->email,
            $store->phone,
            ''
        );
        $store_logo = BusinessSetting::where(['key' => 'logo'])->first();
        $additional_data = [
            'business_name' => Helpers::get_business_settings('business_name'),
            'business_logo' => Helpers::get_full_url('business', $store_logo?->value, $store_logo?->storage[0]?->value ?? 'public'),
        ];
        $payment_info = new PaymentInfo(
            success_hook: 'sub_success',
            failure_hook: 'sub_fail',
            currency_code: Helpers::currency_code(),
            payment_method: $payment_gateway,
            payment_platform: $payment_platform,
            payer_id: $store->id,
            receiver_id: $package->id,
            additional_data: $additional_data,
            payment_amount: $package->price + $pending_bill,
            external_redirect_link: $url,
            attribute: 'store_subscription_'.$type,
            attribute_id: $package->id,
        );
        $receiver_info = new Receiver('Admin', 'example.png');
        $redirect_link = PaymentLinkService::generateLink($payer, $payment_info, $receiver_info);

        return $redirect_link;
    }

    public function calculateRefundAmount($store, $return_data = null)
    {
        $store?->loadMissing(['store_sub', 'store_sub_trans']);

        $store_subscription = $store->store_sub;
        if ($store_subscription && $store_subscription?->is_canceled === 0 && $store_subscription?->is_trial === 0) {
            $day_left = $store_subscription->expiry_date_parsed->format('Y-m-d');
            if (Carbon::now()->diffInDays($day_left, false) > 0) {
                $add_days = Carbon::now()->diffInDays($day_left, false);
                $validity = $store_subscription?->validity;
                $subscription_usage_max_time = Helpers::get_business_settings('subscription_usage_max_time') ?? 50;
                $subscription_usage_max_time = ($validity * $subscription_usage_max_time) / 100;

                if (($validity - $add_days) < $subscription_usage_max_time) {
                    $per_day = $store->store_sub_trans->price / $store->store_sub_trans->validity;
                    $back_amount = $per_day * $add_days;

                    if ($return_data == true) {
                        return ['back_amount' => $back_amount, 'days' => $add_days];
                    }

                    $vendorWallet = StoreWallet::firstOrNew(
                        ['vendor_id' => $store->vendor_id]
                    );
                    $vendorWallet->total_earning = $vendorWallet->total_earning + $back_amount;
                    $vendorWallet->save();

                    $refund = new SubscriptionBillingAndRefundHistory;
                    $refund->store_id = $store->id;
                    $refund->subscription_id = $store_subscription->id;
                    $refund->package_id = $store_subscription->package_id;
                    $refund->transaction_type = 'refund';
                    $refund->is_success = 1;
                    $refund->amount = $back_amount;
                    $refund->reference = 'validity_left_'.$add_days;
                    $refund->save();

                }
            }

        }

        return true;
    }
}
