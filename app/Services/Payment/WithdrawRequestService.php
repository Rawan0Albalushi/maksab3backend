<?php

namespace App\Services\Payment;

use App\Mail\WithdrawRequestMail;
use App\Models\WithdrawRequest;
use App\Services\BaseService;
use Illuminate\Support\Facades\DB;
use App\Traits\Payment\WithdrawalMethodFieldsTrait;
use Illuminate\Pagination\LengthAwarePaginator;
use Modules\Rental\Emails\ProviderWithdrawRequestMail;
use Modules\Service\Emails\ProviderWithdrawRequestMail as ServiceProviderWithdrawRequestMail;
use App\Services\Admin\AdminService;
use App\Services\Payment\WithdrawalMethodService;
use App\Support\Notification\SendNotification;
use Illuminate\Support\Facades\Log;

class WithdrawRequestService extends BaseService
{
    use WithdrawalMethodFieldsTrait;
    public function getDeliveryManList(array $filters = [], array $paginate = []): LengthAwarePaginator
    {
        return $this->ownerList('delivery_man_id', $filters['delivery_man_id'] ?? null, $paginate);
    }
    public function createForDeliveryMan(mixed $wallet, array $data = []): array
    {
        $method = app(WithdrawalMethodService::class)->find($data['withdrawal_method_id'] ?? null);

        if (! $method) {
            return ['status_code' => 403, 'code' => 'id', 'message' => translate('No data found')];
        }

        if (! ($wallet?->balance >= $data['amount'])) {
            return ['status_code' => 403, 'code' => 'amount', 'message' => translate('messages.Insufficient balance')];
        }

        $request = WithdrawRequest::create([
            'delivery_man_id' => $wallet?->delivery_man_id,
            'amount' => $data['amount'],
            'transaction_note' => null,
            'sender_note' => $data['sender_note'] ?? null,
            'withdrawal_method_id' => $data['withdrawal_method_id'],
            'withdrawal_method_fields' => json_encode($this->withdrawalMethodFieldValues($method, $data['fields'] ?? [])),
            'approved' => 0,
        ]);

        $wallet?->increment('pending_withdraw', $data['amount']);
        $this->notifyAdmin($request);

        return ['status_code' => 200, 'message' => translate('messages.Withdraw request placed successfully')];
    }
    public function getVendorList(array $filters = [], array $paginate = []): LengthAwarePaginator
    {
        return $this->ownerList('vendor_id', $filters['vendor_id'] ?? null, $paginate);
    }
    public function createForVendor(mixed $vendor, array $data = []): array
    {
        $method = app(WithdrawalMethodService::class)->find($data['withdrawal_method_id'] ?? null);

        if (! $method) {
            return ['status_code' => 403, 'code' => 'id', 'message' => translate('No data found')];
        }

        $wallet = $vendor?->wallet;

        if (! ($wallet?->balance >= $data['amount'])) {
            return ['status_code' => 403, 'code' => 'amount', 'message' => translate('messages.Insufficient balance')];
        }

        DB::table('withdraw_requests')->insert([
            'vendor_id' => $wallet?->vendor_id,
            'amount' => $data['amount'],
            'transaction_note' => null,
            'withdrawal_method_id' => $data['withdrawal_method_id'],
            'withdrawal_method_fields' => json_encode($this->withdrawalMethodFieldValues($method, $data['fields'] ?? [])),
            'approved' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $wallet?->increment('pending_withdraw', $data['amount']);
        $this->notifyAdminOfVendorRequest($vendor, WithdrawRequest::where('vendor_id', $wallet->vendor_id)->latest()->first());

        return ['status_code' => 200, 'message' => translate('messages.Withdraw request placed successfully')];
    }
    private function ownerList(string $ownerColumn, mixed $ownerId, array $paginate): LengthAwarePaginator
    {
        return WithdrawRequest::with(['method', 'disbursementMethod'])
            ->whereNotNull($ownerColumn)
            ->where($ownerColumn, $ownerId)
            ->latest()
            ->paginate($this->pageSize($paginate), ['*'], 'page', $this->pageNumber($paginate));
    }
    private function notifyAdmin(WithdrawRequest $request): void
    {
        if (! config('mail.status')
            || ! SendNotification::mailTemplateEnabled('dm_withdraw_request_mail_status_admin')
            || ! SendNotification::channelEnabled('admin', 'dm_withdraw_request', 'mail_status')) {
            return;
        }

        try {
            SendNotification::mail(app(AdminService::class)->findSuperAdminEmail(), new WithdrawRequestMail('admin_mail', $request, 'dm'));
        } catch (\Exception $exception) {
            Log::error('payment.withdraw_request_service.notify_admin_failed', [
                'error' => $exception->getMessage(),
                'file' => $exception->getFile().':'.$exception->getLine(),
            ]);
        }
    }
    public static function adminRequestMailSpec(mixed $moduleType): ?array
    {
        return match (true) {
            $moduleType === 'rental' && addon_published_status('Rental') => [
                'canSendRentalMail', 'rental_withdraw_request_mail_status_admin', 'provider_withdraw_request',
                ProviderWithdrawRequestMail::class, 'pending',
            ],
            $moduleType === 'service' && addon_published_status('Service') => [
                'canSendServiceMail', 'service_withdraw_request_mail_status_admin', 'service_provider_withdraw_request',
                ServiceProviderWithdrawRequestMail::class, 'pending',
            ],
            ! in_array($moduleType, ['rental', 'service'], true) => [
                'canSendMail', 'withdraw_request_mail_status_admin', 'withdraw_request',
                WithdrawRequestMail::class, 'admin_mail',
            ],
            default => null,
        };
    }

    private function notifyAdminOfVendorRequest(mixed $vendor, ?WithdrawRequest $request): void
    {
        if (! config('mail.status') || ! $request) {
            return;
        }

        $moduleType = $vendor?->stores[0]?->module?->module_type;
        $adminEmail = app(AdminService::class)->findSuperAdminEmail();

        $spec = self::adminRequestMailSpec($moduleType);

        if (! $spec) {
            return;
        }

        [$gate, $template, $key, $mailable, $status] = $spec;

        try {
            if (SendNotification::$gate($template, 'admin', $key)) {
                SendNotification::mail($adminEmail, new $mailable($status, $request));
            }
        } catch (\Exception $exception) {
            Log::error('payment.withdraw_request_service.notify_admin_of_vendor_request_failed', [
                'error' => $exception->getMessage(),
                'file' => $exception->getFile().':'.$exception->getLine(),
            ]);
        }
    }
}
