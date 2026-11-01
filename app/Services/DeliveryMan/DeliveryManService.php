<?php

namespace App\Services\DeliveryMan;

use App\CentralLogics\Helpers;
use App\Mail\DmRegistration;
use App\Mail\DmSelfRegistration;
use App\Models\DeliveryMan;
use App\Services\Admin\AdminService;
use App\Traits\Auth\SetsHashedPasswordTrait;
use App\Traits\System\UploadsImageCollectionTrait;
use App\Services\BaseService;
use App\Services\Order\OrderService;
use App\Services\Order\OrderTransactionService;
use App\Services\Parcel\ParcelCancellationService;
use App\Services\System\DataSettingService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\RideShare\Services\UserLevelService;
use App\Support\Notification\SendNotification;
use App\Support\Notification\NotificationMessages;
use App\Services\System\BusinessSettingService;
use App\Support\Storage\FileStorage;
use Illuminate\Support\Facades\Log;

class DeliveryManService extends BaseService
{
    use UploadsImageCollectionTrait;
    use SetsHashedPasswordTrait;

    private const ONGOING_STATUSES = ['pending', 'accepted', 'confirmed', 'processing', 'handover', 'picked_up'];

    private const IMAGE_DIR = 'delivery-man/';

    public function contactKey(mixed $deliveryMan): array
    {
        return filled($deliveryMan->email)
            ? ['email' => $deliveryMan->email]
            : ['phone' => $deliveryMan->phone];
    }

    public function findFcmToken(mixed $id): mixed
    {
        return $this->findBasic($id)?->fcm_token;
    }

    public function rideShareMetrics(mixed $deliveryMan, array $metrics): array
    {
        $trips = $deliveryMan->driverTrips->where('payment_status', PAID);
        $tips = $trips->sum('tips');
        $totalEarning = $trips->sum('paid_fare');
        $totalCommission = 0;

        foreach ($trips as $trip) {
            $totalCommission += $trip?->fee?->admin_commission ?? 0;
        }

        return [
            'trip_income' => $totalEarning - $totalCommission - $tips,
            'total_trip_commission' => $totalCommission,
            'total_trip_earning' => $totalEarning,
            'total_trip_tips' => $tips,
            'paid_amount' => 0,
            'level_up_reward_amount' => 0,
            'total_income' => ($totalEarning - $totalCommission - $tips) + $metrics['total_delivery_income'],
            'total_tips' => $tips + $metrics['total_delivery_tips'],
            'ride_count' => (int) $deliveryMan->driverTrips->count(),
            'todays_ride_count' => (int) $deliveryMan->todays_rides->count(),
            'this_week_ride_count' => (int) $deliveryMan->this_week_rides->count(),
            'todays_earning' => $metrics['todays_earning'] + $this->rideEarning($deliveryMan->todays_rides),
            'this_week_earning' => $metrics['this_week_earning'] + $this->rideEarning($deliveryMan->this_week_rides),
            'this_month_earning' => $metrics['this_month_earning'] + $this->rideEarning($deliveryMan->this_month_rides),
        ];
    }

