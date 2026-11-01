<?php

namespace App\Services\Chat;

use App\Models\Conversation;
use App\Services\BaseService;
use Carbon\Carbon;
use Illuminate\Pagination\LengthAwarePaginator;
use App\Services\DeliveryMan\DeliveryManService;
use App\Services\Customer\UserService;
use App\Services\Order\OrderService;
use App\Services\Vendor\VendorService;
use Modules\Rental\Services\Trip\TripsService;
use Modules\RideShare\Services\RideRequestService;
use Modules\Service\Services\ServiceBookingService;

class ConversationService extends BaseService
{
    public const ADMIN_RECEIVER_ID = 0;
    private const ACTIVE_ORDER_STATUSES = ['pending', 'accepted', 'confirmed', 'processing', 'handover', 'picked_up'];
    private const ACTIVE_TRIP_STATUSES = ['pending', 'confirmed', 'ongoing', 'completed'];
    private const ACTIVE_RIDE_STATUSES = ['pending', 'accepted', 'ongoing'];
    private const PARTY_RELATIONS = [
        'sender.user.storage',
        'sender.vendor.storage',
        'sender.vendor.stores.storage',
        'sender.delivery_man.storage',
        'receiver.user.storage',
        'receiver.vendor.storage',
        'receiver.vendor.stores.storage',
        'receiver.delivery_man.storage',
        'last_message',
    ];
    public function getList(
        array $filters = [],
        array $paginate = []
    ): LengthAwarePaginator {
        return $this->listQuery($filters)
            ->orderBy('last_message_time', 'DESC')
            ->paginate($this->pageSize($paginate), ['*'], 'page', $this->pageNumber($paginate));
    }
    public function getSearchList(
        array $filters = [],
        array $paginate = []
    ): LengthAwarePaginator {
        $keywords = explode(' ', (string) ($filters['name'] ?? ''));

        return $this->listQuery($filters)
            ->where(fn ($query) => $query
                ->whereHas('sender', fn ($sender) => $this->matchName($sender, $keywords))
                ->orWhereHas('receiver', fn ($receiver) => $this->matchName($receiver, $keywords)))
            ->orderBy('last_message_time', 'DESC')
            ->paginate($this->pageSize($paginate), ['*'], 'page', $this->pageNumber($paginate));
    }
    public function findThread(mixed $conversationId, mixed $viewerInfoId = null): ?Conversation
    {
        return $this->threadQuery()
            ->when($viewerInfoId, fn ($query) => $query->whereUser($viewerInfoId))
            ->find($conversationId);
    }
    public function findThreadBetween(mixed $senderInfoId, mixed $receiverInfoId): ?Conversation
    {
        return $this->betweenQuery($senderInfoId, $receiverInfoId)
            ->with(self::PARTY_RELATIONS)
            ->first();
    }
    public function openThread(mixed $senderInfoId, string $senderType, mixed $receiverInfoId, ?string $receiverType): Conversation
    {
        $conversation = $this->betweenQuery($senderInfoId, $receiverInfoId)->first();

        if ($conversation) {
            return $conversation;
        }

        $conversation = new Conversation();
        $conversation->sender_id = $senderInfoId;
        $conversation->sender_type = $senderType;
        $conversation->receiver_id = $receiverInfoId;
        $conversation->receiver_type = $receiverType;
        $conversation->unread_message_count = 0;
        $conversation->last_message_time = Carbon::now()->toDateTimeString();
        $conversation->save();

        return Conversation::find($conversation->id);
    }
    public function counterpartyInfoId(mixed $conversationId, mixed $viewerInfoId): mixed
    {
        // Held to conversations the viewer is part of. The id comes from the request, and for a
        // thread the viewer is not in, "the other side" fell back to its sender -- so walking ids
        // revealed who talks to whom across stores and let the viewer message them. Callers
        // already treat null as "no data found".
        $conversation = Conversation::withoutGlobalScopes()
            ->whereUser($viewerInfoId)
            ->find($conversationId, ['id', 'sender_id', 'receiver_id']);

        if (! $conversation) {
            return null;
        }

        $otherId = $conversation->sender_id == $viewerInfoId
            ? $conversation->receiver_id
            : $conversation->sender_id;

        $other = app(UserInfoService::class)->find($otherId);

        return $other?->admin_id ? self::ADMIN_RECEIVER_ID : $otherId;
    }
    public function registerMessage(Conversation $conversation, mixed $messageId, mixed $sentAt = null): void
    {
        $conversation->unread_message_count = $conversation->unread_message_count
            ? $conversation->unread_message_count + 1
            : 1;
        $conversation->last_message_id = $messageId;
        $conversation->last_message_time = $sentAt ?? Carbon::now()->toDateTimeString();
        $conversation->save();
    }
    public function clearUnread(Conversation $conversation, mixed $viewerInfoId): void
    {
        $lastMessage = $conversation->last_message;

        if ($lastMessage && $lastMessage->sender_id != $viewerInfoId) {
            $conversation->unread_message_count = 0;
            $conversation->save();
        }
    }
    public function customerEngagementCount(?Conversation $conversation, mixed $customerId): int
    {
        [$type, $party] = $this->counterparty($conversation, [
            UserInfoService::TYPE_VENDOR,
            UserInfoService::TYPE_DELIVERY_MAN,
        ]);

        if ($type === UserInfoService::TYPE_VENDOR) {
            $vendor = app(VendorService::class)->findWithStore($party->vendor_id);

            if ($vendor?->store?->module_type === 'rental' && addon_published_status('Rental')) {
                return app(TripsService::class)->countUnpaidActive($customerId, $vendor->store->id, self::ACTIVE_TRIP_STATUSES);
            }

            if ($vendor?->store?->module_type === 'service' && addon_published_status('Service')) {
                return app(ServiceBookingService::class)->countActive(['user_id' => $customerId, 'provider_id' => $vendor->store->id]);
            }

            return app(OrderService::class)->countByStatuses(['user_id' => $customerId, 'store_id' => $vendor->stores[0]->id], self::ACTIVE_ORDER_STATUSES);
        }

        if ($type === UserInfoService::TYPE_DELIVERY_MAN) {
            $deliveryMan = app(DeliveryManService::class)->findWithoutReviews($party->deliveryman_id);

            $count = app(OrderService::class)->countByStatuses(['user_id' => $customerId, 'delivery_man_id' => $deliveryMan->id], self::ACTIVE_ORDER_STATUSES);

            if ($count <= 0 && addon_published_status('RideShare')) {
                $count = app(RideRequestService::class)->countByStatuses(['customer_id' => $customerId, 'driver_id' => $deliveryMan->id], self::ACTIVE_RIDE_STATUSES);
            }

            return $count;
        }

        return 1;
    }
    public function vendorEngagementCount(?Conversation $conversation, mixed $storeId, ?string $storeModuleType = null): int
    {
        [$type, $party] = $this->counterparty($conversation, [
            UserInfoService::TYPE_CUSTOMER,
            UserInfoService::TYPE_DELIVERY_MAN,
        ]);

        if ($type === UserInfoService::TYPE_CUSTOMER) {
            $user = app(UserService::class)->find($party->user_id);

            if ($storeModuleType === 'service' && addon_published_status('Service')) {
                return app(ServiceBookingService::class)->countActive(['provider_id' => $storeId, 'user_id' => $user->id]);
            }

            return app(OrderService::class)->countByStatuses(['store_id' => $storeId, 'user_id' => $user->id], self::ACTIVE_ORDER_STATUSES);
        }

        if ($type === UserInfoService::TYPE_DELIVERY_MAN) {
            $deliveryMan = app(DeliveryManService::class)->findWithoutReviews($party->deliveryman_id);

            return app(OrderService::class)->countByStatuses(['store_id' => $storeId, 'delivery_man_id' => $deliveryMan->id], self::ACTIVE_ORDER_STATUSES);
        }

        return 0;
    }
    public function deliveryManEngagementCount(?Conversation $conversation, mixed $deliveryManId): int
    {
        [$type, $party] = $this->counterparty($conversation, [
            UserInfoService::TYPE_VENDOR,
            UserInfoService::TYPE_CUSTOMER,
        ]);

        if ($type === UserInfoService::TYPE_VENDOR) {
            $vendor = app(VendorService::class)->findWithStores($party->vendor_id);

            return app(OrderService::class)->countByStatuses(['delivery_man_id' => $deliveryManId, 'store_id' => $vendor->stores[0]->id], self::ACTIVE_ORDER_STATUSES);
        }

        if ($type === UserInfoService::TYPE_CUSTOMER) {
            $user = app(UserService::class)->find($party->user_id);

            $count = app(OrderService::class)->countByStatuses(['delivery_man_id' => $deliveryManId, 'user_id' => $user->id], self::ACTIVE_ORDER_STATUSES);

            if ($count <= 0 && addon_published_status('RideShare')) {
                $count = app(RideRequestService::class)->countByStatuses(['driver_id' => $deliveryManId, 'customer_id' => $user->id], self::ACTIVE_RIDE_STATUSES);
            }

            return $count;
        }

        return 1;
    }
    public function unreadCountForUser(mixed $userId): int
    {
        return Conversation::whereUser($userId)->where('unread_message_count', '>', 0)->count();
    }
    private function threadQuery(): mixed
    {
        return Conversation::with(self::PARTY_RELATIONS);
    }
    private function betweenQuery(mixed $senderInfoId, mixed $receiverInfoId): mixed
    {
        return Conversation::whereConversation($senderInfoId, $receiverInfoId);
    }
    private function counterparty(?Conversation $conversation, array $typePrecedence): array
    {
        if (! $conversation) {
            return [null, null];
        }

        foreach ($typePrecedence as $type) {
            if ($conversation->sender_type === $type && $conversation->sender) {
                return [$type, $conversation->sender];
            }

            if ($conversation->receiver_type === $type && $conversation->receiver) {
                return [$type, $conversation->receiver];
            }
        }

        return [null, null];
    }
    private function listQuery(array $filters): mixed
    {
        $viewerInfoId = $filters['viewer_info_id'] ?? null;
        $viewerType = $filters['viewer_type'] ?? null;
        $type = $filters['type'] ?? null;

        return $this->threadQuery()
            ->whereUser($viewerInfoId)
            ->when($type, fn ($query) => $query->where(fn ($scoped) => $scoped
                ->where(fn ($q) => $q->where('receiver_type', $type)->where('sender_type', $viewerType))
                ->orWhere(fn ($q) => $q->where('sender_type', $type)->where('receiver_type', $viewerType))));
    }
    private function matchName(mixed $query, array $keywords): void
    {
        foreach ($keywords as $keyword) {
            $query->where('f_name', 'like', "%{$keyword}%")
                ->orWhere('l_name', 'like', "%{$keyword}%");
        }
    }
}
