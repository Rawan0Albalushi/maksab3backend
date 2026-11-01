<?php

namespace App\Http\Controllers\Api\V1\Common\System;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Requests\Common\System\NewsletterSubscribeRequest;
use App\Services\System\DataSettingService;
use App\Services\Marketing\NewsletterService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class HomeController extends BaseApiController
{
    public function __construct(
        private readonly DataSettingService $dataSettingService,
        private readonly NewsletterService $newsletterService,
    ) {
    }

    public function termsAndConditions(Request $request): JsonResponse
    {
        return $this->policy($request, 'terms_and_conditions');
    }

    public function aboutUs(Request $request): JsonResponse
    {
        return $this->policy($request, 'about_us');
    }

    public function privacyPolicy(Request $request): JsonResponse
    {
        return $this->policy($request, 'privacy_policy');
    }

    public function refundPolicy(Request $request): JsonResponse
    {
        return $this->policy($request, 'refund_policy');
    }

    public function shippingPolicy(Request $request): JsonResponse
    {
        return $this->policy($request, 'shipping_policy');
    }

    public function cancellation(Request $request): JsonResponse
    {
        return $this->policy($request, 'cancellation_policy');
    }

    public function subscribeNewsletter(NewsletterSubscribeRequest $request): JsonResponse
    {
        $this->newsletterService->create($request->payload());

        return $this->responseFormatter(config('response.default_store_201'));
    }

    private function policy(Request $request, string $key): JsonResponse
    {
        return $this->responseFormatter(
            config('response.default_200'),
            $this->dataSettingService->localizedContent($key, $request->header('X-localization') ?? 'en'),
        );
    }
}