    public function getAddData(array $input): array
    {
        if (array_key_exists('image', $input)) {
            $imageName = FileStorage::upload('delivery-man/', ($input['image'] ?? null));
        } else {
            $imageName = 'def.png';
        }

        $identityImageNames = [];
        if (! empty(($input['identity_image'] ?? null))) {
            foreach (($input['identity_image'] ?? null) as $img) {
                $identityImage = FileStorage::upload('delivery-man/', $img);
                array_push($identityImageNames, ['img' => $identityImage, 'storage' => FileStorage::getDisk()]);
            }
            $identityImage = json_encode($identityImageNames);
        } else {
            $identityImage = json_encode([]);
        }

        if (($input['referral_code'] ?? null)) {
            $referal_user = DeliveryMan::where('ref_code', ($input['referral_code'] ?? null))->first();
            $this->notifyReferralUsed($referal_user);
        }

        $data = [
            'f_name' => ($input['f_name'] ?? null),
            'l_name' => ($input['l_name'] ?? null),
            'email' => ($input['email'] ?? null),
            'phone' => ($input['phone'] ?? null),
            'identity_number' => ($input['identity_number'] ?? null),
            'identity_type' => ($input['identity_type'] ?? null),
            'vehicle_id' => ($input['vehicle_id'] ?? null),
            'zone_id' => ($input['zone_id'] ?? null),
            'identity_image' => $identityImage,
            'image' => $imageName,
            'active' => 0,
            'earning' => ($input['earning'] ?? null),
            'password' => bcrypt(($input['password'] ?? null)),
            'ref_by' => ($input['earning'] ?? null) ? $referal_user?->id ?? null : null,
            'ref_code' => Helpers::generate_referer_code('deliveryman'),
            'is_delivery' => in_array('delivery', ($input['serve_for'] ?? null) ?? []) ? 1 : 0,
            'is_ride' => in_array('ride', ($input['serve_for'] ?? null) ?? []) ? 1 : 0,
        ];

        if (addon_published_status('RideShare')) {
            $data['user_level_id'] = ($input['user_level_id'] ?? null) ?? null;
        }

        return $data;
    }

    public function getUpdateData(array $input, object $deliveryMan): array
    {
        if (array_key_exists('image', $input)) {
            $imageName = FileStorage::update('delivery-man/', $deliveryMan->image, ($input['image'] ?? null));
        } else {
            $imageName = $deliveryMan['image'];
        }

        $currentImages = json_decode($deliveryMan['identity_image'], true) ?? [];

        if (array_key_exists('delete_identity_image', $input)) {
            foreach (($input['delete_identity_image'] ?? null) as $delImg) {
                foreach ($currentImages as $key => $imgData) {
                    $imgName = is_array($imgData) ? $imgData['img'] : $imgData;
                    if ($imgName === $delImg) {
                        FileStorage::delete('delivery-man/', $imgData);
                        unset($currentImages[$key]);
                    }
                }
            }
            $currentImages = array_values($currentImages);
        }

        if (array_key_exists('identity_image', $input)) {
            foreach (($input['identity_image'] ?? null) as $img) {
                $identityImage = FileStorage::upload('delivery-man/', $img);
                array_push($currentImages, ['img' => $identityImage, 'storage' => FileStorage::getDisk()]);
            }
        }

        $identityImage = json_encode($currentImages);

        return [
            'f_name' => ($input['f_name'] ?? null),
            'l_name' => ($input['l_name'] ?? null),
            'email' => ($input['email'] ?? null),
            'phone' => ($input['phone'] ?? null),
            'identity_number' => ($input['identity_number'] ?? null),
            'vehicle_id' => ($input['vehicle_id'] ?? null),
            'identity_type' => ($input['identity_type'] ?? null),
            'zone_id' => ($input['zone_id'] ?? null),
            'identity_image' => $identityImage,
            'image' => $imageName,
            'earning' => ($input['earning'] ?? null),
            'password' => strlen(($input['password'] ?? null)) > 1 ? bcrypt(($input['password'] ?? null)) : $deliveryMan['password'],
            'application_status' => in_array($deliveryMan['application_status'], ['pending', 'denied']) ? 'approved' : $deliveryMan['application_status'],
            'status' => in_array($deliveryMan['application_status'], ['pending', 'denied']) ? 1 : $deliveryMan['status'],
            'is_delivery' => in_array('delivery', ($input['serve_for'] ?? null) ?? []) ? 1 : 0,
            'is_ride' => in_array('ride', ($input['serve_for'] ?? null) ?? []) ? 1 : 0,
        ];
    }

    public function loadProfileRelations(DeliveryMan $deliveryMan): DeliveryMan
    {
        $deliveryMan->loadMissing(['storage', 'rating', 'userinfo.storage', 'wallet']);
        $deliveryMan->loadCount(['orders', 'todaysorders', 'this_week_orders']);

        return $deliveryMan;
    }

