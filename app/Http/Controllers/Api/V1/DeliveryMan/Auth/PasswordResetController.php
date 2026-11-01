<?php

namespace App\Http\Controllers\Api\V1\DeliveryMan\Auth;

use App\CentralLogics\Helpers;
use App\Http\Controllers\Api\BaseApiController;
use App\Http\Requests\DeliveryMan\Auth\FirebaseVerifyRequest;
use App\Http\Requests\DeliveryMan\Auth\ForgotPasswordRequest;
use App\Http\Requests\DeliveryMan\Auth\ResetPasswordRequest;
use App\Http\Requests\DeliveryMan\Auth\VerifyTokenRequest;
use App\Mail\DmPasswordResetMail;
use App\Services\Auth\PasswordResetService;
use App\Services\DeliveryMan\DeliveryManService;
use Carbon\CarbonInterval;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

class PasswordResetController extends BaseApiController
{
    private const OWNER_TYPE = 'deliveryman';

    private const DEMO_TOKEN = STATIC_OTP_CODE;

    public function __construct(
        protected DeliveryManService $deliveryManService,
        protected PasswordResetService $passwordResetService
    ) {}

    public function sendOtp(ForgotPasswordRequest $request): JsonResponse
    {
        $deliveryMan = $this->deliveryManService->findByPhone($request->input('phone'));

        if (! $deliveryMan) {
            return $this->errorResponse(config('response.default_404'), 'Phone number not found!', 'not-found');
        }

        $firebaseOtpVerification = Helpers::get_business_settings('firebase_otp_verification', false) ?? 0;
        $sendOtpVia = Helpers::get_business_settings('send_otp_via', false) ?? 'sms';

        if (($firebaseOtpVerification && $sendOtpVia == 'firebase') || getEnvMode() == 'demo') {
            return $this->successResponse(translate('messages.otp_sent_successful'));
        }

        $owner = $this->owner($deliveryMan);
        $wait = $this->passwordResetService->secondsUntilResend($owner);

        if ($wait > 0) {
            return $this->errorResponse(
                config('response.method_not_allowed_405'),
                translate('messages.Please try again after').$wait.' '.translate('messages.seconds'),
                'otp'
            );
        }

        $token = generateOtpCode();
        $this->passwordResetService->issueToken($owner, $token);

        $sent = $this->passwordResetService->dispatchOtp([
            'actor' => self::OWNER_TYPE,
            'notification_key' => 'deliveryman_forget_password',
            'mail_status_key' => 'forget_password_mail_status_dm',
            'email' => $deliveryMan->getRawOriginal('email'),
            'phone' => $request->input('phone'),
            'token' => $token,
            'mailable' => fn () => new DmPasswordResetMail($token, $deliveryMan),
        ]);

        return match (true) {
            $sent['sms'] && $sent['mail'] => $this->successResponse(translate('messages.OTP successfully sent to your phone and mail')),
            $sent['sms'] => $this->successResponse(translate('messages.OTP successfully sent to your phone')),
            $sent['mail'] => $this->successResponse(translate('OTP successfully sent to your mail')),
            default => $this->errorResponse(config('response.method_not_allowed_405'), translate('Failed to send SMS'), 'otp'),
        };
    }

    public function verifyOtp(VerifyTokenRequest $request): JsonResponse
    {
        $deliveryMan = $this->deliveryManService->findByPhone($request->input('phone'));

        if (! $deliveryMan) {
            return $this->errorResponse(config('response.default_404'), 'Phone number not found!', 'not-found');
        }

        $token = $request->input('reset_token');

        if (getEnvMode() == 'demo') {
            return $token == self::DEMO_TOKEN
                ? $this->tokenAccepted()
                : $this->invalidToken();
        }

        $owner = $this->owner($deliveryMan);

        if ($this->passwordResetService->tokenMatches($owner, $token)) {
            return $this->tokenAccepted();
        }

        $throttled = $this->passwordResetService->registerFailedAttempt($owner);

        if ($throttled) {
            return $this->errorResponse(
                config('response.method_not_allowed_405'),
                isset($throttled['seconds'])
                    ? translate('messages.Please try again after').CarbonInterval::seconds($throttled['seconds'])->cascade()->forHumans()
                    : translate('messages.Too many attempts'),
                $throttled['code']
            );
        }

        return $this->invalidToken();
    }

    public function resetPassword(ResetPasswordRequest $request): JsonResponse
    {
        $deliveryMan = $this->deliveryManService->findByPhone($request->input('phone'));

        if (! $deliveryMan) {
            return $this->errorResponse(config('response.default_404'), 'Phone number not found!', 'not-found');
        }

        $token = $request->input('reset_token');

        if (getEnvMode() == 'demo') {
            if ($token != self::DEMO_TOKEN) {
                return $this->invalidToken('invalid');
            }

            $this->deliveryManService->setPassword($deliveryMan, $request->input('confirm_password'));

            return $this->passwordChanged();
        }

        $owner = $this->owner($deliveryMan);

        if (! $this->passwordResetService->tokenMatches($owner, $token)) {
            return $this->invalidToken('invalid');
        }

        $this->deliveryManService->setPassword($deliveryMan, $request->input('confirm_password'));
        $this->passwordResetService->consumeToken($owner, $token);

        return $this->passwordChanged();
    }

    public function verifyFirebaseOtp(FirebaseVerifyRequest $request): JsonResponse
    {
        $webApiKey = Helpers::get_business_settings('firebase_web_api_key', false) ?? '';

        try {
            $response = Http::post('https://identitytoolkit.googleapis.com/v1/accounts:signInWithPhoneNumber?key='.$webApiKey, [
                'sessionInfo' => $request->input('sessionInfo'),
                'phoneNumber' => $request->input('phoneNumber'),
                'code' => $request->input('code'),
            ])->json();
        } catch (ConnectionException) {
            return $this->errorResponse(config('response.forbidden_403'), translate('Firebase verification service is unreachable'), '403');
        }

        if (isset($response['error'])) {
            return $this->errorResponse(config('response.forbidden_403'), $response['error']['message'], '403');
        }

        $deliveryMan = $this->deliveryManService->findByPhone($request->input('phoneNumber'));

        if (! $deliveryMan) {
            return $this->responseFormatter(['message' => translate('No data found')] + config('response.default_404'));
        }

        $this->passwordResetService->issueToken($this->owner($deliveryMan), $request->input('code'));

        return $this->tokenAccepted();
    }

    private function owner(mixed $deliveryMan): array
    {
        return $this->deliveryManService->contactKey($deliveryMan)
            + ['created_by' => self::OWNER_TYPE]
            + $this->passwordResetService->getTenantScope();
    }

    private function successResponse(string $message): JsonResponse
    {
        return $this->responseFormatter(['message' => $message] + config('response.default_200'));
    }

    private function tokenAccepted(): JsonResponse
    {
        return $this->successResponse('Token found, you can proceed');
    }

    private function passwordChanged(): JsonResponse
    {
        return $this->successResponse('Password changed successfully.');
    }

    private function invalidToken(string $code = 'reset_token'): JsonResponse
    {
        return $this->errorResponse(config('response.bad_request_400'), 'Invalid token.', $code);
    }
}
