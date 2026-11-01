<?php

namespace App\Services\Customer;

use App\CentralLogics\Helpers;
use App\Mail\CustomerRegistration;
use App\Mail\EmailVerification;
use App\Models\User;
use App\Scopes\HostScope;
use App\Services\BaseService;
use App\Services\Chat\UserInfoService;
use App\Services\Order\OrderService;
use App\Services\Payment\ProCustomerSubscriptionService;
use App\Services\Payment\WalletTransactionService;
use App\Services\System\UserNotificationService;
use App\Services\Zone\ZoneService;
use Carbon\CarbonInterval;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Modules\RideShare\Services\RideReviewService;
use Modules\Service\Services\ServiceBookingService;
use App\Support\Notification\SendNotification;
use App\Support\Notification\NotificationMessages;
use App\Services\System\BusinessSettingService;
use App\Support\Notification\Sms;
use App\Support\Storage\FileStorage;
use Illuminate\Support\Facades\Log;

class UserService extends BaseService
{

    public const TEST_OTP = STATIC_OTP_CODE;

    private const OTP_INTERVAL_SECONDS = 60;

    private const MAX_OTP_HIT = 5;

    private const MAX_OTP_HIT_WINDOW = 60;

    private const TEMP_BLOCK_SECONDS = 600;

    private const ACTIVE_ORDER_STATUSES = ['pending', 'accepted', 'confirmed', 'processing', 'handover', 'picked_up'];

    public function findFirebaseToken(mixed $id): mixed
    {
        return $this->find($id)?->cm_firebase_token;
    }

    public function isProCustomer(mixed $userId): bool
    {
        return User::where('id', $userId)->where('pro_status', 1)->exists();
    }

    public function find(mixed $id, array $relations = [], array $counts = []): ?User
    {
        return User::with($relations)->withCount($counts)->find($id);
    }

    public function getBasicByIds(mixed $ids): mixed
    {
        $ids = array_values(array_filter((array) $ids, fn ($id) => is_numeric($id)));

        return $ids
            ? User::withoutGlobalScopes()->whereIn('id', $ids)->get(['id', 'f_name', 'l_name'])
            : collect();
    }

    public function findFullName(mixed $id): string
    {
        $user = User::where('id', $id)->first();

        return $user->f_name.' '.$user->l_name;
    }

    public function getByIdsWithStorage(mixed $ids): mixed
    {
        return User::withStorage()->whereIn('id', $ids)->get();
    }

    public function countAll(): int
    {
        return User::count();
    }

    public function rememberLanguage(User $user, string $languageKey): void
    {
        $user->current_language_key = $languageKey;
        $user->save();
    }

    public function profileExtras(User $user): array
    {
        $coreOrderCount = (int) $user->orders()->count();
        $orderCount = $coreOrderCount;

        if (addon_published_status('Service')) {
            $orderCount += app(ServiceBookingService::class)->countVisibleForUser($user->id);
        }

        $discount = Helpers::getCusromerFirstOrderDiscount(
            order_count: $coreOrderCount,
            user_creation_date: $user->created_at,
            refby: $user->ref_by
        );

        return [
            'order_count' => $orderCount,
            'member_since_days' => (int) $user->created_at->diffInDays(),
            'selected_modules_for_interest' => $user->module_ids ? json_decode($user->module_ids, true) : [],
            'is_valid_for_discount' => data_get($discount, 'is_valid'),
            'discount_amount' => (float) data_get($discount, 'discount_amount'),
            'discount_amount_type' => data_get($discount, 'discount_amount_type'),
            'validity' => (string) data_get($discount, 'validity'),
            'pro_subscription' => app(ProCustomerSubscriptionService::class)->findForUser($user->id),
            'rating' => $this->rideRating($user->id),
        ];
    }

    public function mergeInterest(User $user, array $interest, mixed $moduleId): bool
    {
        $moduleIds = $user->module_ids ? json_decode($user->module_ids, true) : [];
        $moduleIds[] = $moduleId;

        $existing = $user->interest ? json_decode($user->interest, true) : [];

        $user->interest = json_encode(array_values(array_unique(array_merge($existing, $interest))));
        $user->module_ids = json_encode(array_unique($moduleIds));

        return $user->save();
    }

