<?php

namespace App\Http\Controllers\Api\V1\Vendor\Profile;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Requests\Vendor\Profile\AnnouncementRequest;
use App\Http\Requests\Vendor\Profile\FcmTokenRequest;
use App\Http\Requests\Vendor\Profile\ProfileUpdateRequest;
use App\Http\Resources\Vendor\Profile\ProfileResource;
use App\Services\Store\StoreService;
use App\Services\Vendor\VendorService;
use App\Traits\Api\ApiRequestContextTrait;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProfileController extends BaseApiController
{
    use ApiRequestContextTrait;

    public function __construct(
        private readonly VendorService $vendorService,
        private readonly StoreService $storeService
    ) {}

    public function show(Request $request): JsonResponse
    {
        $vendor = $request->input('vendor')->loadMissing('storage');
        $store = $this->vendorStore($request);
        $isServiceProvider = ($store->module_type ?? null) === 'service' && service_addon_active();
        $detailedStore = $this->storeService->findForVendorProfile($store->id);

        return $this->responseFormatter(config('response.default_200'), new ProfileResource($vendor, [
            'stats' => $this->vendorService->orderStats($vendor, $isServiceProvider, $store->id),
            'is_service_provider' => $isServiceProvider,
            'store' => $this->storeService->storePayload($store),
            'translations' => $detailedStore?->translations,
            'has_subscription_transactions' => $this->vendorService->hasSubscriptionTransactions($store->id),
            'subscription' => $detailedStore?->store_sub_update_application,
            'subscription_other_data' => $this->vendorService->subscriptionSummary($detailedStore),
            'out_of_stock_count' => $this->vendorService->outOfStockCount($detailedStore),
        ] + $this->employeeContext($request)));
    }

    /**
     * The token middleware puts the *owner* on the request for an employee token too, so these two
     * acted on the owner: an employee could reset the owner's password or delete the account.
     */
    private function ownerOnly(Request $request): ?JsonResponse
    {
        return $request->input('vendor_employee')
            ? $this->errorResponse(config('response.forbidden_403'), translate('messages.Access denied'), 'vendor_employee')
            : null;
    }

    public function update(ProfileUpdateRequest $request): JsonResponse
    {
        if ($denied = $this->ownerOnly($request)) {
            return $denied;
        }

        $this->vendorService->updateProfile($request->input('vendor'), $request->payload());

        return $this->responseFormatter(['message' => translate('Updated successfully')] + config('response.default_update_200'));
    }

    public function updateActiveStatus(Request $request): JsonResponse
    {
        $active = $this->storeService->toggleActive($this->vendorStore($request));

        return $this->responseFormatter([
            'message' => $active ? translate('messages.Store opened') : translate('messages.Store temporarily closed'),
        ] + config('response.default_update_200'));
    }

    public function updateAnnouncement(AnnouncementRequest $request): JsonResponse
    {
        $this->storeService->updateAnnouncement($this->vendorStore($request), $request->validated());

        return $this->responseFormatter(['message' => translate('Updated successfully')] + config('response.default_update_200'));
    }

    public function updateFcmToken(FcmTokenRequest $request): JsonResponse
    {
        if (! $request->hasHeader('vendorType')) {
            return $this->errorResponse(config('response.forbidden_403'), translate('messages.vendor_type_required'), 'vendor_type');
        }

        $this->vendorService->updateFirebaseToken([
            'vendor_type' => $request->header('vendorType'),
            'vendor_id' => $this->vendorId($request),
            'vendor_employee_id' => $request->input('vendor_employee')?->id,
        ], $request->input('fcm_token'));

        return $this->responseFormatter(['message' => 'Updated successfully'] + config('response.default_update_200'));
    }

    public function earnings(Request $request): JsonResponse
    {
        return $this->responseFormatter(
            config('response.default_200'),
            $this->storeService->earningSummary($this->vendorId($request))
        );
    }

    public function destroy(Request $request): JsonResponse
    {
        if ($denied = $this->ownerOnly($request)) {
            return $denied;
        }

        return $this->serviceResponse(
            $this->vendorService->deleteAccount($request->input('vendor')),
            config('response.default_delete_200')
        );
    }

    private function employeeContext(Request $request): array
    {
        $employee = $request->input('vendor_employee');

        if (! $employee) {
            return [];
        }

        return [
            'roles' => $employee->role ? json_decode($employee->role->modules) : [],
            'employee_info' => json_decode($employee),
        ];
    }
}
