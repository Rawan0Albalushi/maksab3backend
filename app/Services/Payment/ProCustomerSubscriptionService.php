<?php

namespace App\Services\Payment;

use App\Models\ProCustomerSubscription;
use App\Models\ProCustomerSubscriptionPlan;
use App\Models\User;
use App\Services\BaseService;
use App\Traits\Payment\PaymentRedirectLinkTrait;
use App\Traits\Payment\ProCustomerSubscriptionTrait;
use RuntimeException;
use App\Services\System\BusinessSettingService;

class ProCustomerSubscriptionService extends BaseService
{
    use PaymentRedirectLinkTrait;
    use ProCustomerSubscriptionTrait;
    private const SUCCESS_HOOK = 'pro_customer_subscription_success';
    private const FAILURE_HOOK = 'pro_customer_subscription_failed';
    private const RECEIVER_ID = '100';
    private const ATTRIBUTE = 'pro_customer_subscription_payment';
    private const RECOVERABLE_ERRORS = ['insufficient_wallet_balance', 'free_trial_already_used'];
    public function findForUser(mixed $userId): mixed
    {
        return ProCustomerSubscription::where('user_id', $userId)->first();
    }
    public function memberFeatureEnabled(): bool
    {
        return (int) app(BusinessSettingService::class)->value('pro_member_status') === 1;
    }
    public function digitalPaymentEnabled(): bool
    {
        return app(BusinessSettingService::class)->digitalPaymentEnabled();
    }
    public function brandName(): string
    {
        $businessName = app(BusinessSettingService::class)->value('business_name') ?: 'Mart';

        return trim($businessName) . ' ' . translate('messages.Pro');
    }
    public function freeTrialUsed(mixed $userId): bool
    {
        return $userId ? $this->hasUsedFreeTrial($userId) : false;
    }
    public function getOffer(mixed $userId, ?string $moduleType): array
    {
        return $this->getProCustomerOffer(
            userId: $userId,
            moduleType: $moduleType,
            showOnlyActivePlan: false
        );
    }
    public function findActive(mixed $userId): ?ProCustomerSubscription
    {
        return $this->latestForUserQuery($userId)->where('status', 'active')->first();
    }
    public function subscriptionMode(mixed $userId, mixed $planId): string
    {
        $current = $this->latestForUserQuery($userId)->first(['id', 'plan_id']);

        return match (true) {
            ! $current => 'start',
            (int) $current->plan_id === (int) $planId => 'renew',
            default => 'shift',
        };
    }
    public function activate(User $user, ProCustomerSubscriptionPlan $plan, string $paymentMethod, string $mode): array
    {
        try {
            $subscription = $this->applyProCustomerPlan($user, $plan, ['payment_method' => $paymentMethod], $mode);
        } catch (RuntimeException $exception) {
            if (! in_array($exception->getMessage(), self::RECOVERABLE_ERRORS, true)) {
                throw $exception;
            }

            return ['error' => $exception->getMessage()];
        }

        return ['subscription' => $subscription];
    }
    public function cancel(ProCustomerSubscription $subscription): void
    {
        $this->cancelProCustomerSubscription($subscription);
    }
    public function gatewayRedirectLink(User $user, ProCustomerSubscriptionPlan $plan, array $payment, string $mode): ?string
    {
        return $this->paymentRedirectLink($user, [
            'success_hook' => self::SUCCESS_HOOK,
            'failure_hook' => self::FAILURE_HOOK,
            'receiver_id' => self::RECEIVER_ID,
            'attribute' => self::ATTRIBUTE,
            'attribute_id' => (string) $user->id,
            'amount' => (float) $plan->price,
            'payment_method' => $payment['payment_method'] ?? null,
            'payment_platform' => $payment['payment_platform'] ?? null,
            'callback' => $payment['callback'] ?? null,
            'additional_data' => ['plan_id' => (int) $plan->id, 'mode' => $mode],
        ]);
    }
    public function findLatestForUser(mixed $userId, bool $activeOnly = false): ?ProCustomerSubscription
    {
        return $this->latestForUserQuery($userId)
            ->when($activeOnly, function ($query) {
                $query->where('status', 'active')
                    ->where(fn ($sub) => $sub->whereNull('end_at')->orWhereDate('end_at', '>=', now()));
            })
            ->first();
    }
    public function markExpired(array $subscriptionIds): void
    {
        ProCustomerSubscription::whereIn('id', $subscriptionIds)
            ->update(['status' => 'expired', 'auto_renew' => 0]);
    }
    public function expiredQuery(mixed $now): mixed
    {
        return ProCustomerSubscription::where('status', 'active')
            ->whereNotNull('end_at')
            ->whereDate('end_at', '<', $now);
    }
    public function getExpiringForReminder(mixed $windowStart, mixed $windowEnd): mixed
    {
        return ProCustomerSubscription::select(['id', 'user_id'])
            ->with(['user:id,f_name,l_name,cm_firebase_token,current_language_key'])
            ->where('status', 'active')
            ->whereBetween('end_at', [$windowStart, $windowEnd])
            ->whereHas('user', fn ($query) => $query
                ->whereNotNull('cm_firebase_token')
                ->where('cm_firebase_token', '!=', '@'))
            ->lazy();
    }
    public function findLatestOrNewForUser(mixed $userId): ProCustomerSubscription
    {
        return $this->findLatestForUser($userId) ?? new ProCustomerSubscription(['user_id' => $userId]);
    }

    private function latestForUserQuery(mixed $userId): mixed
    {
        return ProCustomerSubscription::where('user_id', $userId)->latest('id');
    }
}
