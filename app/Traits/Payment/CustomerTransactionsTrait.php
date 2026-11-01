<?php

namespace App\Traits\Payment;

use App\Models\LoyaltyPointTransaction;
use App\Models\WalletBonus;
use App\Models\WalletTransaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use App\Services\Customer\UserService;
use App\Services\Payment\WalletBonusService;
use App\Services\System\BusinessSettingService;
use App\Services\Order\ExpenseService;

trait CustomerTransactionsTrait
{
    private const CREDIT_TYPES = [
        'add_fund_by_admin', 'add_fund', 'order_refund', 'loyalty_point',
        'referrer', 'CashBack', 'subscription_refund',
    ];
    private const DEBIT_TYPES = ['order_place', 'trip_booking', 'ride_booking', 'service_booking', 'partial_payment'];
    private const MODEL_RETURNING_TYPES = [
        'loyalty_point', 'trip_booking', 'order_place', 'add_fund_by_admin',
        'referrer', 'partial_payment', 'service_booking',
    ];
    private const POINT_EARNING_TYPES = ['order_place', 'trip_booking', 'service_booking'];
    public function recordWalletTransaction(mixed $userId, float $amount, mixed $transactionType, mixed $reference): mixed
    {
        if (storefront_wallet_disabled_for_user($userId)) {
            return false;
        }

        if (app(BusinessSettingService::class)->value('wallet_status', false) != 1) {
            return false;
        }

        $user = app(UserService::class)->find($userId);
        if (! $user) {
            return false;
        }
        $currentBalance = $user->wallet_balance;

        [$credit, $debit, $adminBonus] = match (true) {
            $transactionType == 'add_fund' => [$amount, 0.0, (float) $this->findWalletBonus($amount)],
            $transactionType == 'loyalty_point' => [(float) $this->loyaltyPointCredit($amount), 0.0, 0.0],
            in_array($transactionType, self::CREDIT_TYPES) => [$amount, 0.0, 0.0],
            in_array($transactionType, self::DEBIT_TYPES) => [0.0, $amount, 0.0],
            default => [0.0, 0.0, 0.0],
        };

        $transaction = new WalletTransaction();
        $transaction->user_id = $user->id;
        $transaction->transaction_id = Str::uuid();
        $transaction->reference = $reference;
        $transaction->transaction_type = $transactionType;

        if ($transactionType == 'add_fund') {
            $transaction->admin_bonus = $adminBonus;
        }

        $transaction->credit = $credit;
        $transaction->debit = $debit;
        $transaction->balance = $currentBalance + $credit + $adminBonus - $debit;
        $transaction->created_at = now();
        $transaction->updated_at = now();
        $user->wallet_balance = $currentBalance + $credit + $adminBonus - $debit;

        try {
            DB::beginTransaction();
            $user->save();
            $transaction->save();

            match (true) {
                $adminBonus > 0 => app(ExpenseService::class)->create([
                    'amount' => $adminBonus,
                    'type' => 'add_fund_bonus',
                    'created_by' => 'admin',
                    'user_id' => $user->id,
                ]),
                $transactionType == 'referrer' => app(ExpenseService::class)->create([
                    'amount' => $amount,
                    'type' => 'referrer',
                    'created_by' => 'admin',
                    'user_id' => $user->id,
                ]),
                default => null,
            };

            DB::commit();

            return in_array($transactionType, self::MODEL_RETURNING_TYPES) ? $transaction : true;
        } catch (\Exception $exception) {
            DB::rollback();

            return false;
        }
    }
    public function recordLoyaltyPointTransaction(mixed $userId, mixed $reference, mixed $amount, mixed $transactionType): mixed
    {
        if (storefront_wallet_disabled_for_user($userId)) {
            return false;
        }

        $settings = app(BusinessSettingService::class)->valuesFor([
            'loyalty_point_status', 'loyalty_point_exchange_rate', 'loyalty_point_item_purchase_point',
        ]);

        if ($settings['loyalty_point_status'] != 1) {
            return false;
        }

        [$credit, $debit] = match (true) {
            in_array($transactionType, self::POINT_EARNING_TYPES) => [(int) ($amount * $settings['loyalty_point_item_purchase_point'] / 100), 0],
            $transactionType == 'point_to_wallet' => [0, $amount],
            default => [0, 0],
        };

        if ($credit <= 0 && $debit <= 0) {
            return false;
        }

        $user = app(UserService::class)->find($userId);
        if (! $user) {
            return false;
        }
        $currentBalance = $user->loyalty_point + $credit - $debit;

        $transaction = new LoyaltyPointTransaction();
        $transaction->user_id = $user->id;
        $transaction->transaction_id = Str::uuid();
        $transaction->reference = $reference;
        $transaction->transaction_type = $transactionType;
        $transaction->balance = $currentBalance;
        $transaction->credit = $credit;
        $transaction->debit = $debit;
        $transaction->created_at = now();
        $transaction->updated_at = now();
        $user->loyalty_point = $currentBalance;

        try {
            DB::beginTransaction();
            $user->save();
            $transaction->save();
            DB::commit();

            return $credit ?? true;
        } catch (\Exception $exception) {
            DB::rollback();

            return false;
        }
    }
    public function findWalletBonus(mixed $addAmount): mixed
    {
        $percentBonus = $this->bestBonusOfType('percentage', $addAmount);
        $flatBonus = $this->bestBonusOfType('amount', $addAmount);

        $percentAmount = $percentBonus && $addAmount >= $percentBonus->minimum_add_amount
            ? min(($addAmount * $percentBonus->bonus_amount) / 100, $percentBonus->maximum_bonus_amount)
            : 0;

        $flatAmount = $flatBonus && $addAmount >= $flatBonus->minimum_add_amount
            ? $flatBonus->bonus_amount
            : 0;

        return max([$percentAmount, $flatAmount]);
    }
    private function loyaltyPointCredit(float $amount): int
    {
        $rate = (int) app(BusinessSettingService::class)->value('loyalty_point_exchange_rate', false);

        return (int) ($amount / ($rate == 0 ? 1 : app(BusinessSettingService::class)->value('loyalty_point_exchange_rate', false)));
    }
    private function bestBonusOfType(string $bonusType, mixed $addAmount): ?WalletBonus
    {
        return app(WalletBonusService::class)->findBestForAmount($bonusType, $addAmount);
    }
}
