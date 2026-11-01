<?php

namespace App\Services\Payment;

use App\Models\ProCustomerBenefitSetting;
use App\Services\BaseService;
use App\Traits\Payment\ProCustomerSubscriptionTrait;

class ProCustomerBenefitSettingService extends BaseService
{
    use ProCustomerSubscriptionTrait;
    private const DISCOUNT = 'discount';
    private const DELIVERY_FEE = 'delivery_fee';
    private const CENTRAL_MODE = 'central';
    public function normalizedBenefits(array $statusFlags): array
    {
        $discountActive = (int) ($statusFlags['discount_status'] ?? 0) === 1;
        $deliveryActive = (int) ($statusFlags['delivery_fee_status'] ?? 0) === 1;
        $couponActive = (int) ($statusFlags['coupon_status'] ?? 0) === 1;
        $setupMode = $statusFlags['discount_setup_mode'] ?? self::CENTRAL_MODE;

        return [
            'active_type' => match (true) {
                $discountActive => self::DISCOUNT,
                $deliveryActive => self::DELIVERY_FEE,
                $couponActive => 'coupon',
                default => null,
            },
            'discount' => $this->discountBenefit($discountActive, $setupMode),
            'delivery_fee' => $this->deliveryFeeBenefit($deliveryActive),
            'coupon' => ['active' => $couponActive ? 1 : 0],
        ];
    }
    public function getSettings(string $type, ?string $lookupKey = null): array
    {
        return ProCustomerBenefitSetting::getSettings($type, $lookupKey);
    }

    private function discountBenefit(bool $active, string $setupMode): array
    {
        $benefit = ['active' => $active ? 1 : 0, 'setup_mode' => $setupMode];
        $configs = $this->settingsByModule(self::DISCOUNT);

        if ($setupMode === self::CENTRAL_MODE) {
            $benefit['config'] = $this->discountConfig($configs[''] ?? []);

            return $benefit;
        }

        $modules = [];

        foreach ($this->proVisibleDiscountModules() as $moduleType) {
            $modules[$moduleType] = $this->discountConfig($configs[$moduleType] ?? []);
        }

        $benefit['modules'] = $modules;

        return $benefit;
    }
    private function deliveryFeeBenefit(bool $active): array
    {
        $configs = $this->settingsByModule(self::DELIVERY_FEE);
        $modules = [];

        foreach ($this->proVisibleDeliveryFeeModules() as $moduleType) {
            $config = $configs[$moduleType] ?? [];
            $modules[$moduleType] = [
                'offer_type' => $config['offer_type'] ?? 'full_free',
                'min_order_status' => (int) ($config['min_order_status'] ?? 0),
                'min_order_amount' => $this->nullableFloat($config['min_order_amount'] ?? null),
                'charge_discount_percentage' => $this->nullableFloat($config['charge_discount'] ?? null),
            ];
        }

        return ['active' => $active ? 1 : 0, 'modules' => $modules];
    }
    private function discountConfig(array $config): array
    {
        return [
            'percentage' => $this->nullableFloat($config['percentage'] ?? null),
            'max_amount' => $this->nullableFloat($config['max_amount'] ?? null),
            'min_order_status' => (int) ($config['min_order_status'] ?? 0),
            'min_order_amount' => $this->nullableFloat($config['min_order_amount'] ?? null),
        ];
    }
    private function settingsByModule(string $benefitType): array
    {
        $configs = [];

        foreach (ProCustomerBenefitSetting::where('benefit_type', $benefitType)->get(['module_type', 'settings']) as $row) {
            $configs[$row->module_type ?? ''] = $row->settings ?? [];
        }

        return $configs;
    }
    private function nullableFloat(mixed $value): ?float
    {
        return $value !== null && $value !== '' ? (float) $value : null;
    }
}
