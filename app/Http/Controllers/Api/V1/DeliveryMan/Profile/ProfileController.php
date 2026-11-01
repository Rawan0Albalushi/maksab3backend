<?php

namespace App\Http\Controllers\Api\V1\DeliveryMan\Profile;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Requests\DeliveryMan\Profile\FcmTokenRequest;
use App\Http\Requests\DeliveryMan\Profile\ProfileUpdateRequest;
use App\Http\Resources\DeliveryMan\Profile\ProfileResource;
use App\Services\DeliveryMan\DeliveryManService;
use App\Traits\Api\ApiRequestContextTrait;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\RideShare\Http\Resources\UserManagement\DriverLevelResource;
use Modules\RideShare\Http\Resources\UserManagement\TimeTrackResource;
use Modules\RideShare\Interface\UserManagement\Service\TimeTrackServiceInterface;
use Modules\RideShare\Services\RiderDetailService;
use Modules\RideShare\Services\RiderVehicleService;

class ProfileController extends BaseApiController
{
    use ApiRequestContextTrait;

    public function __construct(protected DeliveryManService $deliveryManService) {}

    public function show(Request $request): JsonResponse
    {
        $deliveryMan = $this->deliveryManService->loadProfileRelations($this->deliveryMan());
        $metrics = $this->deliveryManService->profileMetrics($deliveryMan);

        return $this->responseFormatter(
            config('response.default_200'),
            ProfileResource::withMetrics($deliveryMan, $metrics, $this->rideShareProfileExtras($deliveryMan, $metrics))->toArray($request)
        );
    }


    public function update(ProfileUpdateRequest $request): JsonResponse
    {
        $this->deliveryManService->updateProfile($this->deliveryMan(), $request->payload());

        return $this->responseFormatter(['message' => translate('Updated successfully')] + config('response.default_update_200'));
    }

    public function updateActiveStatus(): JsonResponse
    {
        $deliveryMan = $this->deliveryMan();
        $this->deliveryManService->toggleActive($deliveryMan);
        $this->syncRideShareAvailability($deliveryMan);

        return $this->responseFormatter(['message' => translate('messages.Active status updated')] + config('response.default_update_200'));
    }


    public function updateFcmToken(FcmTokenRequest $request): JsonResponse
    {
        $this->deliveryManService->updateFcmToken($this->deliveryManId(), $request->input('fcm_token'));

        return $this->responseFormatter(['message' => translate('Updated successfully')] + config('response.default_update_200'));
    }

    public function destroy(): JsonResponse
    {
        $deliveryMan = $this->deliveryMan();

        if ($this->deliveryManService->ongoingOrderCount($deliveryMan->id)) {
            return $this->errorResponse(config('response.forbidden_403'), translate('messages.Please complete your ongoing and accepted orders'), 'on-going');
        }

        if ($deliveryMan->wallet && $deliveryMan->wallet->collected_cash > 0) {
            return $this->errorResponse(config('response.forbidden_403'), translate('messages.You have cash in hand, you have to pay the due to delete your account.'), 'on-going');
        }

        $this->deliveryManService->removeAccount($deliveryMan);

        return $this->responseFormatter(config('response.default_delete_200'));
    }

    private function rideShareProfileExtras(mixed $deliveryMan, array $metrics): array
    {
        if (! addon_published_status('RideShare') || $deliveryMan->is_ride != 1) {
            return [];
        }

        return array_merge($this->deliveryManService->rideShareMetrics($deliveryMan, $metrics), [
            'rider_vehicle' => app(RiderVehicleService::class)->findForRider($deliveryMan->id),
            'rider_level' => $deliveryMan->level ? DriverLevelResource::make($deliveryMan->level) : null,
            'time_track' => $deliveryMan->latestTrack ? TimeTrackResource::make($deliveryMan->latestTrack) : null,
        ]);
    }

    private function syncRideShareAvailability(mixed $deliveryMan): void
    {
        if (! addon_published_status('RideShare') || $deliveryMan->is_ride != 1) {
            return;
        }

        $details = app(RiderDetailService::class)->syncAvailability(
            $deliveryMan->id,
            $deliveryMan->driverDetails,
            (bool) $deliveryMan->active
        );

        $track = app(TimeTrackServiceInterface::class)->findOneBy(
            criteria: ['user_id' => $deliveryMan->id, 'date' => date('Y-m-d')],
            relations: ['latestLog'],
            orderBy: ['created_at' => 'desc']
        );

        if (! $track) {
            $track = app(TimeTrackServiceInterface::class)->create(['user_id' => $deliveryMan->id, 'date' => now()]);
            $track->logs()->create(['online_at' => now()]);
        }

        if (! $details['is_online']) {
            $track->latestLog()->update(['offline_at' => now()]);
            $track->total_online += Carbon::parse($track?->latestLog?->online_at)->diffInMinutes(now());
            $track->save();

            return;
        }

        $track->total_offline += Carbon::parse($track->latestLog?->offline_at)->diffInMinutes(now());
        $track->save();
        $track->latestLog()->create(['online_at' => now()]);
    }
}
