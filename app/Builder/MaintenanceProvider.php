<?php

namespace App\Builder;

use App\CentralLogics\Helpers;
use App\Models\BusinessSetting;
use App\Models\DataSetting;
use Carbon\Carbon;
use App\Support\Cache\ApiCache;
use Modules\Builder\Contracts\MaintenanceProvider as MaintenanceProviderContract;
use Modules\Builder\ValueObjects\MaintenanceState;
use Modules\Builder\ValueObjects\StorefrontScope;

class MaintenanceProvider implements MaintenanceProviderContract
{
    private const SYSTEM_KEY = 'vendor_storefront';

    public function state(?StorefrontScope $scope = null): MaintenanceState
    {
        if (! (int) (Helpers::get_business_settings('maintenance_mode') ?? 0)) {
            return MaintenanceState::inactive();
        }

        $data = $this->maintenanceData();

        $systems = $this->decode($data['maintenance_system_setup'] ?? null, []);
        if (! \in_array(self::SYSTEM_KEY, (array) $systems, true)) {
            return MaintenanceState::inactive();
        }

        $duration = $this->decode($data['maintenance_duration_setup'] ?? null, []);
        if (! $this->withinWindow($duration)) {
            return MaintenanceState::inactive();
        }

        $message = $this->decode($data['maintenance_message_setup'] ?? null, []);

        return new MaintenanceState(
            active:  true,
            title:   $message['maintenance_message'] ?? null,
            body:    $message['message_body'] ?? null,
            endDate: $this->endDate($duration),
            phone:   ! empty($message['business_number'])
                ? (Helpers::get_business_settings('phone', false) ?: null)
                : null,
            email:   ! empty($message['business_email'])
                ? (Helpers::get_business_settings('email', false) ?: null)
                : null,
        );
    }

    /**
     * The three maintenance DataSetting rows, JSON-decoded, keyed by setting
     * key. Cached forever (same key the host config endpoint uses); the admin
     * save path forgets this key, so it stays fresh.
     *
     * @return array<string,mixed>
     */
    private function maintenanceData(): array
    {
        return ApiCache::remember('data_settings', 'maintenance_mode', function () {
            return DataSetting::where('type', 'maintenance_mode')
                ->whereIn('key', [
                    'maintenance_system_setup',
                    'maintenance_duration_setup',
                    'maintenance_message_setup',
                ])
                ->pluck('value', 'key')
                ->toArray();
        });
    }

    private function withinWindow(array $duration): bool
    {
        if (($duration['maintenance_duration'] ?? null) === 'until_change') {
            return true;
        }

        $start = $duration['start_date'] ?? null;
        $end   = $duration['end_date'] ?? null;
        if (! $start || ! $end) {
            return false;
        }

        try {
            return Carbon::now()->between(Carbon::parse($start), Carbon::parse($end));
        } catch (\Throwable) {
            return false;
        }
    }

    private function endDate(array $duration): ?string
    {
        if (($duration['maintenance_duration'] ?? null) === 'until_change') {
            return null;
        }

        $end = $duration['end_date'] ?? null;
        if (! $end) {
            return null;
        }

        try {
            return Carbon::parse($end)->toIso8601String();
        } catch (\Throwable) {
            return null;
        }
    }

    private function decode(mixed $value, mixed $fallback): mixed
    {
        if (\is_array($value)) {
            return $value;
        }
        if (\is_string($value)) {
            $decoded = \json_decode($value, true);
            return \is_null($decoded) ? $fallback : $decoded;
        }
        return $fallback;
    }
}