    public function updateFirebaseToken(User $user, mixed $token): bool
    {
        $user->cm_firebase_token = $token;

        return $user->save();
    }

    public function syncZoneFromCoordinates(User $user, mixed $longitude, mixed $latitude): void
    {
        if (! $longitude || ! $latitude) {
            return;
        }

        try {
            $user->zone_id = app(ZoneService::class)->findSmallestContaining($longitude, $latitude)?->id;
            $user->save();
        } catch (\Exception $exception) {
            Log::warning('customer.user_service.sync_zone_from_coordinates_failed', [
                'error' => $exception->getMessage(),
                'file' => $exception->getFile().':'.$exception->getLine(),
            ]);
        }
    }

    public function hasOngoingOrders(mixed $userId): bool
    {
        return app(OrderService::class)->hasOngoingForUser($userId, self::ACTIVE_ORDER_STATUSES);
    }

    public function removeAccount(User $user): void
    {
        $user->token()?->revoke();

        if ($user->userinfo) {
            $user->userinfo->delete();
        }

        $user->delete();
    }

    public function update(User $user, array $data): bool
    {
        $imageName = isset($data['image'])
            ? FileStorage::update(dir: 'profile/', old_image: $user->image, image: $data['image'])
            : $user->image;

        $password = (! empty($data['password']) && strlen($data['password']) > 5)
            ? bcrypt($data['password'])
            : $user->password;

        [$firstName, $lastName] = $this->splitName($data['name'] ?? '');

        $user->f_name = $firstName;
        $user->l_name = $lastName;
        $user->image = $imageName;
        $user->password = $password;
        $user->phone = $data['phone'] ?? $user->phone;
        $user->email = $data['email'] ?? $user->email;
        $user->save();

        if ($user->userinfo) {
            app(UserInfoService::class)->updateForUser($user->id, [
                'f_name' => $firstName,
                'l_name' => $lastName,
                'email' => $data['email'] ?? $user->email,
                'image' => $imageName,
            ]);
        }

        return true;
    }

    public function findWithInfo(mixed $id): ?User
    {
        return $this->find($id, ['userinfo']);
    }

    public function pendingVerification(User $user, array $data): ?array
    {
        if (($data['button_type'] ?? null) == 'change_password' || ($data['otp'] ?? null)) {
            return null;
        }

        $settings = app(BusinessSettingService::class)->valuesFor([
            'email_verification_status', 'phone_verification_status', 'firebase_otp_verification', 'send_otp_via',
        ]);
        $buttonType = $data['button_type'] ?? null;

        if (data_get($settings, 'phone_verification_status') == 1
            && ($user->phone != ($data['phone'] ?? null) || $buttonType == 'phone' || (! $user->is_phone_verified && ! $buttonType))) {
            if (data_get($settings, 'firebase_otp_verification') == 1 && data_get($settings, 'send_otp_via') == 'firebase') {
                return [
                    'verification_on' => 'phone',
                    'verification_medium' => 'firebase',
                    'otp_send' => true,
                    'message' => translate('OTP successfully sent'),
                    'code' => 200,
                ];
            }

            $result = $this->sendPhoneOtp($data['phone'] ?? null);

            return [
                'verification_on' => 'phone',
                'verification_medium' => 'SMS',
                'otp_send' => $result['is_success'],
                'message' => $result['message'],
                'code' => $result['code'],
            ];
        }

        if (data_get($settings, 'email_verification_status') == 1
            && ($user->email != ($data['email'] ?? null) || $buttonType == 'email' || (! $user->is_email_verified && ! $buttonType))) {
            $result = $this->sendEmailOtp($data['email'] ?? null, $user?->f_name.' '.$user?->l_name);

            return [
                'verification_on' => 'email',
                'verification_medium' => 'email',
                'otp_send' => $result['is_success'],
                'message' => $result['message'],
                'code' => $result['code'],
            ];
        }

        return null;
    }

