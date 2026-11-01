<?php

namespace App\Traits\Payment;

use App\Services\Payment\PaymentLinkService;
use App\CentralLogics\Helpers;
use App\Library\Payer;
use App\Library\Payment as PaymentInfo;
use App\Library\Receiver;
use App\Services\System\BusinessSettingService;
use App\Services\System\CurrencyService;

trait PaymentRedirectLinkTrait
{
    protected function paymentRedirectLink(mixed $customer, array $payment): ?string
    {
        return PaymentLinkService::generateLink(
            $this->paymentPayer($customer, $payment),
            new PaymentInfo(
                success_hook: $payment['success_hook'],
                failure_hook: $payment['failure_hook'],
                currency_code: app(CurrencyService::class)->code(),
                payment_method: $payment['payment_method'] ?? null,
                payment_platform: $payment['payment_platform'] ?? null,
                payer_id: $payment['payer_id'] ?? $customer->id,
                receiver_id: $payment['receiver_id'],
                additional_data: $this->paymentAdditionalData($payment['additional_data'] ?? []),
                payment_amount: $payment['amount'],
                external_redirect_link: $payment['callback'] ?? null,
                attribute: $payment['attribute'],
                attribute_id: $payment['attribute_id'],
            ),
            new Receiver($payment['receiver_name'] ?? 'receiver_name', 'example.png')
        );
    }

    private function paymentPayer(mixed $customer, array $payment): Payer
    {
        return new Payer(
            $payment['payer_name'] ?? ((data_get($customer, 'f_name') ?? '') . ' ' . (data_get($customer, 'l_name') ?? '')),
            $payment['payer_email'] ?? data_get($customer, 'email'),
            $payment['payer_phone'] ?? data_get($customer, 'phone'),
            ''
        );
    }

    private function paymentAdditionalData(array $extra): array
    {
        $logo = app(BusinessSettingService::class)->findByKey('logo');

        return array_merge([
            'business_name' => app(BusinessSettingService::class)->value('business_name'),
            'business_logo' => Helpers::get_full_url('business', $logo?->value, $logo?->storage[0]?->value ?? 'public'),
        ], $extra);
    }
}
