<?php

namespace App\Services\Payment;

use App\Mail\AddFundToWallet;
use App\Models\LoyaltyPointTransaction;
use App\Services\BaseService;
use App\Traits\Payment\CustomerTransactionsTrait;
use Illuminate\Pagination\LengthAwarePaginator;
use App\Support\Notification\SendNotification;
use App\Services\System\BusinessSettingService;
use App\Services\Customer\UserService;

class LoyaltyPointTransactionService extends BaseService
{
    use CustomerTransactionsTrait;

    private const LIST_COLUMNS = [
        'id', 'user_id', 'credit', 'debit', 'transaction_type', 'reference', 'created_at',
    ];

    private const TRANSFER_TYPE = 'point_to_wallet';

    public function getList(array $filters = [], array $paginate = []): LengthAwarePaginator
    {
        return LoyaltyPointTransaction::where('user_id', $filters['user_id'] ?? null)
            ->latest()
            ->select(self::LIST_COLUMNS)
            ->paginate($this->pageSize($paginate), ['*'], 'page', $this->pageNumber($paginate));
    }

    public function canTransfer(mixed $balance, mixed $point): bool
    {
        return $balance > 0 && $point >= $this->minimumTransferablePoint() && $point <= $balance;
    }

    public function transferToWallet(mixed $userId, mixed $point, mixed $reference, mixed $email): bool
    {
        try {
            $walletTransaction = $this->recordWalletTransaction($userId, $point, 'loyalty_point', $reference);
            $this->recordLoyaltyPointTransaction($userId, $walletTransaction->transaction_id, $point, self::TRANSFER_TYPE);
            app(UserService::class)->notifyFundAdded($userId);

            if (SendNotification::canSendMail('add_fund_mail_status_user', 'store', 'customer_add_fund_to_wallet')) {
                SendNotification::mail($email, new AddFundToWallet($walletTransaction));
            }

            return true;
        } catch (\Exception $exception) {
            return false;
        }
    }

    private function minimumTransferablePoint(): int
    {
        return (int) app(BusinessSettingService::class)->value('loyalty_point_minimum_point', false);
    }
}
