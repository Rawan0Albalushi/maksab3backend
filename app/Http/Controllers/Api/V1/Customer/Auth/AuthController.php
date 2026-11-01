<?php

namespace App\Http\Controllers\Api\V1\Customer\Auth;

use App\Http\Controllers\Api\BaseApiController;
use App\Http\Requests\Customer\Auth\LoginRequest;
use App\Http\Requests\Customer\Auth\ProfileCompletionRequest;
use App\Http\Requests\Customer\Auth\RegisterRequest;
use App\Http\Resources\Customer\Auth\AuthSessionResource;
use App\Services\Customer\UserService;
use App\Services\Order\CartService;
use App\Traits\Customer\CustomerSessionTrait;
use Firebase\JWT\JWT;
use GuzzleHttp\Client;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Http;
use App\Services\Customer\EmailVerificationsService;
use App\Services\System\BusinessSettingService;

class AuthController extends BaseApiController
{
    use CustomerSessionTrait;

    private const APPLE_TOKEN_URL = 'https://appleid.apple.com/auth/token';

    private const APPLE_AUDIENCE = 'https://appleid.apple.com';

    private const APPLE_KEY_DIR = 'storage/app/public/apple-login/';

    public function __construct(
        private readonly UserService $userService,
        private readonly CartService $cart
    ) {
    }

    public function register(RegisterRequest $request): JsonResponse
    {
        $data = $request->payload();
        $referrer = $this->userService->resolveReferrer($data, translate('messages.Referer code not found'));

        if (isset($referrer['error'])) {
            return $this->referralError($referrer['error']);
        }

        $user = $this->userService->register($data, $referrer['ref_by']);
        $verification = $this->userService->startRegistrationVerification($data);

        if ($verification['error']) {
            return $this->responseFormatter(config('response.method_not_allowed_405'), errors: [$verification['error']]);
        }

        $this->userService->sendRegistrationMail($data);

        return $this->session([
            'token' => $verification['keep_token'] ? $user->createToken('RestaurantCustomerAuth')->accessToken : null,
            'is_phone_verified' => $verification['phone'],
            'is_email_verified' => $verification['mail'],
            'is_personal_info' => 1,
            'is_exist_user' => null,
            'login_type' => 'manual',
            'email' => $user->email ?: null,
        ]);
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $data = $request->payload();

        return match ($data['login_type']) {
            'manual' => $this->manualLogin($data),
            'otp' => $data['verified'] ? $this->otpLogin($data) : $this->requestOtp($data),
            'social' => $this->socialLogin($data),
            default => $this->responseFormatter(config('response.unauthorized_401'), errors: [
                ['code' => 'auth-001', 'message' => translate('messages.User Not Found!!!')],
            ]),
        };
    }

    public function updateInfo(ProfileCompletionRequest $request): JsonResponse
    {
        $data = $request->payload();
        $referrer = $this->userService->resolveReferrer($data, translate('Invalid referer code'));

        if (isset($referrer['error'])) {
            return $this->referralError($referrer['error']);
        }

        $result = $this->userService->completeProfile($data, $referrer['ref_by']);

        if ($result['status'] === 'not_found') {
            return $this->responseFormatter(config('response.default_404'), errors: [
                ['code' => 'user', 'message' => translate('No data found')],
            ]);
        }

        if ($result['status'] === 'already_exists') {
            return $this->responseFormatter(config('response.forbidden_403'), errors: [
                ['code' => 'user', 'message' => translate('Already exists')],
            ]);
        }

        return $this->session($this->sessionPayload($result['user'], $data['login_type'], $data['guest_id'], forceToken: true));
    }

    protected function cartService(): CartService
    {
        return $this->cart;
    }

    private function manualLogin(array $data): JsonResponse
    {
        $credentials = [
            $data['field_type'] == 'email' ? 'email' : 'phone' => $data['email_or_phone'],
            'password' => $data['password'],
        ];

        if (! auth()->attempt($credentials)) {
            return $this->responseFormatter(config('response.unauthorized_401'), errors: [
                ['code' => 'auth-001', 'message' => translate('User credential does not match')],
            ]);
        }

        $user = auth()->user();

        if (! $user->status) {
            return $this->blockedAccount();
        }

        $this->userService->ensureReferralCode($user);
        $this->userService->recordLoginMedium($user, 'manual');

        return $this->session($this->sessionPayload($user, 'manual', $data['guest_id']));
    }

