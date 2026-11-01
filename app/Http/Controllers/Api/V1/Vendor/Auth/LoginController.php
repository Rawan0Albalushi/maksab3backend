<?php

namespace App\Http\Controllers\Api\V1\Vendor\Auth;

use App\CentralLogics\Helpers;
use App\Http\Controllers\Api\BaseApiController;
use App\Http\Requests\Vendor\Auth\LoginRequest;
use App\Http\Requests\Vendor\Auth\StoreRegistrationRequest;
use App\Services\Store\StoreService;
use App\Services\Vendor\VendorService;
use Illuminate\Http\JsonResponse;
use App\Services\System\ModuleService;
use App\Services\Zone\ModuleZoneService;
use App\Services\Zone\ZoneService;

class LoginController extends BaseApiController
{
    public function __construct(
        protected VendorService $vendorService,
        protected StoreService $storeService,
        protected ModuleService $moduleService,
        protected ZoneService $zoneService
    ) {}

    public function login(LoginRequest $request): JsonResponse
    {
        $type = $request->input('vendor_type');

        $account = $this->vendorService->login(
            $request->only(['email', 'password']),
            (string) $type
        );

        if (! $account) {
            return $this->credentialsError();
        }

        $store = $type == 'owner' ? $account->stores->first() : $account->store;

        if (! $store) {
            return $this->errorResponse(config('response.forbidden_403'), translate('messages.No store is associated with this account'), 'auth-002');
        }

        $token = $this->vendorService->generateAuthToken($account);
        $subscription = $this->storeSubscriptionCheck($store, $account, $token);

        if (data_get($subscription, 'type') != null) {
            $config = $this->statusConfig(data_get($subscription, 'code'));

            if (data_get($subscription, 'type') == 'errors') {
                return $this->responseFormatter($config, errors: data_get($subscription, 'data.errors'));
            }

            $this->vendorService->saveAuthToken($account, $token);

            return $this->responseFormatter($config, data_get($subscription, 'data'));
        }

        $moduleType = $type == 'owner' ? $store->module?->module_type : $store->module_type;

        if ($moduleType == 'rental' && ! addon_published_status('Rental')) {
            return $this->errorResponse(config('response.unauthorized_401'), translate('Rental module is not available'), 'auth-001');
        }

        $this->vendorService->saveAuthToken($account, $token);

        $payload = ['token' => $token, 'zone_wise_topic' => $store->zone?->store_wise_topic];

        if ($type == 'employee') {
            $payload['role'] = Helpers::decodeJsonToArray($account->role?->modules);
        }

        $payload['module_type'] = $moduleType;

        return $this->responseFormatter(config('response.default_200'), $payload);
    }

    public function register(StoreRegistrationRequest $request): JsonResponse
    {
        $translations = $request->translationRows();

        if (! $this->zoneService->containsCoordinates($request->input('zone_id'), $request->input('latitude'), $request->input('longitude'))) {
            return $this->errorResponse(config('response.forbidden_403'), translate('messages.Coordinates out of zone'), 'latitude');
        }

        // D7's rule, same as Free Delivery / ETA / Delivery Rule setup: a module the zone does not
        // serve could never be connected, so registering a store for it is refused rather than
        // creating one that can never be reached.
        // `connectedModuleIds()` belongs to ModuleZoneService — it reads the module_zone pivot,
        // which is that service's model. ZoneService has no such method, so this line was a fatal
        // on every vendor registration that reached it. Resolved inline; the injected ZoneService
        // is still the right one for containsCoordinates() above.
        $connected = app(ModuleZoneService::class)->connectedModuleIds($request->input('zone_id'));

        if (! in_array((int) $request->input('module_id'), $connected, true)) {
            return $this->errorResponse(config('response.forbidden_403'), translate('messages.This zone is not connected to the selected module'), 'module_id');
        }

        $module = $this->moduleService->find($request->input('module_id'));

        if ($module?->module_type == 'rental' && addon_published_status('Rental') && empty($request->input('pickup_zone_id'))) {
            return $this->errorResponse(config('response.forbidden_403'), translate('messages.You must select a pickup zone'), 'pickup_zone_id');
        }

        [$vendor, $store, $type] = $this->vendorService->registerWithStore(
            $request->validated(),
            $request->only([
                'phone', 'email', 'latitude', 'longitude', 'zone_id', 'tin', 'tin_expire_date',
                'minimum_delivery_time', 'maximum_delivery_time', 'delivery_time_type',
                'module_id', 'pickup_zone_id',
            ]) + [
                'logo' => $request->file('logo'),
                'cover_photo' => $request->file('cover_photo'),
                'tin_certificate_image' => $request->file('tin_certificate_image'),
            ],
            $translations,
            $request->input('business_plan'),
            $request->input('package_id')
        );

        $this->vendorService->notifyRegistration($vendor, $module, $request->input('email'));

        return $this->responseFormatter(config('response.default_200'), array_filter([
            'store_id' => $store->id,
            'package_id' => $type == 'subscription' ? $store->package_id : null,
            'type' => $type,
            'message' => translate('messages.Application placed successfully'),
        ], fn ($value) => $value !== null));
    }

    private function credentialsError(): JsonResponse
    {
        return $this->errorResponse(
            config('response.unauthorized_401'),
            translate('Credentials do not match, please try again'),
            'auth-001'
        );
    }

    private function storeSubscriptionCheck($store, $vendor, $token): ?array
    {
        if ($store?->store_business_model == 'none') {
            return $this->subscribedPayload($store, $token, withPackage: true);
        }

        if ($store->status == 0 && $vendor->status == 0) {
            return $this->subscriptionError(translate('Your registration is not approved yet. You can login once admin approved the request.'));
        }

        if ($store->status == 0 && $vendor->status == 1 && in_array($store?->store_business_model, ['subscription', 'commission'])) {
            return $this->subscriptionError(translate('messages.Your account is suspended'));
        }

        if ($store?->store_business_model == 'subscription' && $store?->store_sub?->mobile_app === 0) {
            return [
                'type' => 'errors',
                'code' => 401,
                'data' => ['errors' => [['code' => 'no_mobile_app', 'message' => translate('Your subscription plan is not active for mobile app')]]],
            ];
        }

        if ($store?->store_business_model == 'unsubscribed' && ! isset($store?->store_sub_update_application)) {
            return $this->subscribedPayload($store, $token, withPackage: false);
        }

        return null;
    }

    private function subscribedPayload($store, $token, bool $withPackage): array
    {
        $subscribed = ['store_id' => $store?->id, 'token' => $token];

        if ($withPackage) {
            $subscribed['package_id'] = $store?->package_id;
        }

        $subscribed['zone_wise_topic'] = $store?->zone?->store_wise_topic;
        $subscribed['type'] = 'new_join';
        $subscribed['module_type'] = $store?->module?->module_type;

        return ['type' => 'subscribed', 'code' => 200, 'data' => ['subscribed' => $subscribed]];
    }

    private function subscriptionError(string $message): array
    {
        return [
            'type' => 'errors',
            'code' => 403,
            'data' => ['errors' => [['code' => 'auth-002', 'message' => $message]]],
        ];
    }
}
