<?php

namespace App\Http\Controllers\Api\V1\Customer\ProCustomer;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Requests\Customer\ProCustomer\SubscribeRequest;
use App\Http\Resources\Customer\ProCustomer\SubscriptionResource;
use App\Services\Payment\ProCustomerSubscriptionPlanService;
use App\Services\Payment\ProCustomerSubscriptionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SubscriptionController extends BaseApiController
{
    private const FREE_TRIAL = 'free_trial';

    private const ERROR_STATUS = [
        'insufficient_wallet_balance' => 'messages.wallet_balance_is_insufficient_for_this_plan',
        'free_trial_already_used' => 'messages.free_trial_already_used',
    ];

    public function __construct(
        private readonly ProCustomerSubscriptionService $subscriptionService,
        private readonly ProCustomerSubscriptionPlanService $planService
    ) {
    }

    public function activeOffer(Request $request): JsonResponse
    {
        return $this->responseFormatter(
            config('response.default_200'),
            $this->subscriptionService->getOffer($request->user()->id, $request->input('module_type'))
        );
    }

    public function store(SubscribeRequest $request): JsonResponse
    {
        if (! $this->subscriptionService->memberFeatureEnabled()) {
            return $this->responseFormatter(config('response.forbidden_403'), errors: [
                ['code' => 'pro_disabled', 'message' => translate('messages.Pro member feature is disabled')],
            ]);
        }

        $payload = $request->payload();
        $plan = $this->planService->findActive($payload['plan_id']);

        if (! $plan) {
            return $this->responseFormatter(config('response.default_404'), errors: [
                ['code' => 'plan_unavailable', 'message' => translate('messages.Plan not available')],
            ]);
        }

        $user = $request->user();
        $mode = $this->subscriptionService->subscriptionMode($user->id, $plan->id);

        if ($plan->plan_type === self::FREE_TRIAL) {
            return $this->activated($user, $plan, self::FREE_TRIAL, $mode);
        }

        if ($payload['payment_type'] === 'wallet') {
            return $this->activated($user, $plan, 'wallet', $mode);
        }

        if (! $this->subscriptionService->digitalPaymentEnabled()) {
            return $this->responseFormatter(config('response.forbidden_403'), errors: [
                ['code' => 'digital_payment_disabled', 'message' => translate('messages.Digital payment is disable')],
            ]);
        }

        $redirectLink = $this->subscriptionService->gatewayRedirectLink($user, $plan, $payload, $mode);

        return $redirectLink
            ? $this->responseFormatter(config('response.default_200'), ['redirect_link' => $redirectLink])
            : $this->responseFormatter(config('response.bad_request_400'), errors: [
                ['code' => 'invalid_gateway', 'message' => translate('messages.Payment gateway not supported')],
            ]);
    }

    public function cancel(Request $request): JsonResponse
    {
        $subscription = $this->subscriptionService->findActive($request->user()->id);

        if (! $subscription) {
            return $this->responseFormatter(config('response.default_404'), errors: [
                ['code' => 'no_active_subscription', 'message' => translate('messages.No active subscription to cancel')],
            ]);
        }

        $this->subscriptionService->cancel($subscription);

        return $this->responseFormatter(config('response.default_200'), [
            'message' => translate('Subscription canceled'),
        ]);
    }

    private function activated(mixed $user, mixed $plan, string $paymentMethod, string $mode): JsonResponse
    {
        $result = $this->subscriptionService->activate($user, $plan, $paymentMethod, $mode);

        if (isset($result['error'])) {
            return $this->responseFormatter(config('response.forbidden_403'), errors: [
                ['code' => $result['error'], 'message' => translate(self::ERROR_STATUS[$result['error']])],
            ]);
        }

        return $this->responseFormatter(config('response.default_200'), [
            'message' => translate('Subscription activated'),
            'subscription' => (new SubscriptionResource($result['subscription']))->resolve(),
        ]);
    }
}