    private function otpLogin(array $data): JsonResponse
    {
        // A test or demo install hands out a fixed code and has no phone anyone can read a real
        // one from, so that code is accepted without a stored verification row -- the same
        // allowance OtpController::verifyManual(), both password-reset flows and the storefront's
        // own loginWithOtp() already make. This endpoint was the one OTP check in the codebase
        // without it: it demanded a phone_verifications row even in test mode, so logging in with
        // the documented 123456 answered "OTP does not match" whenever no row happened to exist
        // for that number -- which is every login that did not just request its own OTP.
        $staticOtp = isStaticOtpMode() && (string) $data['otp'] === STATIC_OTP_CODE;

        if (! $staticOtp && ! $this->userService->findVerification('phone', $data['phone'], $data['otp'])) {
            return $this->responseFormatter(config('response.default_404'), errors: [
                ['code' => 'otp', 'message' => translate('OTP does not match')],
            ]);
        }

        if (($data['verified'] ?? 'default') === 'no') {
            $this->userService->replacePhoneOwner($data['phone']);
        }

        $user = $this->userService->findByPhone($data['phone']);

        // A correct code for a number nobody owns is not a login. Checked rather than assumed
        // because $user->status on a null would be a 500, and the static-OTP branch above reaches
        // this line without a verification row having proved anyone was ever sent a code here.
        if (! $user) {
            return $this->responseFormatter(config('response.default_404'), errors: [
                ['code' => 'auth-001', 'message' => translate('messages.User Not Found!!!')],
            ]);
        }

        if (! $user->status) {
            return $this->blockedAccount();
        }

        $this->userService->ensureReferralCode($user);
        $this->userService->markOtpLogin($user);
        $this->userService->consumeVerification('phone', $data['phone'], $data['otp']);

        return $this->session($this->sessionPayload($user, 'otp', $data['guest_id']));
    }

    private function requestOtp(array $data): JsonResponse
    {
        $user = $this->userService->findByPhone($data['phone']);

        if ($user && ! $user->status) {
            return $this->blockedAccount();
        }

        $error = $this->userService->sendLoginOtp($data['phone']);

        if ($error) {
            return $this->responseFormatter(config('response.method_not_allowed_405'), errors: [$error]);
        }

        return $this->session([
            'token' => null,
            'is_phone_verified' => 0,
            'is_email_verified' => 1,
            'is_personal_info' => 1,
            'is_exist_user' => null,
            'login_type' => 'otp',
            'email' => null,
        ]);
    }

    private function socialLogin(array $data): JsonResponse
    {
        $exchange = $this->socialProfile($data);

        if (isset($exchange['error'])) {
            return $this->responseFormatter(config('response.forbidden_403'), errors: [
                ['code' => 'social', 'message' => $exchange['error']],
            ]);
        }

        $resolved = $this->userService->resolveSocialUser($exchange['profile'], [
            'verified' => $data['verified'] ?? 'default',
            'medium' => $data['medium'],
            'unique_id' => $data['unique_id'],
            'email' => $exchange['email'],
        ]);

        if (isset($resolved['error'])) {
            return $this->responseFormatter(config('response.forbidden_403'), errors: [
                ['code' => 'social', 'message' => $resolved['error']],
            ]);
        }

        $user = $resolved['user'];

        if (! $user) {
            return $this->responseFormatter(config('response.unauthorized_401'), errors: [
                ['code' => 'auth-001', 'message' => translate('Unauthorized')],
            ]);
        }

        if ($resolved['pending']) {
            return $this->session($this->pendingSessionPayload($user, 'social', $resolved['is_exist_user']));
        }

        if (! $user->status) {
            return $this->blockedAccount();
        }

        $this->userService->completeSocialLogin($user, $data['medium']);

        return $this->session($this->sessionPayload($user, 'social', $data['guest_id']));
    }

