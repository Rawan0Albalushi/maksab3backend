<?php

namespace App\Services\Payment;

use App\CentralLogics\Helpers;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use App\Models\Setting;
use App\Services\BaseService;

class SettingService extends BaseService
{
    public function hasActiveSmsGateway(): bool
    {
        return Setting::whereJsonContains('live_values->status', '1')
            ->where('settings_type', 'sms_config')
            ->exists();
    }

    public function smsGatewayConfigs(): array
    {
        return Setting::where('settings_type', 'sms_config')
            ->pluck('live_values', 'key_name')
            ->all();
    }

    public function getActiveGateways(): array
    {

        if (! Schema::hasTable('addon_settings')) {
            return [];
        }

        $digital_payment = Helpers::get_business_settings('digital_payment');
        if ($digital_payment && $digital_payment['status'] == 0) {
            return [];
        }

        $published_status = 0;
        $payment_published_status = config('get_payment_publish_status');
        if (isset($payment_published_status[0]['is_published'])) {
            $published_status = $payment_published_status[0]['is_published'];
        }

        if ($published_status == 1) {
            $methods = DB::table('addon_settings')->where('is_active', 1)->where('settings_type', 'payment_config')->get();
            $env = env('APP_ENV') == 'live' ? 'live' : 'test';
            $credentials = $env.'_values';

        } else {
            $methods = DB::table('addon_settings')->where('is_active', 1)->whereIn('settings_type', ['payment_config'])->whereIn('key_name', ['ssl_commerz', 'paypal', 'stripe', 'razor_pay', 'senang_pay', 'paytabs', 'paystack', 'paymob_accept', 'paytm', 'flutterwave', 'liqpay', 'bkash', 'mercadopago'])->get();
            $env = env('APP_ENV') == 'live' ? 'live' : 'test';
            $credentials = $env.'_values';

        }

        $data = [];
        foreach ($methods as $method) {
            $credentialsData = json_decode($method->$credentials);
            $additional_data = json_decode($method->additional_data);
            $data[] = [
                'gateway' => $method->key_name,
                'gateway_title' => $additional_data?->gateway_title,
                'gateway_image' => $additional_data?->gateway_image,
                'gateway_image_full_url' => Helpers::get_full_url('payment_modules/gateway_image', $additional_data?->gateway_image, $additional_data?->storage ?? 'public'),
            ];
        }

        return $data;

    }
}