    public function profileMetrics(DeliveryMan $deliveryMan): array
    {
        $wallet = $deliveryMan->wallet;
        $earnings = $this->earningTotals($deliveryMan->id);
        $returnFees = $this->parcelReturnFeeTotals($deliveryMan->id);

        $balance = $wallet ? $wallet->total_earning - ($wallet->total_withdrawn + $wallet->pending_withdraw) : 0;
        $collectedCash = $wallet?->collected_cash;
        $payableBalance = (float) ($collectedCash ?? 0);
        $withdrawAble = (float) ($balance - $collectedCash > 0 ? abs($balance - $collectedCash) : 0);
        $overFlowBalance = $balance - $collectedCash;
        $walletEarning = round($wallet?->total_earning - ($wallet?->total_withdrawn + $wallet?->pending_withdraw), 8);

        $metrics = $this->ratingMetrics($deliveryMan) + [
            'order_count' => (int) $deliveryMan->orders_count,
            'todays_order_count' => (int) $deliveryMan->todaysorders_count,
            'this_week_order_count' => (int) $deliveryMan->this_week_orders_count,
            'member_since_days' => (int) $deliveryMan->created_at->diffInDays(),
            'referal_earning' => (float) $deliveryMan->referalHistory()->sum('amount'),
            'todays_earning' => (float) ($earnings['today_charge'] + $earnings['today_tips'] + $returnFees['today']),
            'this_week_earning' => (float) ($earnings['week_charge'] + $earnings['week_tips'] + $returnFees['week']),
            'this_month_earning' => (float) ($earnings['month_charge'] + $earnings['month_tips']) + $returnFees['month'],
            'cash_in_hands' => $collectedCash ?? 0,
            'balance' => $balance,
            'total_withdrawn' => (float) ($wallet?->total_withdrawn ?? 0),
            'total_earning' => (float) ($wallet?->total_earning ?? 0),
            'pending_withdraw' => (float) ($wallet?->pending_withdraw ?? 0),
            'withdraw_able_balance' => $withdrawAble,
            'Payable_Balance' => $payableBalance,
            'total_delivery_income' => (float) $earnings['total_charge'],
            'total_delivery_tips' => (float) $earnings['total_tips'],
            'adjust_able' => $this->isAdjustable($wallet, $overFlowBalance, $balance, $walletEarning),
            'show_pay_now_button' => false,
            'show_withdraw_button' => $withdrawAble > $payableBalance,
        ];

        $metrics += $this->cashOverflowFlags($overFlowBalance, $collectedCash);
        $metrics += $this->dynamicBalance($balance, $walletEarning, $wallet?->balance, $collectedCash);

        if ($this->minimumPayableAmount() <= $collectedCash
            && app(BusinessSettingService::class)->value('digital_payment')['status'] == 1
            && $payableBalance > $withdrawAble) {
            $metrics['show_pay_now_button'] = true;
        }

        return $metrics;
    }

    public function updateProfile(DeliveryMan $deliveryMan, array $data): bool
    {
        $imageName = isset($data['image'])
            ? FileStorage::update(self::IMAGE_DIR, $deliveryMan->image, $data['image'])
            : $deliveryMan->image;

        $deliveryMan->vehicle_id = $data['vehicle_id'] ?? $deliveryMan->vehicle_id ?? null;
        $deliveryMan->f_name = $data['f_name'];
        $deliveryMan->l_name = $data['l_name'];
        $deliveryMan->email = $data['email'];
        $deliveryMan->image = $imageName;
        $deliveryMan->password = strlen((string) ($data['password'] ?? '')) > 5 ? bcrypt($data['password']) : $deliveryMan->password;
        $deliveryMan->updated_at = now();
        $deliveryMan->save();

        if ($userInfo = $deliveryMan->userinfo) {
            $userInfo->f_name = $data['f_name'];
            $userInfo->l_name = $data['l_name'];
            $userInfo->email = $data['email'];
            $userInfo->image = $imageName;
            $userInfo->save();
        }

        return true;
    }

    public function toggleActive(DeliveryMan $deliveryMan): bool
    {
        $deliveryMan->active = $deliveryMan->active ? 0 : 1;

        return $deliveryMan->save();
    }