    private function socialProfile(array $data): array
    {
        try {
            return match ($data['medium']) {
                'google' => $this->googleProfile($data),
                'facebook' => $this->facebookProfile($data),
                'apple' => $this->appleProfile($data),
                default => ['error' => 'wrong credential.'],
            };
        } catch (\Exception $exception) {
            return ['error' => $exception->getMessage()];
        }
    }

    private function googleProfile(array $data): array
    {
        $endpoint = $data['access_token'] == 1
            ? 'https://www.googleapis.com/oauth2/v3/userinfo?access_token=' . $data['token']
            : 'https://www.googleapis.com/oauth2/v3/tokeninfo?id_token=' . $data['token'];

        $profile = json_decode((new Client())->request('GET', $endpoint)->getBody()->getContents(), true);

        return $this->assertEmailMatches($profile, $data);
    }

    private function facebookProfile(array $data): array
    {
        $endpoint = 'https://graph.facebook.com/' . $data['unique_id']
            . '?access_token=' . $data['token'] . '&&fields=name,email';

        $profile = json_decode((new Client())->request('GET', $endpoint)->getBody()->getContents(), true);

        return $this->assertEmailMatches($profile, $data);
    }

    private function appleProfile(array $data): array
    {
        if ($data['has_verified']) {
            $verification = app(EmailVerificationsService::class)->findByTokenOnly($data['unique_id']);

            return $this->assertEmailMatches(['email' => $verification?->email], $data);
        }

        $settings = app(BusinessSettingService::class)->findValue('apple_login');
        $appleLogin = $settings ? json_decode($settings)[0] : null;

        if (! $appleLogin) {
            return ['error' => 'wrong credential.'];
        }

        $clientId = ($data['platform'] == 'flutter_app') ? $appleLogin->client_id_app : $appleLogin->client_id;
        $redirectUri = ($data['platform'] == 'flutter_web')
            ? ($appleLogin->redirect_url_flutter ?? 'www.example.com/apple-callback')
            : ($appleLogin->redirect_url_react ?? 'www.example.com/apple-callback');

        $clientSecret = JWT::encode([
            'iss' => $appleLogin->team_id,
            'iat' => strtotime('now'),
            'exp' => strtotime('+60days'),
            'aud' => self::APPLE_AUDIENCE,
            'sub' => $clientId,
        ], file_get_contents(self::APPLE_KEY_DIR . $appleLogin->service_file), 'ES256', $appleLogin->key_id);

        $response = Http::asForm()->post(self::APPLE_TOKEN_URL, [
            'grant_type' => 'authorization_code',
            'code' => $data['unique_id'],
            'redirect_uri' => $redirectUri,
            'client_id' => $clientId,
            'client_secret' => $clientSecret,
        ]);

        $claims = explode('.', (string) $response['id_token'])[1] ?? null;
        $profile = json_decode(base64_decode((string) $claims), true);

        if (! is_array($profile)) {
            return ['error' => 'wrong credential.'];
        }

        return ['profile' => $profile, 'email' => $profile['email'] ?? null];
    }

    private function assertEmailMatches(array $profile, array $data): array
    {
        $hasProviderId = isset($profile['id']) || isset($profile['kid']);

        if (strcmp((string) $data['email'], (string) ($profile['email'] ?? null)) != 0 && ! $hasProviderId) {
            return ['error' => translate('messages.Email does not match')];
        }

        return ['profile' => $profile, 'email' => $data['email']];
    }

    private function referralError(array $error): JsonResponse
    {
        return $this->responseFormatter(
            $this->configForStatus($error['status']),
            errors: [['code' => $error['field'], 'message' => $error['message']]]
        );
    }

    private function blockedAccount(): JsonResponse
    {
        return $this->responseFormatter(config('response.forbidden_403'), errors: [
            ['code' => 'auth-003', 'message' => translate('messages.Your account is blocked')],
        ]);
    }

    private function configForStatus(int $status): array
    {
        return match ($status) {
            405 => config('response.method_not_allowed_405'),
            default => config('response.forbidden_403'),
        };
    }

    private function session(array $payload): JsonResponse
    {
        return $this->responseFormatter(config('response.default_200'), new AuthSessionResource($payload));
    }
}
