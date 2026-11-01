<?php

namespace App\Services\Chat;

use App\Models\UserInfo;
use App\Services\BaseService;
use App\Services\Admin\AdminService;
use App\Services\Customer\UserService;
use App\Services\DeliveryMan\DeliveryManService;
use App\Services\Vendor\VendorService;
use Modules\Rental\Services\Vehicle\VehicleDriverService;

class UserInfoService extends BaseService
{
    public const TYPE_CUSTOMER = 'customer';
    public const TYPE_VENDOR = 'vendor';
    public const TYPE_DELIVERY_MAN = 'delivery_man';
    public const TYPE_ADMIN = 'admin';
    public const TYPE_RENTAL_DRIVER = 'rental_driver';
    private const OWNER_COLUMN = [
        self::TYPE_CUSTOMER => 'user_id',
        self::TYPE_VENDOR => 'vendor_id',
        self::TYPE_DELIVERY_MAN => 'deliveryman_id',
        self::TYPE_ADMIN => 'admin_id',
        self::TYPE_RENTAL_DRIVER => 'deliveryman_id',
    ];
    public function findOrCreateForAdmin(): ?UserInfo
    {
        $admin = app(AdminService::class)->findSuperAdmin();

        if (! $admin) {
            return null;
        }

        $userInfo = $this->baseQuery()->where('admin_id', $admin->id)->first();

        if ($userInfo) {
            return $userInfo;
        }

        $userInfo = new UserInfo();
        $userInfo->admin_id = $admin->id;
        $userInfo->f_name = $admin->f_name;
        $userInfo->l_name = $admin->l_name;
        $userInfo->phone = $admin->phone;
        $userInfo->email = $admin->email;
        $userInfo->image = $admin->image;
        $userInfo->save();

        return $userInfo;
    }
    public function updateForUser(mixed $userId, array $values): void
    {
        UserInfo::where('user_id', $userId)->update($values);
    }
    public function findByOwner(?string $type, mixed $ownerId): ?UserInfo
    {
        $column = $type === null ? null : (self::OWNER_COLUMN[$type] ?? null);

        if (! $column || ! $ownerId) {
            return null;
        }

        $userInfo = $this->baseQuery()
            ->where($column, $ownerId)
            ->first();

        if ($userInfo) {
            return $userInfo;
        }

        $owner = $this->owner($type, $ownerId);

        if (! $owner) {
            return null;
        }

        $userInfo = new UserInfo();
        $userInfo->{$column} = $owner->id;

        foreach ($this->buildProfile($type, $owner) as $attribute => $value) {
            $userInfo->{$attribute} = $value;
        }

        $userInfo->save();

        return $userInfo;
    }
    public function find(mixed $userInfoId): ?UserInfo
    {
        return $this->baseQuery()->find($userInfoId);
    }
    public function getPushTarget(?UserInfo $party, string $driverType = self::TYPE_DELIVERY_MAN): array
    {
        if (! $party) {
            return ['token' => null, 'web_topic' => null];
        }

        if ($party->vendor_id) {
            return app(VendorService::class)->findFirebaseTarget($party->vendor_id);
        }

        if ($party->deliveryman_id) {
            return [
                'token' => $driverType === self::TYPE_RENTAL_DRIVER
                    ? null
                    : app(DeliveryManService::class)->findFcmToken($party->deliveryman_id),
                'web_topic' => null,
            ];
        }

        if ($party->user_id) {
            return ['token' => app(UserService::class)->findFirebaseToken($party->user_id), 'web_topic' => null];
        }

        return ['token' => null, 'web_topic' => null];
    }
    private function baseQuery(): mixed
    {
        return UserInfo::without(['user', 'vendor', 'delivery_man']);
    }
    private function owner(string $type, mixed $ownerId): mixed
    {
        return match ($type) {
            self::TYPE_CUSTOMER => app(UserService::class)->find($ownerId),
            self::TYPE_VENDOR => app(VendorService::class)->findWithStores($ownerId),
            self::TYPE_DELIVERY_MAN => app(DeliveryManService::class)->findWithoutReviews($ownerId),
            self::TYPE_RENTAL_DRIVER => app(VehicleDriverService::class)->findById($ownerId),
            self::TYPE_ADMIN => app(AdminService::class)->find($ownerId),
            default => null,
        };
    }
    private function buildProfile(string $type, mixed $owner): array
    {
        if ($type === self::TYPE_RENTAL_DRIVER) {
            return [
                'f_name' => $owner->first_name,
                'l_name' => $owner->last_name,
                'phone' => $owner->phone,
                'email' => $owner->email,
                'image' => $owner->image,
            ];
        }

        if ($type === self::TYPE_VENDOR) {
            $store = $owner->stores[0] ?? null;

            return [
                'f_name' => $store?->name,
                'l_name' => '',
                'phone' => $owner->phone,
                'email' => $owner->email,
                'image' => $store?->logo,
            ];
        }

        return [
            'f_name' => $owner->f_name,
            'l_name' => $owner->l_name,
            'phone' => $owner->phone,
            'email' => $owner->email,
            'image' => $owner->image,
        ];
    }
}