    public function updateFcmToken(mixed $deliveryManId, mixed $fcmToken): bool
    {
        return (bool) DeliveryMan::where('id', $deliveryManId)->update(['fcm_token' => $fcmToken]);
    }

    public function ongoingOrderCount(mixed $deliveryManId): int
    {
        return app(OrderService::class)->countByStatuses(['delivery_man_id' => $deliveryManId], self::ONGOING_STATUSES);
    }

    public function removeAccount(DeliveryMan $deliveryMan): void
    {
        FileStorage::delete(self::IMAGE_DIR, $deliveryMan['image']);

        foreach ($this->decodeIdentityImages($deliveryMan['identity_image']) as $image) {
            FileStorage::delete(self::IMAGE_DIR, $image);
        }

        $deliveryMan->userinfo?->delete();
        $deliveryMan->delete();
    }

    public function getList(array $filters = [], array $paginate = []): LengthAwarePaginator
    {
        return $this->storeScopedQuery($filters)
            ->latest()
            ->paginate($this->pageSize($paginate), ['*'], 'page', $this->pageNumber($paginate));
    }

    public function searchList(array $filters = [], array $paginate = []): LengthAwarePaginator
    {
        $terms = explode(' ', $filters['search'] ?? '');

        return $this->ownedQuery($filters)->with('storage')
            ->where(function ($query) use ($terms) {
                foreach ($terms as $term) {
                    $query->orWhere('f_name', 'like', "%{$term}%")
                        ->orWhere('l_name', 'like', "%{$term}%")
                        ->orWhere('email', 'like', "%{$term}%")
                        ->orWhere('phone', 'like', "%{$term}%")
                        ->orWhere('identity_number', 'like', "%{$term}%");
                }
            })
            ->paginate($this->pageSize($paginate), ['*'], 'page', $this->pageNumber($paginate));
    }

    public function findWithoutReviews(mixed $id): ?DeliveryMan
    {
        return $this->findBasic($id);
    }

    public function find(mixed $id, array $filters = []): ?DeliveryMan
    {
        return $this->storeScopedQuery($filters)->with(['reviews.customer.storage'])->find($id);
    }

    public function login(array $credentials): ?object
    {
        return auth('delivery_men')->attempt($credentials) ? auth('delivery_men')->user() : null;
    }

    public function findByPhoneAndType(mixed $phone, string $type): ?DeliveryMan
    {
        return $this->byPhoneQuery($phone)->where($type, 1)->first();
    }

    public function issueAuthToken(DeliveryMan $deliveryMan): string
    {
        $token = Str::random(120);
        $deliveryMan->auth_token = $token;
        $deliveryMan->save();

        return $token;
    }

    public function notificationTopics(DeliveryMan $deliveryMan, string $type): array
    {
        $zone = $deliveryMan->zone;

        if ($type == 'is_delivery') {
            $topic = 'restaurant_dm_'.$deliveryMan?->store_id;

            if (! $zone) {
                return ['topic' => $topic, 'zone_topic' => ''];
            }

            return [
                'topic' => $deliveryMan->vehicle_id
                    ? 'delivery_man_'.$zone->id.'_'.$deliveryMan->vehicle_id
                    : ($deliveryMan->type == 'zone_wise' ? $zone->deliveryman_wise_topic : 'restaurant_dm_'.$deliveryMan->store_id),
                'zone_topic' => $deliveryMan->type == 'zone_wise' ? $zone->deliveryman_wise_topic.'_push' : '',
            ];
        }

        if (! $zone) {
            return ['topic' => 'admin', 'zone_topic' => ''];
        }

        return [
            'topic' => $deliveryMan->vehicle_id ? 'rider_'.$zone->id.'_'.$deliveryMan->vehicle_id : 'admin',
            'zone_topic' => $deliveryMan->type == 'zone_wise' ? 'zone_'.$zone->id.'_rider' : '',
        ];
    }

    public function createFromRegistration(array $data, array $identityFiles, mixed $imageFile): DeliveryMan
    {
        return DB::transaction(function () use ($data, $identityFiles, $imageFile) {
            return $this->persistRegistration($data, $identityFiles, $imageFile);
        });
    }

