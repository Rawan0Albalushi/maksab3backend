<?php

namespace App\Http\Resources\Vendor\Profile;

use App\CentralLogics\Helpers;
use App\Http\Resources\BaseResource;
use Illuminate\Http\Request;

class ProfileResource extends BaseResource
{
    private const DROPPED = ['orders', 'rating', 'todaysorders', 'this_week_orders', 'this_month_orders', 'wallet', 'storage'];

    private const STORE_DROPPED = ['storage', 'translations','store_sub','store_sub_update_application'];

    public function __construct(mixed $resource, private readonly array $extras = [])
    {
        parent::__construct($resource);
    }

    public function toArray(Request $request): array
    {
        $vendor = $this->resource;
        $wallet = $vendor->wallet;
        $walletEarning = round($wallet?->total_earning - ($wallet?->total_withdrawn + $wallet?->pending_withdraw), 8);
        $balance = max((float) ($wallet?->balance ?? 0), 0);

        $data = $vendor->attributesToArray();
        $appends = [];

        foreach ($vendor->getAppends() as $append) {
            $appends[$append] = $data[$append] ?? null;
            unset($data[$append]);
        }

        $appends['image_full_url'] = $vendor->image_full_url;

        foreach ($this->extras['stats'] ?? [] as $key => $value) {
            $data[$key] = $value;
        }

        $data['member_since_days'] = (int) $vendor->created_at->diffInDays();
        $data['cash_in_hands'] = (float) ($wallet?->collected_cash ?? 0);
        $data['balance'] = $balance;

        if (! ($this->extras['is_service_provider'] ?? false)) {
            $data['total_earning'] = (float) ($wallet?->total_earning ?? 0);
        }

        $data['Payable_Balance'] = (float) ($wallet?->balance < 0 ? abs($wallet?->balance) : 0);
        $data['withdraw_able_balance'] = (float) $walletEarning;
        $data['adjust_able'] = ($wallet?->balance > 0 && $wallet?->collected_cash > 0)
            || ($wallet?->collected_cash != 0 && $walletEarning != 0);
        $data['show_pay_now_button'] = $this->showPayNow($wallet);
        $data['pending_withdraw'] = (float) ($wallet?->pending_withdraw ?? 0);
        $data['total_withdrawn'] = (float) ($wallet?->total_withdrawn ?? 0);

        [$data['dynamic_balance'], $data['dynamic_balance_type']] = $this->dynamicBalance($wallet, $walletEarning, $balance);
        [$data['over_flow_warning'], $data['over_flow_block_warning']] = $this->overflowWarnings($wallet);

        $data['stores'] = $this->storePayload();
        $data['translations'] = $this->extras['translations'] ?? null;

        if (array_key_exists('roles', $this->extras)) {
            $data['roles'] = $this->extras['roles'];
            $data['employee_info'] = $this->extras['employee_info'];
        }

        $data['subscription_transactions'] = (bool) ($this->extras['has_subscription_transactions'] ?? false);

        if ($this->extras['subscription'] ?? null) {
            $data['subscription'] = $this->extras['subscription'];
            $data['subscription_other_data'] = $this->extras['subscription_other_data'];
        }

        $data['out_of_stock_count'] = (int) ($this->extras['out_of_stock_count'] ?? 0);

        foreach ($appends as $key => $value) {
            $data[$key] = $value;
        }

        foreach (self::DROPPED as $relation) {
            unset($data[$relation]);
        }

        return $data + array_diff_key($vendor->relationsToArray(), array_flip(self::DROPPED));
    }

    private function storePayload(): ?array
    {
        $store = $this->extras['store'] ?? null;

        if (! $store) {
            return null;
        }

        return array_diff_key($store, array_flip(self::STORE_DROPPED));
    }

    private function showPayNow(mixed $wallet): bool
    {
        $minimum = Helpers::get_business_settings('min_amount_to_pay_store', false) ?? 0;

        return $minimum <= $wallet?->collected_cash
            && (Helpers::get_business_settings('digital_payment')['status'] ?? 0) == 1
            && $wallet?->collected_cash > $wallet?->balance;
    }

    private function dynamicBalance(mixed $wallet, float $walletEarning, float $balance): array
    {
        if ($balance <= 0) {
            return [(float) abs($wallet?->collected_cash ?? 0), translate('Payable balance')];
        }

        return [
            (float) abs($walletEarning),
            $wallet?->balance == $walletEarning
                ? translate('Withdrawable balance')
                : translate('messages.balance').' '.translate('Unadjusted'),
        ];
    }

    private function overflowWarnings(mixed $wallet): array
    {
        $hasCash = $wallet?->collected_cash > 0;
        $overflowEnabled = Helpers::get_business_settings('cash_in_hand_overflow_store', false);
        $overflowAmount = Helpers::get_business_settings('cash_in_hand_overflow_store_amount', false);
        $threshold = $overflowAmount - (($overflowAmount * 10) / 100);

        return [
            (bool) ($hasCash && $overflowEnabled && $wallet?->balance < 0 && $threshold <= abs($wallet?->collected_cash)),
            (bool) ($hasCash && $overflowEnabled && $wallet?->balance < 0 && $overflowAmount < abs($wallet?->collected_cash)),
        ];
    }
}
