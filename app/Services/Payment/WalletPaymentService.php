<?php

namespace App\Services\Payment;

use App\Models\WalletPayment;
use App\Services\BaseService;
use App\Traits\Payment\PaymentRedirectLinkTrait;
use App\Services\Customer\UserService;
use App\Services\System\BusinessSettingService;

class WalletPaymentService extends BaseService
{
    use PaymentRedirectLinkTrait;

    private const SUCCESS_HOOK = 'wallet_success';

    private const FAILURE_HOOK = 'wallet_failed';

    private const RECEIVER_ID = '100';

    private const ATTRIBUTE = 'wallet_payments';

    public function digitalPaymentEnabled(): bool
    {
        return app(BusinessSettingService::class)->digitalPaymentEnabled();
    }

    public function create(array $data): ?string
    {
        $customer = app(UserService::class)->find($data['user_id'] ?? null);

        if (! $customer) {
            return null;
        }

        $wallet = new WalletPayment();
        $wallet->user_id = $customer->id;
        $wallet->amount = $data['amount'];
        $wallet->payment_status = 'pending';
        $wallet->payment_method = $data['payment_method'];
        $wallet->save();

        return $this->paymentRedirectLink($customer, [
            'success_hook' => self::SUCCESS_HOOK,
            'failure_hook' => self::FAILURE_HOOK,
            'receiver_id' => self::RECEIVER_ID,
            'attribute' => self::ATTRIBUTE,
            'attribute_id' => $wallet->id,
            'amount' => $data['amount'],
            'payment_method' => $data['payment_method'],
            'payment_platform' => $data['payment_platform'] ?? null,
            'callback' => $data['callback'] ?? null,
        ]);
    }
}
