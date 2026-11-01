<?php

namespace App\Http\Controllers\Api\V1\Vendor\Auth;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Requests\Vendor\Auth\ForgotPasswordRequest;
use App\Http\Requests\Vendor\Auth\ResetPasswordRequest;
use App\Http\Requests\Vendor\Auth\VerifyTokenRequest;
use App\Mail\PasswordResetMail;
use App\Services\Auth\PasswordResetService;
use App\Services\Vendor\VendorService;
use Carbon\CarbonInterval;
use Illuminate\Http\JsonResponse;

class PasswordResetController extends BaseApiController
{
    private const OWNER_TYPE = 'vendor';

    private const DEMO_TOKEN = STATIC_OTP_CODE;

    public function __construct(
        protected VendorService $vendorService,
        protected PasswordResetService $passwordResetService
    ) {}

    public function sendOtp(ForgotPasswordRequest $request): JsonResponse
    {
        $vendor = $this->vendorService->findByEmail($request->input('email'));

        if (! $vendor) {
            return $this->errorResponse(config('response.default_404'), 'Email not found!', 'not-found');
        }

        $owner = $this->owner($vendor);
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

        if (config('mail.status') && ! $this->passwordResetService->sendMail(
            null,
            $vendor->getRawOriginal('email'),
            fn () => new PasswordResetMail($token)
        )) {
            return $this->errorResponse(config('response.forbidden_403'), 'Failed to send email.', 'not-found');
        }

        return $this->successResponse('Email sent successfully.');
    }

    public function verifyOtp(VerifyTokenRequest $request): JsonResponse
    {
        $vendor = $this->vendorService->findByEmail($request->input('email'));
        $token = $request->input('reset_token');
        $owner = $this->owner($vendor);

        if ($this->passwordResetService->tokenMatches($owner, $token)
            || (getEnvMode() == 'demo' && $token == self::DEMO_TOKEN)) {
            return $this->successResponse(translate('OTP found, you can proceed'));
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

        return $this->errorResponse(config('response.bad_request_400'), 'Invalid OTP.', 'reset_token');
    }

    public function resetPassword(ResetPasswordRequest $request): JsonResponse
    {
        $vendor = $this->vendorService->findByEmail($request->input('email'));
        $token = $request->input('reset_token');
        $owner = $this->owner($vendor);

        $accepted = getEnvMode() == 'demo'
            ? $token == self::DEMO_TOKEN
            : $this->passwordResetService->tokenMatches($owner, $token);

        if (! $accepted) {
            return $this->errorResponse(config('response.bad_request_400'), translate('messages.Invalid OTP.'), 'invalid');
        }

        $this->vendorService->setPassword($vendor, $request->input('confirm_password'));
        $this->passwordResetService->consumeToken($owner, $token);

        return $this->successResponse(translate('Password changed successfully.'));
    }

    private function owner(mixed $vendor): array
    {
        return $this->vendorService->contactKey($vendor) + ['created_by' => self::OWNER_TYPE]
            + $this->passwordResetService->getTenantScope();
    }

    private function successResponse(string $message): JsonResponse
    {
        return $this->responseFormatter(['message' => $message] + config('response.default_200'));
    }
}