    public function notifyRegistration(DeliveryMan $deliveryMan, mixed $email): void
    {
        if (! config('mail.status')) {
            return;
        }

        try {
            if (SendNotification::canSendMail('registration_mail_status_dm', 'deliveryman', 'deliveryman_registration')) {
                SendNotification::mail($email, new DmSelfRegistration('pending', $deliveryMan));
            }

            if (SendNotification::canSendMail('dm_registration_mail_status_admin', 'admin', 'deliveryman_self_registration')) {
                SendNotification::mail(app(AdminService::class)->findSuperAdminEmail(), new DmRegistration('pending', $deliveryMan));
            }
        } catch (\Exception $exception) {
            Log::error('delivery_man.delivery_man_service.notify_registration_failed', [
                'error' => $exception->getMessage(),
                'file' => $exception->getFile().':'.$exception->getLine(),
            ]);
        }
    }

    public function findByPhone(mixed $phone): ?DeliveryMan
    {
        return $this->byPhoneQuery($phone)->first();
    }

    public function create(array $data): DeliveryMan
    {
        $deliveryMan = new DeliveryMan;
        $deliveryMan->fill($this->writablePayload($data) + [
            'store_id' => $data['store_id'],
            'active' => 0,
            'earning' => 0,
            'type' => 'restaurant_wise',
            'image' => isset($data['image']) ? FileStorage::upload(self::IMAGE_DIR, $data['image']) : 'def.png',
            'identity_image' => json_encode($this->uploadImageCollection($data['identity_image'] ?? [], self::IMAGE_DIR)),
            'password' => bcrypt($data['password']),
        ]);
        $deliveryMan->save();

        return $deliveryMan;
    }

    public function update(mixed $id, array $data, array $filters = []): ?DeliveryMan
    {
        $deliveryMan = $this->ownedQuery($filters)->find($id);

        if (! $deliveryMan) {
            return null;
        }

        $deliveryMan->fill($this->writablePayload($data) + [
            'image' => isset($data['image'])
                ? FileStorage::update(self::IMAGE_DIR, $deliveryMan->image, $data['image'])
                : $deliveryMan->image,
            'identity_image' => $this->replaceIdentityImages($deliveryMan, $data),
            'password' => strlen((string) ($data['password'] ?? '')) > 1 ? bcrypt($data['password']) : $deliveryMan->password,
        ]);
        $deliveryMan->save();

        return $deliveryMan;
    }

    public function updateStatus(mixed $id, mixed $status, array $filters = []): ?DeliveryMan
    {
        $deliveryMan = $this->ownedQuery($filters)->find($id);

        if (! $deliveryMan) {
            return null;
        }

        $deliveryMan->status = $status;

        if (! $status) {
            $deliveryMan->auth_token = null;
        }

        $this->notifyStatusChange($deliveryMan, $status);
        $deliveryMan->save();

        return $deliveryMan;
    }

    public function delete(mixed $id, array $filters = []): bool
    {
        $deliveryMan = $this->ownedQuery($filters)->find($id);

        if (! $deliveryMan) {
            return false;
        }

        FileStorage::delete(self::IMAGE_DIR, $deliveryMan['image']);

        foreach ($this->decodeIdentityImages($deliveryMan['identity_image']) as $image) {
            FileStorage::delete(self::IMAGE_DIR, $image);
        }

        return (bool) $deliveryMan->delete();
    }

    public function findBasic(mixed $id, array $relations = []): ?DeliveryMan
    {
        return DeliveryMan::with($relations)->find($id);
    }

