<?php

namespace App\Http\Controllers\Api\V1\Customer\Profile;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Requests\Customer\Profile\FirebaseTokenUpdateRequest;
use App\Http\Requests\Customer\Profile\InterestUpdateRequest;
use App\Http\Requests\Customer\Profile\ProfileUpdateRequest;
use App\Http\Resources\Customer\Profile\ProfileResource;
use App\Services\Customer\UserService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProfileController extends BaseApiController
{
    private const LOCALIZATION_HEADER = 'X-localization';

    public function __construct(
        private readonly UserService $userService
    ) {
    }

    public function show(Request $request): JsonResponse
    {
        if (! $request->hasHeader(self::LOCALIZATION_HEADER)) {
            return $this->responseFormatter(config('response.forbidden_403'), errors: [
                ['code' => 'current_language_key', 'message' => translate('messages.Current language key required')],
            ]);
        }

        $user = $request->user();
        $this->userService->rememberLanguage($user, $request->header(self::LOCALIZATION_HEADER));
        $user->loadMissing(['storage', 'userinfo']);

        return $this->responseFormatter(
            config('response.default_200'),
            new ProfileResource($user, $this->userService->profileExtras($user))
        );
    }

    public function update(ProfileUpdateRequest $request): JsonResponse
    {
        $payload = $request->payload();
        $user = $this->userService->findWithInfo($request->user()->id);

        $pending = $this->userService->pendingVerification($user, $payload);

        if ($pending) {
            return $this->responseFormatter($this->responseConfig($pending['code']), [
                'verification_on' => $pending['verification_on'],
                'verification_medium' => $pending['verification_medium'],
                'otp_send' => $pending['otp_send'],
                'message' => $pending['message'],
            ]);
        }

        $verification = $this->userService->verifyContact($user, $payload);

        if (! $verification['is_success']) {
            return $this->responseFormatter($this->responseConfig($verification['code']), [
                'verification_on' => $verification['verification_on'],
                'verification_medium' => $verification['verification_medium'],
                'message' => $verification['message'],
            ]);
        }

        $this->userService->update($user, $payload);

        return $this->responseFormatter(config('response.default_update_200'), [
            'message' => $verification['message'] ?? $this->updateMessage($payload),
        ]);
    }

    public function updateInterest(InterestUpdateRequest $request): JsonResponse
    {
        $this->userService->mergeInterest(
            $request->user(),
            $request->input('interest'),
            getModuleId($request->header('moduleId'))
        );

        return $this->responseFormatter(config('response.default_update_200'));
    }

    public function updateFirebaseToken(FirebaseTokenUpdateRequest $request): JsonResponse
    {
        $this->userService->updateFirebaseToken($request->user(), $request->input('cm_firebase_token'));

        return $this->responseFormatter(config('response.default_update_200'));
    }

    public function updateZone(Request $request): JsonResponse
    {
        $this->userService->syncZoneFromCoordinates(
            $request->user(),
            (float) $request->header('longitude'),
            (float) $request->header('latitude')
        );

        return $this->responseFormatter(config('response.default_update_200'));
    }

    public function destroy(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($this->userService->hasOngoingOrders($user->id)) {
            return $this->responseFormatter(config('response.forbidden_403'), errors: [
                ['code' => 'on-going', 'message' => translate('messages.Please complete your ongoing and accepted orders')],
            ]);
        }

        $this->userService->removeAccount($user);

        return $this->responseFormatter(config('response.default_delete_200'));
    }

    private function updateMessage(array $payload): string
    {
        return ($payload['button_type'] ?? null) == 'change_password'
            ? translate('Updated successfully')
            : translate('Updated successfully');
    }

    private function responseConfig(int $code): array
    {
        return $code === 200 ? config('response.default_200') : config('response.forbidden_403');
    }
}
