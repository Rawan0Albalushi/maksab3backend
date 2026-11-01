<?php

namespace App\Providers;

use App\Services\System\BusinessSettingService;
use App\Services\System\MaintenanceModeService;
use Carbon\CarbonImmutable;
use Carbon\Translator;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\ServiceProvider;

class ConfigServiceProvider extends ServiceProvider
{
    private const BOOT_SETTING_KEYS = [
        'default_pagination',
        'digit_after_decimal_point',
        'local_storage',
        'mail_config',
        'openai_config',
        's3_credential',
        'timeformat',
        'timezone',
    ];

    public function boot(): void
    {
        $settings = $this->bootSettings();

        $this->guard(fn () => $this->applyCalendar());
        $this->guard(fn () => $this->applyMail($settings));
        $this->guard(fn () => $this->applyOrderSettings($settings));
        $this->guard(fn () => $this->applyLocale($settings));
        $this->guard(fn () => $this->applyStorage($settings));
        $this->guard(fn () => $this->applyOpenAi($settings));
        $this->guard(fn () => $this->expireMaintenance());
    }

    private function expireMaintenance(): void
    {
        $maintenance = app(MaintenanceModeService::class);

        if ($maintenance->isDue()) {
            $maintenance->expireIfDue();
        }
    }

    private function bootSettings(): array
    {
        try {
            return app(BusinessSettingService::class)->valuesFor(self::BOOT_SETTING_KEYS);
        } catch (\Throwable) {
            return [];
        }
    }

    private function guard(callable $apply): void
    {
        try {
            $apply();
        } catch (\Throwable $throwable) {
            Log::error('config_service_provider: '.$throwable->getMessage(), [
                'file' => $throwable->getFile(),
                'line' => $throwable->getLine(),
            ]);
        }
    }

    private function applyCalendar(): void
    {
        Translator::get(config('app.locale'))->setTranslations([
            'first_day_of_week' => CarbonImmutable::MONDAY,
            'weekend' => [CarbonImmutable::SUNDAY],
        ]);
    }

    private function applyMail(array $settings): void
    {
        $mail = $this->decoded($settings, 'mail_config');

        if (! $mail) {
            return;
        }

        Config::set('mail.status', (bool) ($mail['status'] ?? 1));

        if (blank($mail['driver'] ?? null)) {
            return;
        }

        Config::set('mail.driver', $mail['driver']);
        Config::set('mail.host', $mail['host'] ?? null);
        Config::set('mail.port', $mail['port'] ?? null);
        Config::set('mail.username', $mail['username'] ?? null);
        Config::set('mail.password', $mail['password'] ?? null);
        Config::set('mail.encryption', $mail['encryption'] ?? null);
        Config::set('mail.from.address', $mail['email_id'] ?? null);
        Config::set('mail.from.name', $mail['name'] ?? null);
        Config::set('mail.sendmail', '/usr/sbin/sendmail -bs');
        Config::set('mail.pretend', false);
    }

    private function applyOrderSettings(array $settings): void
    {
        Config::set('default_pagination', $settings['default_pagination'] ?? DEFAULT_PAGINATION);
        Config::set('round_up_to_digit', $settings['digit_after_decimal_point'] ?? 2);
    }

    private function applyLocale(array $settings): void
    {
        $timezone = $settings['timezone'] ?? null;

        if (is_string($timezone) && in_array($timezone, timezone_identifiers_list(), true)) {
            Config::set('app.timezone', $timezone);
            date_default_timezone_set($timezone);
        }

        Config::set('timeformat', ($settings['timeformat'] ?? null) == '12' ? 'h:i:a' : 'H:i');
    }

    private function applyStorage(array $settings): void
    {
        $credentials = $this->decoded($settings, 's3_credential');

        if (! $credentials) {
            return;
        }

        Config::set('filesystems.disks.s3.key', $credentials['key'] ?? null);
        Config::set('filesystems.disks.s3.secret', $credentials['secret'] ?? null);
        Config::set('filesystems.disks.s3.region', $credentials['region'] ?? null);
        Config::set('filesystems.disks.s3.bucket', $credentials['bucket'] ?? null);
        Config::set('filesystems.disks.s3.url', $credentials['url'] ?? null);
        Config::set('filesystems.disks.s3.endpoint', $credentials['end_point'] ?? null);
    }

    private function applyOpenAi(array $settings): void
    {
        $openAi = $this->decoded($settings, 'openai_config');

        if (! $openAi) {
            return;
        }

        Config::set('openai.api_key', $openAi['OPENAI_API_KEY'] ?? null);
        Config::set('openai.organization', $openAi['OPENAI_ORGANIZATION'] ?? null);
        Config::set('ai.providers.openai.key', $openAi['OPENAI_API_KEY'] ?? null);
    }

    private function decoded(array $settings, string $key): ?array
    {
        $value = $settings[$key] ?? null;

        if (is_array($value)) {
            return $value;
        }

        $decoded = is_string($value) ? json_decode($value, true) : null;

        return is_array($decoded) ? $decoded : null;
    }
}
