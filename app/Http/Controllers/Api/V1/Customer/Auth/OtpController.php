<?php

namespace App\Http\Controllers\Api\V1\Customer\Auth;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Requests\Customer\Auth\FirebaseVerifyRequest;
use App\Http\Requests\Customer\Auth\OtpVerifyRequest;
use App\Http\Resources\Customer\Auth\AuthSessionResource;
use App\Services\Customer\UserService;
use App\Services\Order\CartService;
use App\Traits\Customer\CustomerSessionTrait;
use Illuminate\Http\JsonResponse;

class OtpController extends BaseApiController
{
    use CustomerSessionTrait;

    public function __construct(
        private readonly UserService $userService,
        private readonly CartService $cart
    ) {
    }

    public function verify(OtpVerifyRequest $request): JsonResponse
    {
        $data = $request->payload();
        $user = $this->resolveUser($data);

        if ($user && $data['login_type'] == 'manual') {
            return $this->verifyManual($user, $data);
        }

        if ($data['login_type'] == 'otp') {
            if (! $this->userService->findVerification('phone', $data['phone'], $data['otp'])) {
                return $this->otpMismatch();
            }

            return $this->completeOtpLogin($user, $data);
        }

        return $this->responseFormatter(config('response.default_404'));
    }

    public function firebaseVerify(FirebaseVerifyRequest $request): JsonResponse
    {
        $data = $request->payload();
        $verification = $this->userService->verifyFirebaseOtp($data['session_info'], $data['phone'], $data['otp']);

        if (! $verification['is_success']) {
            return $this->responseFormatter(config('response.forbidden_403'), errors: [
                ['code' => 'firebase', 'message' => $verification['message']],
            ]);
        }

        $user = $this->userService->findByPhone($data['phone']);

        if ($user && $data['login_type'] == 'manual') {
            if ($user->is_phone_verified) {
                return $this->alreadyVerified('phone');
            }

            $this->userService->markVerified($user, 'phone');

            return $this->session($this->sessionPayload($user, $data['login_type'], $data['guest_id']));
        }

        if ($data['login_type'] == 'otp') {
            $this->userService->storeFirebaseOtp($data['phone'], $data['otp']);

            return $this->completeOtpLogin($user, $data);
        }

        return $this->responseFormatter(config('response.default_404'));
    }

    protected function cartService(): CartService
    {
        return $this->cart;
    }

    private function resolveUser(array $data): mixed
    {
        if ($data['email']) {
            return $this->userService->findByEmail($data['email']);
        }

        return $this->userService->findByPhone($data['phone']);
    }

    private function verifyManual(mixed $user, array $data): JsonResponse
    {
        if ($data['verification_type'] == 'phone' && $user->is_phone_verified) {
            return $this->alreadyVerified('phone');
        }

        if ($data['verification_type'] == 'email' && $user->is_email_verified) {
            return $this->alreadyVerified('email');
        }

        $identifier = $data['verification_type'] == 'email' ? $data['email'] : $data['phone'];

        if (getEnvMode() == 'test') {
            if ($data['otp'] != UserService::TEST_OTP) {
                return $this->otpMismatch();
            }

            $this->userService->markVerified($user, $data['verification_type']);

            return $this->session($this->sessionPayload($user, $data['login_type'], $data['guest_id'], forceToken: true));
        }

        if ($this->userService->findVerification($data['verification_type'], $identifier, $data['otp'])) {
            $this->userService->consumeVerification($data['verification_type'], $identifier, $data['otp']);
            $this->userService->markVerified($user, $data['verification_type']);

            return $this->session($this->sessionPayload($user, $data['login_type'], $data['guest_id']));
        }

        if ($data['verification_type'] == 'phone') {
            $throttle = $this->userService->throttlePhoneOtp($data['phone']);

            if ($throttle) {
                return $this->responseFormatter(config('response.method_not_allowed_405'), errors: [$throttle]);
            }
        }

        return $this->otpMismatch();
    }

    private function completeOtpLogin(mixed $user, array $data): JsonResponse
    {
        if ($user && $user->is_phone_verified == 0 && $user->is_from_pos == 0) {
            return $this->session($this->pendingSessionPayload($user, 'otp', $this->userService->existingUserSummary($user)));
        }

        if ($user) {
            $this->userService->consumeVerification('phone', $data['phone'], $data['otp']);

            return $this->session($this->sessionPayload($user, $data['login_type'], $data['guest_id']));
        }

        $created = $this->userService->createOtpUser($data['phone']);

        return $this->session($this->pendingSessionPayload($created, 'otp', null, 0));
    }

    private function alreadyVerified(string $verificationType): JsonResponse
    {
        return $this->responseFormatter(config('response.default_200'), [
            'message' => $verificationType == 'phone'
                ? translate('messages.Phone number is already verified')
                : translate('messages.Email is already verified'),
        ]);
    }

    private function otpMismatch(): JsonResponse
    {
        return $this->responseFormatter(config('response.default_404'), errors: [
            ['code' => 'otp', 'message' => translate('OTP does not match')],
        ]);
    }

    private function session(array $payload): JsonResponse
    {
        return $this->responseFormatter(config('response.default_200'), new AuthSessionResource($payload));
    }
}