    private function persistRegistration(array $data, array $identityFiles, mixed $imageFile): DeliveryMan
    {
        $identityImage = json_encode(array_map(
            fn ($file) => ['img' => FileStorage::upload('delivery-man/', $file), 'storage' => FileStorage::getDisk()],
            array_values(array_filter($identityFiles))
        ));
        $image = $imageFile ? FileStorage::upload('delivery-man/', $imageFile) : 'def.png';

        $referrer = $data['referral_code'] ?? null
            ? DeliveryMan::withoutGlobalScope('delivery_only')->where('ref_code', $data['referral_code'])->first()
            : null;

        $firstLevel = addon_published_status('RideShare') && ($data['type'] ?? null) == 'is_ride'
            ? app(UserLevelService::class)->findFirstForUserType(DRIVER)
            : null;

        $deliveryMan = new DeliveryMan;
        $deliveryMan->f_name = $data['f_name'];
        $deliveryMan->l_name = $data['l_name'] ?? null;
        $deliveryMan->email = $data['email'];
        $deliveryMan->phone = $data['phone'];
        $deliveryMan->identity_number = $data['identity_number'];
        $deliveryMan->identity_type = $data['identity_type'];
        $deliveryMan->identity_image = $identityImage;
        $deliveryMan->vehicle_id = $data['vehicle_id'] ?? null;
        $deliveryMan->image = $image;
        $deliveryMan->status = 0;
        $deliveryMan->active = 0;
        $deliveryMan->application_status = 'pending';
        $deliveryMan->zone_id = $data['zone_id'];
        $deliveryMan->earning = $data['earning'];
        $deliveryMan->password = bcrypt($data['password']);
        $deliveryMan->ref_by = $referrer?->id;
        $deliveryMan->ref_code = Helpers::generate_referer_code('deliveryman');
        $deliveryMan->is_delivery = $data['type'] == 'is_delivery' ? 1 : 0;
        $deliveryMan->is_ride = $data['type'] == 'is_ride' ? 1 : 0;

        if (addon_published_status('RideShare')) {
            $deliveryMan->user_level_id = $firstLevel?->id;
        }

        $deliveryMan->save();

        return $deliveryMan;
    }

    private function byPhoneQuery(mixed $phone): Builder
    {
        return DeliveryMan::withoutGlobalScope('delivery_only')->where('phone', $phone);
    }

    private function minimumPayableAmount(): mixed
    {
        if (addon_published_status('RideShare')) {
            return app(DataSettingService::class)->findValueByKey('min_amount_to_pay_rider');
        }

        return app(BusinessSettingService::class)->value('min_amount_to_pay_dm', false) ?? 0;
    }

    private function ratingMetrics(DeliveryMan $deliveryMan): array
    {
        $ownRating = $deliveryMan->rating[0] ?? null;

        if (addon_published_status('RideShare')) {
            $combined = $deliveryMan->combinedRating;

            return [
                'avg_rating' => (float) ($combined->average ?: ($ownRating->average ?? 0)),
                'rating_count' => (float) ($combined->total ?: ($ownRating->rating_count ?? 0)),
            ];
        }

        return [
            'avg_rating' => (float) ($ownRating->average ?? 0),
            'rating_count' => (float) ($ownRating->rating_count ?? 0),
        ];
    }

    private function isAdjustable(mixed $wallet, mixed $overFlowBalance, mixed $balance, mixed $walletEarning): bool
    {
        if ($wallet?->collected_cash == 0 || $walletEarning == 0) {
            return false;
        }

        return isset($wallet)
            && (($overFlowBalance > 0 && $wallet->collected_cash > 0) || ($wallet->collected_cash != 0 && $balance != 0));
    }

    private function cashOverflowFlags(mixed $overFlowBalance, mixed $collectedCash): array
    {
        $maxCashInHand = app(BusinessSettingService::class)->value('dm_max_cash_in_hand');
        $overflowEnabled = app(BusinessSettingService::class)->value('cash_in_hand_overflow_delivery_man');
        $warningThreshold = $maxCashInHand - (($maxCashInHand * 10) / 100);
        $hasPayable = $collectedCash > 0 && $overflowEnabled && $overFlowBalance < 0;

        return [
            'over_flow_warning' => $hasPayable && $warningThreshold <= abs($collectedCash),
            'dm_max_cash_in_hand' => (float) $maxCashInHand,
            'over_flow_block_warning' => $hasPayable && $maxCashInHand < abs($collectedCash),
        ];
    }

