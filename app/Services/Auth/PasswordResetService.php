<?php

namespace App\Services\Auth;

use App\Services\BaseService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use App\Support\Notification\SendNotification;
use App\Support\Notification\Sms;

class PasswordResetService extends BaseService
{
    private const TABLE = 'password_resets';
    private const OTP_INTERVAL_SECONDS = 60;
    private const MAX_OTP_HIT = 5;
    private const MAX_OTP_HIT_SECONDS = 60;
    private const TEMP_BLOCK_SECONDS = 600;
    private const TOKEN_LIFETIME_MINUTES = 60;
    private const STOREFRONT_CONTEXT = 'Modules\\Builder\\Services\\StorefrontContext';
    public function getTenantScope(): array
    {
        if (! class_exists(self::STOREFRONT_CONTEXT) || ! app()->bound(self::STOREFRONT_CONTEXT)) {
            return ['tenant_id' => 0, 'sub_tenant_id' => 0];
        }

        $scope = app(self::STOREFRONT_CONTEXT)->getScope();

        return [
            'tenant_id' => (int) ($scope->tenantId ?? 0),
            'sub_tenant_id' => (int) ($scope->subTenantId ?? 0),
        ];
    }
    public function findByOwner(array $owner): ?object
    {
        return $this->ownerQuery($owner)->first();
    }
    public function secondsUntilResend(array $owner): int
    {
        $row = $this->findByOwner($owner);

        if (! $row) {
            return 0;
        }

        $elapsed = Carbon::parse($row->created_at)->diffInSeconds();

        return $elapsed < self::OTP_INTERVAL_SECONDS ? (int) round(self::OTP_INTERVAL_SECONDS - $elapsed) : 0;
    }
    public function issueToken(array $owner, string $token): void
    {
        DB::table(self::TABLE)->updateOrInsert($owner, [
            'token' => $token,
            'created_at' => now(),
        ]);
    }
    public function tokenMatches(array $owner, mixed $token): bool
    {
        $row = $this->ownerQuery($owner)->where('token', $token)->first();

        return $row !== null && ! $this->expired($row);
    }
    public function expired(object $row): bool
    {
        return Carbon::parse($row->created_at)->diffInMinutes(Carbon::now()) >= self::TOKEN_LIFETIME_MINUTES;
    }
    public function consumeToken(array $owner, mixed $token): void
    {
        $this->ownerQuery($owner)->where('token', $token)->delete();
    }
    public function registerFailedAttempt(array $owner): ?array
    {
        $row = $this->findByOwner($owner);

        if ($row) {
            $blocked = $this->blockRemainingSeconds($row);

            if ($blocked !== null) {
                return ['code' => 'otp_block_time', 'seconds' => $blocked];
            }

            if ($this->blockExpired($row)) {
                DB::table(self::TABLE)->updateOrInsert($owner, [
                    'otp_hit_count' => 0,
                    'is_temp_blocked' => 0,
                    'temp_block_time' => null,
                    'created_at' => now(),
                ]);
            }

            if ($this->shouldBlock($row)) {
                DB::table(self::TABLE)->updateOrInsert($owner, [
                    'is_temp_blocked' => 1,
                    'temp_block_time' => now(),
                    'created_at' => now(),
                ]);

                return ['code' => 'otp_temp_blocked'];
            }
        }

        DB::table(self::TABLE)->updateOrInsert($owner, [
            'otp_hit_count' => DB::raw('otp_hit_count + 1'),
            'created_at' => now(),
            'temp_block_time' => null,
        ]);

        return null;
    }
    public function dispatchOtp(array $spec): array
    {
        $mail = (bool) config('mail.status') && $this->sendMail(
            $spec['mail_status_key'],
            $spec['email'],
            $spec['mailable'],
            [$spec['actor'], $spec['notification_key']]
        );

        $sms = SendNotification::channelEnabled($spec['actor'], $spec['notification_key'], 'sms_status')
            && $this->sendSms($spec['phone'], $spec['token']);

        return ['mail' => $mail, 'sms' => $sms];
    }
    public function sendSms(mixed $phone, mixed $token): bool
    {
        return Sms::delivered($phone, $token);
    }
    public function sendMail(?string $statusKey, mixed $email, callable $mailable, ?array $notification = null): bool
    {
        try {
            if (blank($email) || ($statusKey && ! SendNotification::mailTemplateEnabled($statusKey))) {
                return false;
            }

            if ($notification && ! SendNotification::channelEnabled($notification[0], $notification[1], 'mail_status')) {
                return false;
            }

            SendNotification::mail($email, $mailable());

            return true;
        } catch (\Throwable) {
            return false;
        }
    }
    private function ownerQuery(array $owner): mixed
    {
        return DB::table(self::TABLE)->where($owner);
    }
    private function blockRemainingSeconds(object $row): ?int
    {
        if (! isset($row->temp_block_time)) {
            return null;
        }

        $elapsed = Carbon::parse($row->temp_block_time)->diffInSeconds();

        return $elapsed <= self::TEMP_BLOCK_SECONDS ? (int) round(self::TEMP_BLOCK_SECONDS - $elapsed) : null;
    }
    private function blockExpired(object $row): bool
    {
        return $row->is_temp_blocked == 1
            && Carbon::parse($row->created_at)->diffInSeconds() >= self::MAX_OTP_HIT_SECONDS;
    }
    private function shouldBlock(object $row): bool
    {
        return $row->otp_hit_count >= self::MAX_OTP_HIT
            && Carbon::parse($row->created_at)->diffInSeconds() < self::MAX_OTP_HIT_SECONDS
            && $row->is_temp_blocked == 0;
    }

    public function tokenExists(mixed $code): bool
    {
        return DB::table('password_resets')->where('token', '=', $code)->exists();
    }
}