    public function verifyContact(User $user, array $data): array
    {
        if ($user->is_email_verified == 1 && $user->email != ($data['email'] ?? null)) {
            $user->is_email_verified = 0;
            $user->save();
        }

        $message = null;
        $otp = $data['otp'] ?? null;

        if (($data['verification_on'] ?? null) == 'phone' && $otp) {
            $result = ($data['verification_medium'] ?? null) == 'firebase'
                ? $this->verifyFirebaseOtp($data['session_info'] ?? null, $data['phone'] ?? null, $otp)
                : $this->verifySmsOtp($data['phone'] ?? null, $otp);

            if (! $result['is_success']) {
                return ['is_success' => false, 'verification_on' => 'phone'] + $result;
            }

            $user->is_phone_verified = 1;
            $user->save();
            $message = translate('messages.Phone successfully verified');
        }

        if (($data['verification_on'] ?? null) == 'email' && $otp) {
            $result = $this->verifyEmailOtp($data['email'] ?? null, $otp);

            if (! $result['is_success']) {
                return ['is_success' => false, 'verification_on' => 'email'] + $result;
            }

            $user->is_email_verified = 1;
            $user->save();
            $message = translate('messages.Email successfully verified');
        }

        return ['is_success' => true, 'message' => $message];
    }

    public function verifyFirebaseOtp(mixed $sessionInfo, mixed $phone, mixed $otp): array
    {
        $webApiKey = app(BusinessSettingService::class)->value('firebase_web_api_key', false) ?? '';

        try {
            $response = Http::post(
                'https://identitytoolkit.googleapis.com/v1/accounts:signInWithPhoneNumber?key='.$webApiKey,
                ['sessionInfo' => $sessionInfo, 'phoneNumber' => $phone, 'code' => $otp]
            );
        } catch (ConnectionException) {
            return $this->otpResult(false, translate('Firebase verification service is unreachable'), 403, 'firebase');
        }

        $body = $response->json();

        if (isset($body['error'])) {
            return $this->otpResult(false, $body['error']['message'], 403, 'firebase');
        }

        return $this->otpResult(true, translate('OTP verification successful'), 200, 'firebase');
    }

    public function findByPhone(mixed $phone): ?User
    {
        return $phone ? $this->findBy('phone', $phone) : null;
    }

    public function findByEmail(mixed $email): ?User
    {
        return $email ? $this->findBy('email', $email) : null;
    }

    public function setPassword(User $user, string $password): bool
    {
        $user->password = bcrypt($password);

        return $user->save();
    }

