<?php

namespace App\Http\Controllers\Api\V1\Customer\Auth;

use App\CentralLogics\Helpers;
use App\Http\Controllers\Api\BaseApiController;
use App\Http\Requests\Customer\Auth\FirebaseResetRequest;
use App\Http\Requests\Customer\Auth\ForgotPasswordRequest;
use App\Http\Requests\Customer\Auth\ResetPasswordRequest;
use App\Http\Requests\Customer\Auth\VerifyResetTokenRequest;
use App\Mail\UserPasswordResetMail;
use App\Services\Auth\PasswordResetService;
use App\Services\Customer\UserService;
use Carbon\CarbonInterval;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use App\Services\Payment\SettingService;

class PasswordResetController extends BaseApiController
{
    private const OWNER_TYPE = 'user';

    private const TEST_TOKEN = STATIC_OTP_CODE;

    public function __construct(
        protected UserService $userService,
        protected PasswordResetService $passwordResetService
    ) {}

    public function sendOtp(ForgotPasswordRequest $request): JsonResponse
    {
        $customer = $this->resolveCustomer($request);

        if (! $customer) {
            return $this->errorResponse(config('response.default_404'), translate('messages.user_not_found!'), 'not-found');
        }

        $firebaseOtpVerification = Helpers::get_business_settings('firebase_otp_verification', false) ?? 0;
        $sendOtpVia = Helpers::get_business_settings('send_otp_via', false) ?? 'sms';

        if ($firebaseOtpVerification && $sendOtpVia == 'firebase') {
            return $this->successResponse(translate('messages.otp_sent_successful'));
        }

        $owner = $this->owner($request);
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

        if (getEnvMode() == 'test') {
            return $this->successResponse(translate('messages.Use test OTP'));
        }

        $smsActive = app(SettingService::class)->hasActiveSmsGateway();

        if ($smsActive && filled($request->input('phone'))) {
            return $this->passwordResetService->sendSms($request->input('phone'), $token)
                ? $this->successResponse(translate('messages.OTP successfully sent to your phone'))
                : $this->errorResponse(config('response.forbidden_403'), translate('Failed to send SMS'), 'otp');
        }

        if (! config('mail.status')) {
            return $this->errorResponse(config('response.forbidden_403'), translate('messages.Failed to send otp'), 'otp');
        }

        $sent = $this->passwordResetService->sendMail(
            'forget_password_mail_status_user',
            $customer->getRawOriginal('email'),
            fn () => new UserPasswordResetMail($token, $customer->f_name)
        );

        return $sent
            ? $this->successResponse(translate('OTP successfully sent to your mail'))
            : $this->errorResponse(config('response.forbidden_403'), translate('messages.Failed to send mail'), 'otp');
    }

    public function verifyOtp(VerifyResetTokenRequest $request): JsonResponse
    {
        if (! $this->resolveCustomer($request)) {
            return $this->errorResponse(config('response.default_404'), translate('Phone number not found!'), 'not-found');
        }

        $token = $request->input('reset_token');

        if (getEnvMode() == 'test') {
            return $token == self::TEST_TOKEN ? $this->tokenAccepted() : $this->invalidToken();
        }

        $owner = $this->owner($request);

        if ($this->passwordResetService->tokenMatches($owner, $token)) {
            return $this->responseFormatter(['message' => translate('OTP found, you can proceed')] + config('response.default_200'));
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
        $customer = $this->resolveCustomer($request);

        if (! $customer) {
            return $this->errorResponse(config('response.default_404'), translate('messages.user_not_found!'), 'not-found');
        }

        $token = $request->input('reset_token');
        $owner = $this->owner($request);
        $matches = $this->passwordResetService->tokenMatches($owner, $token);

        if (getEnvMode() == 'test') {
            if ($token != self::TEST_TOKEN) {
                return $this->responseFormatter(['message' => translate('OTP does not match')] + config('response.default_404'));
            }
        } elseif (! $matches) {
            return $this->errorResponse(config('response.bad_request_400'), translate('messages.Invalid OTP.'), 'invalid');
        }

        $this->userService->setPassword($customer, $request->input('confirm_password'));

        if ($matches) {
            $this->passwordResetService->consumeToken($owner, $token);
        }

        return $this->successResponse(translate('Password changed successfully.'));
    }

    public function verifyFirebaseOtp(FirebaseResetRequest $request): JsonResponse
    {
        $verified = $this->userService->verifyFirebaseOtp(
            $request->input('sessionInfo'),
            $request->input('phoneNumber'),
            $request->input('code')
        );

        if (! $verified['is_success']) {
            return $this->errorResponse(config('response.forbidden_403'), $verified['message'], '403');
        }

        $customer = $this->userService->findByPhone($request->input('phoneNumber'));

        if (! $customer) {
            return $this->responseFormatter(['message' => translate('No data found')] + config('response.default_404'));
        }

        if ($request->input('is_reset_token') == 1) {
            $this->passwordResetService->issueToken(
                ['phone' => $customer->phone] + $this->ownerScope(),
                $request->input('code')
            );

            return $this->tokenAccepted();
        }

        if ($customer->is_phone_verified) {
            return $this->successResponse(translate('messages.Phone number is already verified'));
        }

        $this->userService->markVerified($customer, 'phone');

        return $this->responseFormatter(
            ['message' => translate('messages.Phone number verified successfully')] + config('response.default_200'),
            ['otp' => 'inactive']
        );
    }

    private function resolveCustomer(Request $request): mixed
    {
        return filled($request->input('phone'))
            ? $this->userService->findByPhone($request->input('phone'))
            : $this->userService->findByEmail($request->input('email'));
    }

    private function owner(Request $request): array
    {
        $key = filled($request->input('phone'))
            ? ['phone' => $request->input('phone')]
            : ['email' => $request->input('email')];

        return $key + $this->ownerScope();
    }

    private function ownerScope(): array
    {
        return $this->passwordResetService->getTenantScope() + ['created_by' => self::OWNER_TYPE];
    }

    private function successResponse(string $message): JsonResponse
    {
        return $this->responseFormatter(['message' => $message] + config('response.default_200'));
    }

    private function tokenAccepted(): JsonResponse
    {
        return $this->successResponse('OTP found, you can proceed');
    }

    private function invalidToken(): JsonResponse
    {
        return $this->errorResponse(config('response.bad_request_400'), translate('Invalid OTP.'), 'invalid');
    }
}
