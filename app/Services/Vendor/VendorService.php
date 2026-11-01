<?php

namespace App\Services\Vendor;

use App\Mail\StoreRegistration;
use App\Mail\VendorSelfRegistration;
use App\Models\Module;
use App\Models\Vendor;
use App\Traits\Auth\SetsHashedPasswordTrait;
use App\Services\BaseService;
use App\Traits\Payment\PaymentRedirectLinkTrait;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Rental\Emails\ProviderRegistration;
use Modules\Rental\Emails\ProviderSelfRegistration;
use Modules\Service\Emails\ProviderRegistration as ServiceProviderRegistration;
use Modules\Service\Emails\ProviderSelfRegistration as ServiceProviderSelfRegistration;
use App\Services\Store\StoreService;
use App\Services\Admin\AdminService;
use App\Services\Order\OrderService;
use App\Services\Payment\SubscriptionTransactionService;
use App\Services\Payment\SubscriptionBillingAndRefundHistoryService;
use App\Services\Store\StoreWalletService;
use App\Services\Vendor\VendorEmployeeService;
use App\Support\Notification\SendNotification;
use App\Support\Storage\FileStorage;
use Illuminate\Support\Facades\Log;

class VendorService extends BaseService
{
    use SetsHashedPasswordTrait;
    use PaymentRedirectLinkTrait;
    private const BLOCKING_ORDER_STATUSES = ['pending', 'accepted', 'confirmed', 'processing', 'handover', 'picked_up'];
    private const SERVICE_EARNING_TABLE = 'booking_transactions';
    public function contactKey(mixed $vendor): array
    {
        return ['email' => $vendor?->email];
    }
    public function registerWithStore(array $validated, array $storeInput, array $translations, mixed $businessPlan, mixed $packageId): array
    {
        return DB::transaction(function () use ($validated, $storeInput, $translations, $businessPlan, $packageId) {
            $vendor = $this->createFromRegistration($validated);
            $store = app(StoreService::class)->createFromVendorRegistration($vendor, $storeInput, $translations);

            return [$vendor, $store, app(StoreService::class)->setBusinessModel($store, $businessPlan, $packageId)];
        });
    }
    public function findFirebaseTarget(mixed $id): array
    {
        $vendor = $this->findWithStores($id);
        $storeId = $vendor?->stores[0]->id ?? null;

        return [
            'token' => $vendor?->firebase_token,
            'web_topic' => $storeId ? "store_panel_{$storeId}_message" : null,
        ];
    }
    public function findWithStore(mixed $id): mixed
    {
        return $this->find($id, ['store', 'stores']);
    }
    public function findWithStores(mixed $id): mixed
    {
        return $this->find($id, ['stores']);
    }
    public function orderStats(Vendor $vendor, bool $isServiceProvider, mixed $storeId): array
    {
        return $isServiceProvider ? $this->serviceProviderStats($storeId) : $this->storeStats($vendor);
    }
    public function login(array $credentials, string $type): ?object
    {
        $guard = $type == 'owner' ? 'vendor' : 'vendor_employee';

        if (! in_array($type, ['owner', 'employee']) || ! auth($guard)->attempt($credentials)) {
            return null;
        }

        return $type == 'owner'
            ? $this->findBy('email', $credentials['email'])
            : app(VendorEmployeeService::class)->findByEmail($credentials['email']);
    }
    public function generateAuthToken(object $account): string
    {
        do {
            $token = Str::random(120);
        } while (Vendor::where('auth_token', $token)->where('email', '!=', $account->email)->exists());

        return $token;
    }
    public function saveAuthToken(object $account, string $token): void
    {
        $account->auth_token = $token;
        $account->save();
    }
    public function createFromRegistration(array $data): Vendor
    {
        $vendor = new Vendor();
        $vendor->f_name = $data['f_name'];
        $vendor->l_name = $data['l_name'] ?? null;
        $vendor->email = $data['email'];
        $vendor->phone = $data['phone'];
        $vendor->password = bcrypt($data['password']);
        $vendor->status = null;
        $vendor->save();

        return $vendor;
    }
    public function notifyRegistration(Vendor $vendor, ?Module $module, mixed $email): void
    {
        if (! config('mail.status')) {
            return;
        }

        $name = $vendor->f_name.' '.$vendor->l_name;
        $type = $module?->module_type;
        $admin = app(AdminService::class)->findSuperAdmin();
        $adminEmail = $admin?->getRawOriginal('email');

        $spec = match (true) {
            $type != 'rental' && $type != 'service' => [
                'gate' => 'canSendMail',
                'provider' => ['registration_mail_status_store', 'store', 'store_registration', VendorSelfRegistration::class],
                'admin' => ['store_registration_mail_status_admin', 'admin', 'store_self_registration', StoreRegistration::class],
            ],
            $type == 'rental' && addon_published_status('Rental') => [
                'gate' => 'canSendRentalMail',
                'provider' => ['rental_registration_mail_status_provider', 'provider', 'provider_registration', ProviderSelfRegistration::class],
                'admin' => ['rental_provider_registration_mail_status_admin', 'admin', 'provider_self_registration', ProviderRegistration::class],
            ],
            $type == 'service' && addon_published_status('Service') => [
                'gate' => 'canSendServiceMail',
                'provider' => ['service_registration_mail_status_provider', 'provider', 'service_provider_registration', ServiceProviderSelfRegistration::class],
                'admin' => ['service_provider_registration_mail_status_admin', 'admin', 'service_provider_self_registration', ServiceProviderRegistration::class],
            ],
            default => null,
        };

        if (! $spec) {
            return;
        }

        try {
            $gate = $spec['gate'];

            foreach (['provider' => $email, 'admin' => $adminEmail] as $recipient => $to) {
                [$template, $audience, $key, $mailable] = $spec[$recipient];

                if (SendNotification::$gate($template, $audience, $key)) {
                    SendNotification::mail($to, new $mailable('pending', $name));
                }
            }
        } catch (\Exception $exception) {
            Log::error('vendor.vendor_service.notify_registration_failed', [
                'error' => $exception->getMessage(),
                'file' => $exception->getFile().':'.$exception->getLine(),
            ]);
        }
    }
    public function findByEmail(mixed $email): ?Vendor
    {
        return $email ? $this->findBy('email', $email) : null;
    }
    public function hasSubscriptionTransactions(mixed $storeId): bool
    {
        return app(SubscriptionTransactionService::class)->countForStore($storeId) > 0;
    }
    public function subscriptionSummary(mixed $store): ?array
    {
        $application = $store?->store_sub_update_application;

        if (! isset($application)) {
            return null;
        }

        return [
            'total_bill' => (float) $application->package?->price * ($application->total_package_renewed + 1),
            'max_product_uploads' => (int) $this->remainingUploads($store, $application),
            'pending_bill' => app(SubscriptionBillingAndRefundHistoryService::class)->pendingBillTotal($store->id),
        ];
    }
    public function outOfStockCount(mixed $store): int
    {
        if ($store?->module->module_type === 'food') {
            return 0;
        }

        $warningStock = $store?->storeConfig?->show_low_stock_count ? $store?->storeConfig?->minimum_stock_for_warning : 0;

        return $warningStock > 0 ? (int) $store->items()->where('stock', '<=', $warningStock)->count() : 0;
    }
    public function updateProfile(Vendor $vendor, array $data): Vendor
    {
        $vendor->f_name = $data['f_name'];
        $vendor->l_name = $data['l_name'];
        $vendor->phone = $data['phone'];
        $vendor->image = isset($data['image'])
            ? FileStorage::update('vendor/', $vendor->image, $data['image'])
            : $vendor->image;
        $vendor->password = ($data['password'] ?? null) !== null && strlen($data['password']) > 5
            ? bcrypt($data['password'])
            : $vendor->password;
        $vendor->updated_at = now();
        $vendor->save();

        return $vendor;
    }
    public function updateFirebaseToken(array $filters, string $token): void
    {
        ($filters['vendor_type'] ?? null) === 'owner'
            ? Vendor::where('id', $filters['vendor_id'])->update(['firebase_token' => $token])
            : app(VendorEmployeeService::class)->updateFirebaseToken($filters['vendor_employee_id'], $token);
    }
    public function deleteAccount(Vendor $vendor): array
    {
        $store = $vendor->stores[0];

        if (app(OrderService::class)->countByStatuses(['store_id' => $store->id], self::BLOCKING_ORDER_STATUSES)) {
            return ['status_code' => 203, 'code' => 'on-going', 'message' => translate('messages.Please complete your ongoing and accepted orders')];
        }

        if ($vendor->wallet && $vendor->wallet->collected_cash > 0) {
            return ['status_code' => 203, 'code' => 'hand_in_cash', 'message' => translate('messages.You have cash in hand, you have to pay the due to delete your account')];
        }

        FileStorage::delete('vendor/', $vendor->image);
        FileStorage::delete('store/', $store->logo);
        FileStorage::delete('store/cover/', $store->cover_photo);

        foreach ($store->deliverymen as $deliveryMan) {
            FileStorage::delete('delivery-man/', $deliveryMan['image']);

            foreach (json_decode($deliveryMan['identity_image'], true) as $image) {
                FileStorage::delete('delivery-man/', $image);
            }
        }

        $store->deliverymen()->delete();
        $vendor->stores()->delete();
        $vendor->userinfo?->delete();
        $vendor->delete();

        return ['status_code' => 200];
    }
    public function collectCashPaymentLink(Vendor $vendor, mixed $store, array $data): ?string
    {
        return $this->paymentRedirectLink($vendor, [
            'success_hook' => 'collect_cash_success',
            'failure_hook' => 'collect_cash_fail',
            'payment_method' => $data['payment_gateway'] ?? null,
            'payment_platform' => 'app',
            'receiver_id' => '100',
            'receiver_name' => 'Admin',
            'payer_name' => $store->name,
            'payer_email' => $store->email,
            'payer_phone' => $store->phone,
            'amount' => $data['amount'] ?? null,
            'callback' => $data['callback'] ?? null,
            'attribute' => 'store_collect_cash_payments',
            'attribute_id' => $store->vendor_id,
        ]);
    }
    public function adjustWallet(mixed $vendorId): array
    {
        $wallet = app(StoreWalletService::class)->findOrNewForVendor($vendorId);
        $walletEarning = $wallet->total_earning - ($wallet->total_withdrawn + $wallet->pending_withdraw);

        if ($wallet->collected_cash == 0 || $walletEarning == 0 || $walletEarning == $wallet->balance) {
            return ['status_code' => 201, 'message' => translate('Already adjusted')];
        }

        $isPartial = $wallet->collected_cash - $walletEarning > 0;
        $amount = $isPartial ? $walletEarning : $wallet->collected_cash;

        $wallet->total_withdrawn = $wallet->total_withdrawn + $amount;
        $wallet->collected_cash = $isPartial ? $wallet->collected_cash - $walletEarning : 0;
        $wallet->save();

        DB::table('withdraw_requests')->insert([
            'vendor_id' => $vendorId,
            'amount' => $amount,
            'transaction_note' => $isPartial ? 'Store_wallet_adjustment_partial' : 'Store_wallet_adjustment_full',
            'withdrawal_method_id' => null,
            'withdrawal_method_fields' => null,
            'approved' => 1,
            'type' => 'adjustment',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return ['status_code' => 200, 'message' => translate('messages.Store wallet adjustment successfull')];
    }
    public function find(mixed $id, array $relations = []): ?Vendor
    {
        return Vendor::with($relations)->find($id);
    }
    private function findBy(string $column, mixed $value): ?Vendor
    {
        return Vendor::where($column, $value)->first();
    }
    private function storeStats(Vendor $vendor): array
    {
        return [
            'order_count' => $vendor->orders()->where('order_type', '!=', 'pos')->whereNotIn('order_status', ['canceled', 'failed'])->count(),
            'todays_order_count' => $vendor->todaysorders()->where('order_type', '!=', 'pos')->whereIn('order_status', ['refunded', 'delivered'])->count(),
            'this_week_order_count' => $vendor->this_week_orders()->where('order_type', '!=', 'pos')->whereIn('order_status', ['refunded', 'delivered'])->count(),
            'this_month_order_count' => $vendor->this_month_orders()->where('order_type', '!=', 'pos')->whereIn('order_status', ['refunded', 'delivered'])->count(),
            'todays_earning' => (float) $vendor->todays_earning()->sum('store_amount'),
            'this_week_earning' => (float) $vendor->this_week_earning()->sum('store_amount'),
            'this_month_earning' => (float) $vendor->this_month_earning()->sum('store_amount'),
        ];
    }
    private function serviceProviderStats(mixed $storeId): array
    {
        $bookings = fn () => DB::table('service_bookings')->where('provider_id', $storeId);
        $earning = fn () => DB::table(self::SERVICE_EARNING_TABLE)
            ->join('service_bookings', 'service_bookings.id', '=', 'booking_transactions.booking_id')
            ->where('booking_transactions.provider_id', $storeId);

        return [
            'order_count' => $bookings()->whereNotIn('booking_status', ['canceled', 'payment_failed'])->count(),
            'todays_order_count' => $bookings()->where('booking_status', 'completed')->whereDate('created_at', now())->count(),
            'this_week_order_count' => $bookings()->where('booking_status', 'completed')->whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()])->count(),
            'this_month_order_count' => $bookings()->where('booking_status', 'completed')->whereMonth('created_at', now()->month)->whereYear('created_at', now()->year)->count(),
            'total_earning' => (float) $earning()->sum('booking_transactions.store_amount'),
            'todays_earning' => (float) $earning()->whereDate('booking_transactions.created_at', now())->sum('booking_transactions.store_amount'),
            'this_week_earning' => (float) $earning()->whereBetween('booking_transactions.created_at', [now()->startOfWeek(), now()->endOfWeek()])->sum('booking_transactions.store_amount'),
            'this_month_earning' => (float) $earning()->whereMonth('booking_transactions.created_at', now()->month)->whereYear('booking_transactions.created_at', now()->year)->sum('booking_transactions.store_amount'),
        ];
    }
    private function remainingUploads(mixed $store, mixed $application): int
    {
        if ($application->max_product === 'unlimited') {
            return -1;
        }

        $used = match (true) {
            $store?->module_type === 'rental' => $store?->vehicles()->count(),
            $store?->module_type === 'service' && service_addon_active() => $store?->services()->count(),
            default => $store?->items()->count(),
        };

        return max($application->max_product - $used, 0);
    }
}