    public function existingUserSummary(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->f_name.' '.$user->l_name,
            'image' => $user->image_full_url,
        ];
    }

    public function ensureReferralCode(User $user): void
    {
        if ($user->ref_code == null && isset($user->id)) {
            $user->ref_code = Helpers::generate_referer_code($user);
            User::where('id', $user->id)->update(['ref_code' => $user->ref_code]);
        }
    }

    public function createOtpUser(mixed $phone): User
    {
        $user = new User;
        $user->phone = $phone;
        $user->password = bcrypt($phone);
        $user->is_phone_verified = 1;
        $user->login_medium = 'otp';
        $user->save();

        $this->ensureReferralCode($user);

        return $user;
    }

    public function markVerified(User $user, string $verificationType): void
    {
        if ($verificationType == 'email') {
            $user->is_email_verified = 1;
        } elseif ($verificationType == 'phone') {
            $user->is_phone_verified = 1;
        }

        $user->save();
    }

    public function findVerification(string $verificationType, mixed $identifier, mixed $otp): mixed
    {
        return $this->verificationService($verificationType)->findByToken($identifier, $otp);
    }

    public function consumeVerification(string $verificationType, mixed $identifier, mixed $otp): void
    {
        $this->verificationService($verificationType)->deleteByToken($identifier, $otp);
    }

    public function throttlePhoneOtp(mixed $phone): ?array
    {
        $record = app(PhoneVerificationService::class)->findByPhone($phone);

        if ($record) {
            if ($record->temp_block_time && Carbon::parse($record->temp_block_time)->diffInSeconds() <= self::TEMP_BLOCK_SECONDS) {
                $wait = round(self::TEMP_BLOCK_SECONDS - Carbon::parse($record->temp_block_time)->diffInSeconds());

                return [
                    'code' => 'otp_block_time',
                    'message' => translate('messages.Please try again after').CarbonInterval::seconds($wait)->cascade()->forHumans(),
                ];
            }

            if ($record->is_temp_blocked == 1 && Carbon::parse($record->updated_at)->diffInSeconds() >= self::MAX_OTP_HIT_WINDOW) {
                app(PhoneVerificationService::class)->upsertForPhone($phone, ['otp_hit_count' => 0, 'is_temp_blocked' => 0, 'temp_block_time' => null, 'created_at' => now(), 'updated_at' => now()]);
            }

            if ($record->otp_hit_count >= self::MAX_OTP_HIT
                && Carbon::parse($record->updated_at)->diffInSeconds() < self::MAX_OTP_HIT_WINDOW
                && $record->is_temp_blocked == 0) {
                app(PhoneVerificationService::class)->upsertForPhone($phone, ['is_temp_blocked' => 1, 'temp_block_time' => now(), 'created_at' => now(), 'updated_at' => now()]);

                return ['code' => 'otp_temp_blocked', 'message' => translate('messages.Too many attempts')];
            }
        }

        app(PhoneVerificationService::class)->upsertForPhone($phone, ['otp_hit_count' => DB::raw('otp_hit_count + 1'), 'updated_at' => now(), 'temp_block_time' => null]);

        return null;
    }

    public function storeFirebaseOtp(mixed $phone, mixed $otp): void
    {
        app(PhoneVerificationService::class)->upsertForPhone($phone, ['token' => $otp, 'otp_hit_count' => 0, 'created_at' => now(), 'updated_at' => now()]);
    }

    public function sendLoginOtp(mixed $phone): ?array
    {
        $firebaseVerification = app(BusinessSettingService::class)->value('firebase_otp_verification', false) ?? 0;
        $sendOtpVia = app(BusinessSettingService::class)->value('send_otp_via', false) ?? 'sms';

        if ($firebaseVerification && $sendOtpVia != 'sms') {
            return null;
        }

        $existing = app(PhoneVerificationService::class)->findByPhone($phone);

        if ($existing && Carbon::parse($existing->updated_at)->diffInSeconds() < self::OTP_INTERVAL_SECONDS) {
            $wait = round(self::OTP_INTERVAL_SECONDS - Carbon::parse($existing->updated_at)->diffInSeconds());

            return ['code' => 'otp', 'message' => translate('messages.Please try again after').$wait.' '.translate('messages.seconds')];
        }

        $otp = $this->generateOtp();

        app(PhoneVerificationService::class)->upsertForPhone($phone, ['token' => $otp, 'otp_hit_count' => 0, 'created_at' => now(), 'updated_at' => now()]);

        $response = Sms::deliver($phone, $otp);

        if (getEnvMode() !== 'test' && $response !== 'success') {
            return ['code' => 'otp', 'message' => translate('Failed to send SMS')];
        }

        return null;
    }

    public function resolveReferrer(array $data, string $notFoundMessage): array
    {
        if (! ($data['ref_code'] ?? null)) {
            return ['ref_by' => null];
        }

        if (app(BusinessSettingService::class)->value('ref_earning_status', false) != '1') {
            return ['error' => ['field' => 'ref_code', 'message' => translate('messages.Referral is disabled'), 'status' => 403]];
        }

        $referrer = User::where('ref_code', '=', $data['ref_code'])->first();

        if (! $referrer || ! $referrer->status) {
            return ['error' => ['field' => 'ref_code', 'message' => $notFoundMessage, 'status' => 405]];
        }

        if (app(WalletTransactionService::class)->referenceExists($data['phone'] ?? null)) {
            return ['error' => ['field' => 'phone', 'message' => translate('Referrer code already used'), 'status' => 203]];
        }

        $this->notifyReferrer($referrer, $data['name'] ?? '');

        return ['ref_by' => $referrer->id];
    }

    public function register(array $data, mixed $refBy): User
    {
        [$firstName, $lastName] = $this->splitName($data['name'] ?? '');

        $user = User::create([
            'f_name' => $firstName,
            'l_name' => $lastName,
            'email' => $data['email'] ?? null,
            'phone' => $data['phone'] ?? null,
            'ref_by' => $refBy,
            'password' => bcrypt($data['password'] ?? ''),
        ]);

        $user->ref_code = Helpers::generate_referer_code($user);
        $user->save();

        return $user;
    }

    public function startRegistrationVerification(array $data): array
    {
        $settings = app(BusinessSettingService::class)->valuesFor([
            'manual_login_status', 'otp_login_status', 'social_login_status', 'google_login_status',
            'facebook_login_status', 'apple_login_status', 'email_verification_status',
            'phone_verification_status', 'send_otp_via',
        ]);
        $firebaseVerification = app(BusinessSettingService::class)->value('firebase_otp_verification', false) ?? 0;

        if (data_get($settings, 'phone_verification_status') == 1) {
            if (! $firebaseVerification || data_get($settings, 'send_otp_via') == 'sms') {
                return ['phone' => 0, 'mail' => 1, 'keep_token' => false, 'error' => $this->sendLoginOtp($data['phone'] ?? null)];
            }

            return ['phone' => 0, 'mail' => 1, 'keep_token' => true, 'error' => null];
        }

        if (data_get($settings, 'email_verification_status') == 1) {
            return [
                'phone' => 1,
                'mail' => 0,
                'keep_token' => false,
                'error' => $this->sendRegistrationEmailOtp($data['email'] ?? null, $data['name'] ?? null),
            ];
        }

        return ['phone' => 1, 'mail' => 1, 'keep_token' => true, 'error' => null];
    }

    public function sendRegistrationMail(array $data): void
    {
        try {
            if (SendNotification::canSendMail('registration_mail_status_user', 'customer', 'customer_registration') && ($data['email'] ?? null)) {
                SendNotification::mail($data['email'], new CustomerRegistration($data['name'] ?? null));
            }
        } catch (\Exception $exception) {
            Log::error('customer.user_service.send_registration_mail_failed', [
                'error' => $exception->getMessage(),
                'file' => $exception->getFile().':'.$exception->getLine(),
            ]);
        }
    }

    public function completeProfile(array $data, mixed $refBy): array
    {
        $user = in_array($data['login_type'] ?? null, ['otp', 'manual'])
            ? $this->findByPhone($data['phone'] ?? null)
            : $this->findByEmail($data['email'] ?? null);

        if (! $user) {
            return ['status' => 'not_found'];
        }

        if ($user->f_name) {
            return ['status' => 'already_exists'];
        }

        [$firstName, $lastName] = $this->splitName($data['name'] ?? '');

        $user->f_name = $firstName;
        $user->l_name = $lastName;
        $user->email = $data['email'] ?? $user->email;
        $user->phone = $data['phone'] ?? $user->phone;
        $user->ref_by = $refBy;
        $user->save();

        return ['status' => 'ok', 'user' => $user];
    }

    public function recordLoginMedium(User $user, string $medium): void
    {
        $user->login_medium = $medium;
        $user->save();
    }

    public function replacePhoneOwner(mixed $phone): void
    {
        $existing = $this->findByPhone($phone);

        if ($existing) {
            $existing->phone = null;
            $existing->save();
        }

        $user = new User;
        $user->phone = $phone;
        $user->password = bcrypt($phone);
        $user->save();
    }

    public function markOtpLogin(User $user): void
    {
        $user->login_medium = 'otp';
        $user->is_phone_verified = 1;
        $user->save();
    }

    public function resolveSocialUser(array $profile, array $context): array
    {
        $user = $this->findByEmail($profile['email'] ?? null);
        $isExistUser = null;

        if ($user && $context['verified'] == 'default' && $user->is_email_verified == 0 && $user->is_from_pos == 0) {
            if ($context['medium'] == 'apple') {
                app(EmailVerificationsService::class)->upsertForEmail($profile['email'], ['token' => $context['unique_id'], 'created_at' => now(), 'updated_at' => now()]);
            }

            return ['user' => $user, 'is_exist_user' => $this->existingUserSummary($user), 'pending' => true];
        }

        if (($user && $context['verified'] == 'no') || (! $user && $context['verified'] == 'default')) {
            if (strcmp((string) $context['email'], (string) ($profile['email'] ?? null)) === 0) {
                $socialId = null;

                if ($context['medium'] != 'apple') {
                    $socialId = $profile['id'] ?? $profile['kid'] ?? $profile['sub'] ?? null;

                    if ($socialId === null) {
                        return ['error' => 'wrong credential.'];
                    }
                }

                try {
                    if ($user && $context['verified'] == 'no') {
                        $user->email = null;
                        $user->save();
                    }

                    $user = new User;
                    $user->email = $profile['email'];
                    $user->login_medium = $context['medium'];
                    $user->temp_token = $context['unique_id'];

                    if ($context['medium'] != 'apple') {
                        $user->social_id = $socialId;
                    }

                    $user->save();
                } catch (\Throwable $exception) {
                    return ['error' => 'wrong credential.'];
                }
            }
        }

        if ($context['medium'] == 'apple') {
            app(EmailVerificationsService::class)->deleteByToken($profile['email'] ?? null, $context['unique_id']);
        }

        return ['user' => $user, 'is_exist_user' => $isExistUser, 'pending' => false];
    }

    public function completeSocialLogin(User $user, string $medium): void
    {
        $this->ensureReferralCode($user);

        $user->login_medium = $medium;
        $user->is_email_verified = 1;
        $user->save();
    }

    public function findWithOrderCount(mixed $id): ?User
    {
        return $this->find($id, counts: ['orders']);
    }

    public function clearProStatus(array $userIds): void
    {
        User::withoutGlobalScopes()->whereIn('id', $userIds)->update(['pro_status' => 0]);
    }

    public function findWithTripCount(mixed $id): ?User
    {
        return $this->find($id, counts: ['trips']);
    }

    public function existsUnscoped(string $column, mixed $value, array $extraWhere = []): bool
    {
        return User::withoutGlobalScope(HostScope::class)
            ->where($column, $value)
            ->where($extraWhere)
            ->exists();
    }

    private function findBy(string $column, mixed $value): ?User
    {
        return User::where($column, $value)->first();
    }

    private function verificationService(string $verificationType): mixed
    {
        return $verificationType == 'email'
            ? app(EmailVerificationsService::class)
            : app(PhoneVerificationService::class);
    }

    private function sendPhoneOtp(mixed $phone): array
    {
        $existing = app(PhoneVerificationService::class)->findByPhone($phone);

        if ($existing && Carbon::parse($existing->updated_at)->diffInSeconds() < self::OTP_INTERVAL_SECONDS) {
            $wait = round(self::OTP_INTERVAL_SECONDS - Carbon::parse($existing->updated_at)->diffInSeconds());

            return $this->otpResult(false, translate('messages.Please try again after').$wait.' '.translate('messages.seconds'), 403);
        }

        $otp = $this->generateOtp();

        app(PhoneVerificationService::class)->upsertForPhone($phone, ['token' => $otp, 'otp_hit_count' => 0, 'created_at' => now(), 'updated_at' => now()]);

        $response = Sms::deliver($phone, $otp);

        if (getEnvMode() !== 'test' && $response !== 'success') {
            return $this->otpResult(false, translate('Failed to send OTP'), 403);
        }

        return $this->otpResult(true, translate('OTP successfully sent'), 200);
    }

    private function dispatchEmailOtp(mixed $email, ?string $name, string $mailStatusKey, ?string $template = null): ?string
    {
        $otp = $this->generateOtp();

        app(EmailVerificationsService::class)->upsertForEmail($email, ['token' => $otp, 'created_at' => now(), 'updated_at' => now()]);

        try {
            if (SendNotification::canSendMail($mailStatusKey)) {
                SendNotification::mail($email, $template === null
                    ? new EmailVerification($otp, $name)
                    : new EmailVerification($otp, $name, $template));

                return 'success';
            }
        } catch (\Exception $exception) {
            return null;
        }

        return null;
    }

    private function sendEmailOtp(mixed $email, ?string $name): array
    {
        $mailResponse = $this->dispatchEmailOtp($email, $name, 'email_verification_status', 'profile_update');

        if (getEnvMode() !== 'test' && $mailResponse !== 'success') {
            return $this->otpResult(false, translate('Failed to send mail'), 403);
        }

        return $this->otpResult(true, translate('OTP successfully sent to mail'), 200);
    }

    private function verifyEmailOtp(mixed $email, mixed $otp): array
    {
        $verification = app(EmailVerificationsService::class)->findHostScopedByToken($email, $otp);

        if ($verification) {
            $verification->delete();

            return $this->otpResult(true, translate('OTP verification successful'), 200, 'email');
        }

        return $this->otpResult(false, translate('OTP does not match'), 403, 'email');
    }

    private function verifySmsOtp(mixed $phone, mixed $otp): array
    {
        $verification = app(PhoneVerificationService::class)->findHostScopedByToken($phone, $otp);

        if ($verification) {
            $verification->delete();

            return $this->otpResult(true, translate('OTP verification successful'), 200, 'SMS');
        }

        $record = app(PhoneVerificationService::class)->findHostScopedByPhone($phone);

        if (! $record) {
            return $this->otpResult(false, translate('Phone not found!!!'), 403, 'SMS');
        }

        if ($record->temp_block_time && Carbon::parse($record->temp_block_time)->diffInSeconds() <= self::TEMP_BLOCK_SECONDS) {
            $wait = round(self::TEMP_BLOCK_SECONDS - Carbon::parse($record->temp_block_time)->diffInSeconds());

            return $this->otpResult(
                false,
                translate('messages.Please try again after').CarbonInterval::seconds($wait)->cascade()->forHumans(),
                403,
                'SMS'
            );
        }

        if ($record->is_temp_blocked == 1 && Carbon::parse($record->updated_at)->diffInSeconds() >= self::MAX_OTP_HIT_WINDOW) {
            $record->otp_hit_count = 0;
            $record->is_temp_blocked = 0;
            $record->temp_block_time = null;
            $record->created_at = now();
            $record->updated_at = now();
            $record->save();
        }

        if ($record->otp_hit_count >= self::MAX_OTP_HIT
            && Carbon::parse($record->updated_at)->diffInSeconds() < self::MAX_OTP_HIT_WINDOW
            && $record->is_temp_blocked == 0) {
            $record->is_temp_blocked = 1;
            $record->temp_block_time = now();
            $record->created_at = now();
            $record->updated_at = now();
            $record->save();

            return $this->otpResult(false, translate('messages.Too many attempts'), 403, 'SMS');
        }

        $record->otp_hit_count = $record->otp_hit_count + 1;
        $record->updated_at = now();
        $record->temp_block_time = null;
        $record->save();

        return $this->otpResult(false, translate('OTP does not match'), 403, 'SMS');
    }

    private function sendRegistrationEmailOtp(mixed $email, ?string $name): ?array
    {
        $mailResponse = $this->dispatchEmailOtp($email, $name, 'registration_otp_mail_status_user');

        if (getEnvMode() !== 'test' && $mailResponse !== 'success') {
            return ['code' => 'otp', 'message' => translate('messages.Failed to send mail')];
        }

        return null;
    }

    public function notifyFundAdded(mixed $userId): bool
    {
        $token = $this->findFirebaseToken($userId);

        if (SendNotification::channelEnabled('customer', 'customer_add_fund_to_wallet', 'push_notification_status') && $token) {
            $data = NotificationMessages::fundAddedToWallet();
            SendNotification::pushToCustomer($userId, $token, $data);
        }

        return true;
    }

    private function notifyReferrer(User $referrer, string $name): void
    {
        [$firstName, $lastName] = $this->splitName($name);

        $notification = NotificationMessages::referralCodeUsed($firstName, $lastName);

        if (SendNotification::channelEnabled('customer', 'customer_new_referral_join', 'push_notification_status')
            && $referrer->cm_firebase_token) {
            SendNotification::sendToDevice($referrer->cm_firebase_token, $notification);
            app(UserNotificationService::class)->record($referrer->id, $notification);
        }
    }

    private function rideRating(mixed $userId): array
    {
        if (! addon_published_status('RideShare')) {
            return ['average_rating' => 0, 'total_review' => 0];
        }

        $reviews = app(RideReviewService::class)->customerRatingSummary($userId);

        return [
            'average_rating' => $reviews->average_rating ? round($reviews->average_rating, 2) : 0,
            'total_review' => $reviews->total_review ?? 0,
        ];
    }

    private function splitName(string $name): array
    {
        $parts = explode(' ', $name, 2);

        return [$parts[0], $parts[1] ?? ''];
    }

    private function generateOtp(): string
    {
        return generateOtpCode();
    }

    private function otpResult(bool $success, string $message, int $code, ?string $medium = null): array
    {
        $result = ['is_success' => $success, 'message' => $message, 'code' => $code];

        if ($medium) {
            $result['verification_medium'] = $medium;
        }

        return $result;
    }

    public function refCodeExists(mixed $refCode): bool
    {
        return User::where('ref_code', '=', $refCode)->exists();
    }
}
