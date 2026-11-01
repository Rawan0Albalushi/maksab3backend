<?php

namespace App\Traits\Payment;

use App\Models\ProCustomerBenefitSetting;
use App\Models\OrderProDiscount;
use App\Models\ProCustomerSubscription;
use App\Models\ProCustomerSubscriptionPlan;
use App\Models\User;
use App\Models\WalletTransaction;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use App\Services\Payment\ProCustomerSubscriptionService;
use App\Services\Payment\ProCustomerTransactionService;
use App\Services\Payment\ProCustomerSubscriptionPlanService;
use App\Services\Order\OrderProDiscountService;
use App\Services\System\DataSettingService;
use App\Services\System\ModuleService;
use App\Services\System\NotificationMessageService;
use App\Services\System\UserNotificationService;
use App\Services\Customer\UserService;
use App\Services\Payment\ProCustomerBenefitSettingService;
use App\Support\Notification\SendNotification;
use App\Support\Notification\NotificationMessages;
use App\Support\Notification\NotificationText;
use Illuminate\Support\Facades\Log;

trait ProCustomerSubscriptionTrait
{
    public const EXPIRY_SWEEP_CHUNK_SIZE = 25;
    public function getProCustomerOffer($userId, bool $incrementCount = false, bool $showOnlyActivePlan = true, ?string $moduleType = null): array
    {
        $fail = static fn(string $message, ?array $planDetails = null) => [
            'status' => false, 'message' => $message, 'benefit' => null, 'plan_details' => $planDetails,
        ];

        if (!$userId) return $fail('no_user');

        $user = app(UserService::class)->find($userId);
        if (!$user) return $fail('no_user');


        $subscription = app(ProCustomerSubscriptionService::class)->findLatestForUser($user->id, $showOnlyActivePlan);

        if (!$subscription) return $fail('no_active_subscription');

        $rows = app(DataSettingService::class)->getValuesByType('pro_customer_benefits');

        $type = match (true) {
            (int) ($rows['discount_status'] ?? 0) === 1     => 'discount',
            (int) ($rows['delivery_fee_status'] ?? 0) === 1 => 'delivery_fee',
            (int) ($rows['coupon_status'] ?? 0) === 1       => 'coupon',
            default                                          => null,
        };

        if (!$type) return $fail('no_benefit_enabled');

        $latestTransaction = null;
        if (!$showOnlyActivePlan || $incrementCount) {
            $latestTransaction = app(ProCustomerTransactionService::class)->findLatestForSubscription($user->id, $subscription->id);
        }

        $planDetails = null;
        if (!$showOnlyActivePlan) {
            $totals = app(OrderProDiscountService::class)->savingsTotals($user->id, $latestTransaction?->id);

            $now   = now();
            $endAt = $subscription->end_at;

            $planDetails = [
                'plan_name'      => $subscription->plan_name,
                'total_saved'    => (float) ($totals->total_saved ?? 0),
                'total_orders'   => (int) ($totals->total_orders ?? 0),
                'start_at'       => $subscription->start_at?->format('Y-m-d'),
                'end_at'         => $endAt ? $endAt->format('Y-m-d') : null,
                'days_remaining' => $endAt ? max(0, $now->startOfDay()->diffInDays($endAt->copy()->startOfDay(), false)) : null,
                'paid_by'        => $latestTransaction?->payment_method,
            ];
        }

        $benefit = [
            'type'            => $type,
            'subscription_id' => $subscription->id,
            'plan_id'         => $subscription->plan_id,
        ];

        if ($type === 'discount') {
            if ($moduleType && !in_array($moduleType, ProCustomerBenefitSetting::DISCOUNT_MODULE_TYPES, true)) {
                return $fail('no_benefit_for_module', planDetails: $planDetails);
            }
            $setupMode = $rows['discount_setup_mode'] ?? 'central';
            $lookupKey = ($setupMode === 'individual' && $moduleType) ? $moduleType : null;
            $cfg       = app(ProCustomerBenefitSettingService::class)->getSettings('discount', $lookupKey);

            $benefit += [
                'percentage'       => isset($cfg['percentage']) && $cfg['percentage'] !== null ? (float) $cfg['percentage'] : null,
                'max_amount'       => isset($cfg['max_amount']) && $cfg['max_amount'] !== null ? (float) $cfg['max_amount'] : null,
                'min_order_status' => (int) ($cfg['min_order_status'] ?? 0),
                'min_order_amount' => isset($cfg['min_order_amount']) && $cfg['min_order_amount'] !== null ? (float) $cfg['min_order_amount'] : null,
            ];
        } elseif ($type === 'delivery_fee') {
            if ($moduleType && !in_array($moduleType, ProCustomerBenefitSetting::DELIVERY_FEE_MODULE_TYPES, true)) {
                return $fail('no_benefit_for_module', planDetails: $planDetails);
            }
            $cfg = $moduleType ? app(ProCustomerBenefitSettingService::class)->getSettings('delivery_fee', $moduleType) : [];

            $benefit += [
                'offer_type'                 => $cfg['offer_type'] ?? 'full_free',
                'min_order_status'           => (int) ($cfg['min_order_status'] ?? 0),
                'min_order_amount'           => isset($cfg['min_order_amount']) && $cfg['min_order_amount'] !== null ? (float) $cfg['min_order_amount'] : null,
                'charge_discount_percentage' => isset($cfg['charge_discount']) && $cfg['charge_discount'] !== null ? (float) $cfg['charge_discount'] : null,
            ];
        } elseif ($type === 'coupon') {
            if($moduleType && $moduleType == 'parcel') {
                return $fail('no_benefit_for_module', planDetails: $planDetails);
            }
        }

        if ($incrementCount) {
            $latestTransaction?->increment('order_count');
        }

        $isActive = $subscription->status === 'active'
            && ($subscription->end_at === null
                || $subscription->end_at->copy()->startOfDay()->gte(now()->startOfDay()));

        return [
            'status'       => $isActive,
            'message'      => $subscription->status,
            'benefit'      => $benefit,
            'plan_details' => $planDetails,
        ];
    }
    public function applyProCustomerDiscount(?int $userId, float $subtotal, float $totalPrice, ?string $moduleType = null): array
    {
        $noop = fn(string $message) => [
            'offer'       => ['status' => false, 'message' => $message, 'benefit' => null, 'plan_details' => null],
            'discount'    => 0.0,
            'total_price' => (float) $totalPrice,
        ];

        if ($moduleType !== null) {
            if (!in_array($moduleType, ProCustomerBenefitSetting::DISCOUNT_MODULE_TYPES, true)) {
                return $noop('no_benefit_for_module');
            }
            if ($moduleType === 'ride-share' && !addon_published_status('RideShare')) {
                return $noop('module_addon_unpublished');
            }
            if ($moduleType === 'rental' && !addon_published_status('Rental')) {
                return $noop('module_addon_unpublished');
            }
            if ($moduleType === 'service' && !addon_published_status('Service')) {
                return $noop('module_addon_unpublished');
            }
        }

        $offer    = $this->getProCustomerOffer($userId, false, true, $moduleType);
        $discount = $this->computeProDiscountAmount($offer, $subtotal, $totalPrice);

        return [
            'offer'       => $offer,
            'discount'    => (float) $discount,
            'total_price' => (float) max($totalPrice - $discount, 0),
        ];
    }
    public function computeProDiscountAmount(array $proOffer, float $subtotal, float $totalPrice): float
    {
        if (!($proOffer['status'] ?? false) || ($proOffer['benefit']['type'] ?? null) !== 'discount') {
            return 0.0;
        }

        $cfg   = $proOffer['benefit'];
        $minOk = $cfg['min_order_status'] !== 1
            || ($cfg['min_order_amount'] !== null && $subtotal >= $cfg['min_order_amount']);

        if (!$minOk || empty($cfg['percentage'])) {
            return 0.0;
        }

        $discount = $totalPrice * ((float) $cfg['percentage'] / 100);
        if ($cfg['max_amount'] !== null) {
            $discount = min($discount, (float) $cfg['max_amount']);
        }

        return (float) $discount;
    }
    public function applyProCustomerDeliveryFee(
        array $proOffer,
        float $deliveryCharge,
        float $totalPrice,
        ?string $freeDeliveryBy = null,
        ?string $moduleType = null,
    ): array {
        $passthrough = [
            'delivery_charge'  => $deliveryCharge,
            'free_delivery_by' => $freeDeliveryBy,
            'savings'          => 0.0,
        ];

        if ($moduleType !== null && !in_array($moduleType, ProCustomerBenefitSetting::DELIVERY_FEE_MODULE_TYPES, true)) {
            return $passthrough;
        }

        $applies = ($proOffer['status'] ?? false)
            && ($proOffer['benefit']['type'] ?? null) === 'delivery_fee'
            && $deliveryCharge > 0;

        if (!$applies) {
            return $passthrough;
        }

        $cfg     = $proOffer['benefit'];
        $savings = 0.0;

        if ($cfg['offer_type'] === 'full_free') {
            $minOk = $cfg['min_order_status'] !== 1
                || ($cfg['min_order_amount'] !== null && $totalPrice >= $cfg['min_order_amount']);
            if ($minOk) {
                $savings        = $deliveryCharge;
                $deliveryCharge = 0.0;
                $freeDeliveryBy = 'admin';
            }
        } elseif ($cfg['offer_type'] === 'partial_free' && $cfg['charge_discount_percentage']) {
            $reduction      = $deliveryCharge * ((float) $cfg['charge_discount_percentage'] / 100);
            $savings        = (float) $reduction;
            $deliveryCharge = max(0, $deliveryCharge - $reduction);
            $freeDeliveryBy = 'admin';
        }

        return [
            'delivery_charge'  => (float) $deliveryCharge,
            'free_delivery_by' => $freeDeliveryBy,
            'savings'          => $savings,
        ];
    }
    public function recordOrderProDiscount(
        int $userId,
        array $proOffer,
        float $amountSaved,
        ?int $orderId = null,
        ?int $tripId = null,
        ?int $rideRequestId = null,
        ?int $serviceBookingId = null,
        ?float $originalDeliveryCharge = null,
        ?string $moduleType = null,
    ): ?OrderProDiscount {
        if (!($proOffer['status'] ?? false)) {
            return null;
        }

        if ($orderId === null && $tripId === null && $rideRequestId === null && $serviceBookingId === null) {
            return null;
        }

        $benefit = $proOffer['benefit'];
        $type    = $benefit['type'];

        if ($amountSaved <= 0 && $type !== 'coupon') {
            return null;
        }

        if ($moduleType !== null) {
            $allowList = match ($type) {
                'discount'     => ProCustomerBenefitSetting::DISCOUNT_MODULE_TYPES,
                'delivery_fee' => ProCustomerBenefitSetting::DELIVERY_FEE_MODULE_TYPES,
                default        => null,
            };
            if ($allowList !== null && !in_array($moduleType, $allowList, true)) {
                return null;
            }
        }

        $transaction_id = app(ProCustomerTransactionService::class)->findLatestIdForSubscription($userId, $benefit['subscription_id']);

        return app(OrderProDiscountService::class)->create([
            'order_id'                            => $orderId,
            'trip_id'                             => $tripId,
            'ride_request_id'                     => $rideRequestId,
            'service_booking_id'                  => $serviceBookingId,
            'user_id'                             => $userId,
            'subscription_id'                     => $benefit['subscription_id'] ?? null,
            'plan_id'                             => $benefit['plan_id'] ?? null,
            'benefit_type'                        => $type,
            'transaction_id'                      => $transaction_id,
            'amount_saved'                        => $type === 'discount' ? $amountSaved : 0,
            'discount_percentage'                 => $type === 'discount' ? ($benefit['percentage'] ?? null) : null,
            'max_discount_amount'                 => $type === 'discount' ? ($benefit['max_amount'] ?? null) : null,
            'min_order_amount'                    => in_array($type, ['discount', 'delivery_fee'], true)
                ? ($benefit['min_order_amount'] ?? null)
                : null,
            'delivery_offer_type'                 => $type === 'delivery_fee' ? ($benefit['offer_type'] ?? null) : null,
            'delivery_charge_discount_percentage' => $type === 'delivery_fee' ? ($benefit['charge_discount_percentage'] ?? null) : null,
            'delivery_fee_reduction_amount'       => $type === 'delivery_fee' ? $amountSaved : null,
            'original_delivery_charge'            => $type === 'delivery_fee' ? $originalDeliveryCharge : null,
        ]);
    }
    public function recomputeOrderProDiscountOnEdit(
        \App\Models\Order $order,
        float $subtotal,
        float $totalPrice,
        ?string $moduleType = null,
        ?float $deliveryCharge = null,
    ): array {
        $userId         = $order->user_id ? (int) $order->user_id : null;
        $currentDelivery = $deliveryCharge !== null
            ? (float) $deliveryCharge
            : (float) ($order->delivery_charge ?? 0);
        $existingFreeBy = $order->free_delivery_by ?? null;

        $passthrough = [
            'discount'         => 0.0,
            'total_price'      => (float) $totalPrice,
            'delivery_charge'  => $currentDelivery,
            'delivery_savings' => 0.0,
            'free_delivery_by' => $existingFreeBy,
            'pro_offer'        => ['status' => false, 'benefit' => null],
        ];

        app(OrderProDiscountService::class)->deleteForOrder($order->id);

        if (!$userId) {
            return $passthrough;
        }

        $proApply = $this->applyProCustomerDiscount(
            $userId,
            $subtotal,
            $totalPrice,
            $moduleType,
        );

        $proOffer    = $proApply['offer'];
        $proDiscount = (float) $proApply['discount'];
        $newTotal    = (float) $proApply['total_price'];

        $deliverySavings = 0.0;
        $newDelivery     = $currentDelivery;
        $newFreeBy       = $existingFreeBy;
        if ($deliveryCharge !== null) {
            $proDelivery = $this->applyProCustomerDeliveryFee(
                $proOffer,
                $currentDelivery,
                $newTotal,
                $existingFreeBy,
                $moduleType,
            );
            $deliverySavings = (float) $proDelivery['savings'];
            $newDelivery     = (float) $proDelivery['delivery_charge'];
            $newFreeBy       = $proDelivery['free_delivery_by'];
        }

        if (($proOffer['status'] ?? false)) {
            if ($proDiscount > 0) {
                $this->recordOrderProDiscount(
                    orderId: (int) $order->id,
                    userId: $userId,
                    proOffer: $proOffer,
                    amountSaved: $proDiscount,
                    originalDeliveryCharge: $order->original_delivery_charge ?? null,
                    moduleType: $moduleType,
                );
            } elseif ($deliverySavings > 0) {
                $this->recordOrderProDiscount(
                    orderId: (int) $order->id,
                    userId: $userId,
                    proOffer: $proOffer,
                    amountSaved: $deliverySavings,
                    originalDeliveryCharge: $order->original_delivery_charge ?? $currentDelivery,
                    moduleType: $moduleType,
                );
            }
        }

        return [
            'discount'         => $proDiscount,
            'total_price'      => $newTotal,
            'delivery_charge'  => $newDelivery,
            'delivery_savings' => $deliverySavings,
            'free_delivery_by' => $newFreeBy,
            'pro_offer'        => $proOffer,
        ];
    }
    public function applyProCustomerPlan(User $user, ProCustomerSubscriptionPlan $plan, array $payment = [], string $mode = 'start'): ProCustomerSubscription
    {
        $rawPlanName = app(ProCustomerSubscriptionPlanService::class)->findUntranslatedName($plan->id);

        $isFreeTrial        = $plan->plan_type === 'free_trial';
        $duration           = (int) $plan->duration;
        $price              = (float) $plan->price;
        $paymentMethodInput = $payment['payment_method'] ?? null;

        if ($isFreeTrial && $this->hasUsedFreeTrial($user->id)) {
            throw new RuntimeException('free_trial_already_used');
        }

        if (!$isFreeTrial && $paymentMethodInput === 'wallet'
            && (float) $user->wallet_balance < $price) {
            throw new RuntimeException('insufficient_wallet_balance');
        }

        $subscription = DB::transaction(function () use ($user, $plan, $rawPlanName, $isFreeTrial, $duration, $price, $paymentMethodInput, $payment, $mode) {
            $subscription = app(ProCustomerSubscriptionService::class)->findLatestOrNewForUser($user->id);

            $isNew = !$subscription->exists;
            $now   = now();

            $extending = $mode === 'renew'
                && !$isNew
                && $subscription->status !== 'canceled'
                && $subscription->end_at
                && $subscription->end_at->copy()->startOfDay()->gte($now->copy()->startOfDay());

            if ($extending) {
                $startAt = $subscription->start_at ?? $now;
                $endAt   = $subscription->end_at->copy()->addDays($duration);
            } else {
                $startAt = $now;
                $endAt   = (clone $now)->addDays($duration);
            }

            $subscription->fill([
                'user_id'    => $user->id,
                'plan_id'    => $plan->id,
                'plan_name'  => $rawPlanName,
                'plan_type'  => $plan->plan_type,
                'plan_price' => $isFreeTrial ? 0 : $price,
                'start_at'   => $startAt,
                'end_at'     => $endAt,
                'status'     => 'active',
            ]);
            if ($isNew) {
                $subscription->auto_renew = 0;
            }
            $subscription->save();

            $paymentMethod = $payment['payment_method'] ?? ($isFreeTrial ? 'free_trial' : null);
            $paymentStatus = $payment['payment_status'] ?? (($isFreeTrial || $paymentMethod) ? 'success' : 'pending');
            $paidAt        = $payment['paid_at'] ?? ($paymentStatus === 'success' ? $now : null);

            $walletTxnUuid = (!$isFreeTrial && $paymentMethodInput === 'wallet' && $price > 0)
                ? (string) Str::uuid()
                : null;

            $transaction = app(ProCustomerTransactionService::class)->create([
                'user_id'               => $user->id,
                'subscription_id'       => $subscription->id,
                'plan_id'               => $plan->id,
                'transaction_reference' => $payment['transaction_reference'] ?? $walletTxnUuid,
                'plan_name'             => $rawPlanName,
                'plan_type'             => $plan->plan_type,
                'plan_price'            => $isFreeTrial ? 0 : $price,
                'amount'                => $isFreeTrial ? 0 : $price,
                'payment_method'        => $paymentMethod,
                'payment_status'        => $paymentStatus,
                'start_at'              => $startAt,
                'end_at'                => $endAt,
                'order_count'           => 0,
                'paid_at'               => $paidAt,
            ]);

            if ($walletTxnUuid !== null) {
                $newBalance = (float) $user->wallet_balance - $price;

                $walletTxn                   = new WalletTransaction();
                $walletTxn->user_id          = $user->id;
                $walletTxn->transaction_id   = $walletTxnUuid;
                $walletTxn->reference        = 'pro_subscription_' . $transaction->id;
                $walletTxn->transaction_type = 'pro_subscription';
                $walletTxn->debit            = $price;
                $walletTxn->credit           = 0;
                $walletTxn->admin_bonus      = 0;
                $walletTxn->balance          = $newBalance;
                $walletTxn->created_at       = $now;
                $walletTxn->updated_at       = $now;
                $walletTxn->save();

                $user->wallet_balance = $newBalance;
            }

            $user->pro_status = 1;
            $user->save();

            return $subscription;
        });

        $this->sendUserPushNotification(
            user: $user,
            messageKey: 'subscription_activated',
            title: $mode === 'renew'
                ? translate('messages.Subscription Renewed')
                : translate('Subscription activated'),
            type: 'customer_subscription_activated',
            dataId: $subscription->id,
        );

        return $subscription;
    }
    public function proActiveModuleTypes(): array
    {
        return app(ModuleService::class)->getActiveTypes();
    }
    public function proAddonEnabledDiscountModules(): array
    {
        return array_values(array_filter(
            ProCustomerBenefitSetting::DISCOUNT_MODULE_TYPES,
            fn ($mod) => match ($mod) {
                'ride-share' => (bool) addon_published_status('RideShare'),
                'rental'     => (bool) addon_published_status('Rental'),
                'service'    => (bool) addon_published_status('Service'),
                default      => true,
            }
        ));
    }
    public function proVisibleDiscountModules(): array
    {
        $active = $this->proActiveModuleTypes();

        return array_values(array_filter(
            $this->proAddonEnabledDiscountModules(),
            fn ($mod) => in_array($mod, $active, true)
        ));
    }
    public function proVisibleDeliveryFeeModules(): array
    {
        $active = $this->proActiveModuleTypes();

        return array_values(array_filter(
            ProCustomerBenefitSetting::DELIVERY_FEE_MODULE_TYPES,
            fn ($mod) => in_array($mod, $active, true)
        ));
    }
    public function expireDueSubscriptions(?int $limit = null, ?float $timeBudget = null): int
    {
        static $running = false;
        if ($running) return 0;
        $running = true;

        try {
            return $this->doExpireDueSubscriptions($limit, $timeBudget);
        } finally {
            $running = false;
        }
    }
    public function cancelProCustomerSubscription(ProCustomerSubscription $subscription): void
    {
        $user = $subscription->user ?? app(UserService::class)->find($subscription->user_id);

        DB::transaction(function () use ($subscription, $user) {
            $subscription->status     = 'canceled';
            $subscription->auto_renew = 0;
            $subscription->save();

            if ($user) {
                $user->pro_status = 0;
                $user->save();
            }
        });

        if ($user) {
            $this->sendUserPushNotification(
                user: $user,
                messageKey: 'subscription_canceled',
                title: translate('Subscription canceled'),
                type: 'subscription_canceled',
                dataId: $subscription->id,
            );
        }
    }
    public function sendCustomerSubscriptionExpireNotification(): void
    {
        $beforeTime = (int) (app(DataSettingService::class)->findValueByKeyAndType('subscription_reminder_before_time', 'notification_settings') ?? 0);
        $beforeUnit = app(DataSettingService::class)->findValueByKeyAndType('subscription_reminder_before', 'notification_settings') ?? 'days';

        if ($beforeTime <= 0) return;

        $isEnabled = app(NotificationMessageService::class)->isEnabled('subscription_expire_reminder');
        if (!$isEnabled) return;

        $target = match ($beforeUnit) {
            'hour'  => now()->addHours($beforeTime),
            'min'   => now()->addMinutes($beforeTime),
            default => now()->addDays($beforeTime),
        };

        [$windowStart, $windowEnd] = $beforeUnit === 'hour'
            ? [$target->copy()->startOfHour(), $target->copy()->endOfHour()]
            : [$target->copy()->startOfDay(), $target->copy()->endOfDay()];

        $subscriptions = app(ProCustomerSubscriptionService::class)->getExpiringForReminder($windowStart, $windowEnd);

        foreach ($subscriptions as $subscription) {
            if (!$subscription->user) continue;
            $this->sendUserPushNotification(
                user: $subscription->user,
                messageKey: 'subscription_expire_reminder',
                title: translate('Subscription expire reminder'),
                type: 'customer_subscription_expire_reminder',
                dataId: $subscription->id,
            );
        }
    }
    private function hasUsedFreeTrial(int $userId): bool
    {
        return app(ProCustomerTransactionService::class)->usedFreeTrial($userId);
    }
    private function doExpireDueSubscriptions(?int $limit, ?float $timeBudget): int
    {
        $now         = now();
        $startedAt   = microtime(true);
        $processed   = 0;
        $lastCursor  = null;
        $outOfBudget = false;

        $budgetSpent = fn (): bool => $timeBudget !== null && (microtime(true) - $startedAt) >= $timeBudget;

        while (! $outOfBudget && ($limit === null || $processed < $limit) && ! $budgetSpent()) {
            $take = $limit === null
                ? self::EXPIRY_SWEEP_CHUNK_SIZE
                : min(self::EXPIRY_SWEEP_CHUNK_SIZE, $limit - $processed);

            $batch = $this->dueSubscriptionQuery($now)
                ->select(['id', 'user_id'])
                ->with(['user' => fn ($query) => $query->withoutGlobalScopes()
                    ->select(['id', 'f_name', 'l_name', 'cm_firebase_token', 'current_language_key'])])
                ->orderBy('id')
                ->limit($take)
                ->get();

            if ($batch->isEmpty()) {
                break;
            }

            $cursor = $batch->first()->id;
            if ($cursor === $lastCursor) {
                break;
            }
            $lastCursor = $cursor;

            $subscriptionIds = $batch->pluck('id')->all();
            $userIds         = $batch->pluck('user_id')->filter()->unique()->values()->all();

            DB::transaction(function () use ($subscriptionIds, $userIds) {
                app(ProCustomerSubscriptionService::class)->markExpired($subscriptionIds);

                if ($userIds !== []) {
                    app(UserService::class)->clearProStatus($userIds);
                }
            });

            $processed += $batch->count();

            foreach ($batch as $subscription) {
                if ($budgetSpent()) {
                    $outOfBudget = true;
                    break;
                }

                if ($subscription->user) {
                    $this->sendUserPushNotification(
                        user: $subscription->user,
                        messageKey: 'subscription_expired',
                        title: translate('Subscription expire message'),
                        type: 'subscription_expired',
                        dataId: $subscription->id,
                    );
                }
            }
        }

        if (! $this->dueSubscriptionQuery($now)->exists()) {
            $this->stampExpirySweepRun($now);
        }

        return $processed;
    }
    private function dueSubscriptionQuery(Carbon $now): Builder
    {
        return app(ProCustomerSubscriptionService::class)->expiredQuery($now);
    }
    private function stampExpirySweepRun(Carbon $now): void
    {
        $lastRunKeys  = ['key' => 'subscription_expiry_last_run_at', 'type' => 'notification_settings'];
        $lastRunQuery = DB::table('data_settings')->where($lastRunKeys);

        if ($lastRunQuery->exists()) {
            $lastRunQuery->update(['value' => $now->toDateTimeString(), 'updated_at' => $now]);
        } else {
            DB::table('data_settings')->insert($lastRunKeys + [
                'value'      => $now->toDateTimeString(),
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }
    private function sendUserPushNotification(
        User $user,
        string $messageKey,
        string $title,
        string $type,
        int|string|null $dataId = null,
    ): bool {
        if (!$user->cm_firebase_token || $user->cm_firebase_token === '@') {
            return false;
        }

        static $notificationMessageMemo = [];

        $locale  = $user->current_language_key ?: 'en';
        $memoKey = $messageKey.'|'.$locale;

        if (!array_key_exists($memoKey, $notificationMessageMemo)) {
            $notificationMessageMemo[$memoKey] = app(NotificationMessageService::class)->findEnabledWithTranslations($messageKey, $locale);
        }

        $notificationMessage = $notificationMessageMemo[$memoKey];

        if (!$notificationMessage) return false;

        $description = NotificationText::format(
            value: $notificationMessage->message,
            user_name: trim($user->f_name . ' ' . $user->l_name),
        );

        $data = NotificationMessages::proCustomerSubscription($title, $description, $type, $dataId);

        try {
            SendNotification::sendToDevice($user->cm_firebase_token, $data);
            app(UserNotificationService::class)->record($user->id, $data);
            return true;
        } catch (\Exception $e) {
            Log::error('payment.pro_customer_subscription_trait.send_user_push_notification_failed', [
                'error' => $e->getMessage(),
                'file' => $e->getFile().':'.$e->getLine(),
            ]);
            return false;
        }
    }
}