    private function dynamicBalance(mixed $balance, mixed $walletEarning, mixed $walletBalance, mixed $collectedCash): array
    {
        if ($balance > 0) {
            return [
                'dynamic_balance' => (float) abs($walletEarning),
                'dynamic_balance_type' => $walletBalance == $walletEarning
                    ? translate('Withdrawable balance')
                    : translate('messages.balance').' '.translate('Unadjusted'),
            ];
        }

        return [
            'dynamic_balance' => (float) abs((float) $collectedCash),
            'dynamic_balance_type' => translate('Payable balance'),
        ];
    }

    private function earningTotals(mixed $deliveryManId): array
    {
        return app(OrderTransactionService::class)->deliveryManEarningTotals($deliveryManId);
    }

    private function parcelReturnFeeTotals(mixed $deliveryManId): array
    {
        return app(ParcelCancellationService::class)->deliveryManReturnFeeTotals($deliveryManId);
    }

    private function decodeIdentityImages(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }

        return json_decode((string) $value, true) ?? [];
    }

    private function storeScopedQuery(array $filters): mixed
    {
        return $this->ownedQuery($filters)
            ->with(['rating', 'wallet', 'storage'])
            ->withCount(['orders as orders_count' => fn ($query) => $query->where('order_status', 'delivered')]);
    }

    private function ownedQuery(array $filters): mixed
    {
        return DeliveryMan::when(isset($filters['store_id']), fn ($query) => $query->where('store_id', $filters['store_id']));
    }

    private function writablePayload(array $data): array
    {
        return [
            'f_name' => $data['f_name'] ?? null,
            'l_name' => $data['l_name'] ?? null,
            'email' => $data['email'] ?? null,
            'phone' => $data['phone'] ?? null,
            'identity_number' => $data['identity_number'] ?? null,
            'identity_type' => $data['identity_type'] ?? null,
            'vehicle_id' => $data['vehicle_id'] ?? null,
        ];
    }

    private function replaceIdentityImages(DeliveryMan $deliveryMan, array $data): mixed
    {
        if (! isset($data['identity_image'])) {
            return $deliveryMan['identity_image'];
        }

        foreach ($this->decodeIdentityImages($deliveryMan['identity_image']) as $image) {
            FileStorage::delete(self::IMAGE_DIR, $image);
        }

        return json_encode($this->uploadImageCollection($data['identity_image'], self::IMAGE_DIR));
    }



    public function notifyReferralUsed($referrer): void
    {
        try {
            $data = NotificationMessages::deliveryManReferralUsed();
            if (SendNotification::channelEnabled('deliveryman', 'deliveryman_referral_notification', 'push_notification_status') && $referrer->fcm_token) {
                SendNotification::pushToDeliveryMan($referrer->id, $referrer->fcm_token, $data);
            }
        } catch (\Exception $exception) {
            info(["line___{$exception->getLine()}", $exception->getMessage()]);
        }
    }

    private function notifyStatusChange(DeliveryMan $deliveryMan, mixed $status): void
    {
        $key = $status ? 'deliveryman_account_unblock' : 'deliveryman_account_block';

        if (! SendNotification::channelEnabled('deliveryman', $key, 'push_notification_status') || ! isset($deliveryMan->fcm_token)) {
            return;
        }

        $data = $status
            ? NotificationMessages::deliveryManAccountActivated()
            : NotificationMessages::deliveryManAccountSuspended();

        try {
            SendNotification::pushToDeliveryMan($deliveryMan->id, $deliveryMan->fcm_token, $data);
        } catch (\Exception $exception) {
            Log::error('delivery_man.delivery_man_service.notify_status_change_failed', [
                'error' => $exception->getMessage(),
                'file' => $exception->getFile().':'.$exception->getLine(),
            ]);
        }
    }

    private function rideEarning(mixed $rides): float
    {
        $paid = $rides->where('payment_status', PAID);

        return (float) ($paid->sum('paid_fare') - $paid->sum('fee.admin_commission') + $paid->sum('tips'));
    }

    public function refCodeExists(mixed $refCode): bool
    {
        return DeliveryMan::where('ref_code', '=', $refCode)->exists();
    }
}
