<?php

namespace App\Services\System;

use App\Models\Notification;
use App\Models\UserNotification;
use App\Scopes\ZoneScope;
use App\Services\BaseService;
use App\Services\System\UserNotificationService;
use Carbon\Carbon;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use App\Support\Storage\FileStorage;

class NotificationService extends BaseService
{
    private const RECENT_DAYS = 15;
    private const DELIVERY_MAN_RECENT_DAYS = 7;
    private const SERVICEMAN_RECENT_DAYS = 7;
    private const VENDOR_RECENT_DAYS = 7;
    private const BROADCAST_TARGET = 'customer';
    private const SOURCE_BROADCAST = 0;
    private const SOURCE_PERSONAL = 1;
    private const BROADCAST_COLUMNS = ['id', 'title', 'description', 'image', 'created_at', 'updated_at'];
    private const PERSONAL_COLUMNS = ['id', 'data', 'created_at', 'updated_at'];
    public function getCustomerFeed(
        array $filters = [],
        array $paginate = []
    ): LengthAwarePaginator {
        return $this->feed([
            'target' => self::BROADCAST_TARGET,
            'zone_ids' => $filters['zone_ids'] ?? [],
            'owner_column' => 'user_id',
            'owner_id' => $filters['user_id'] ?? null,
            'recent_days' => self::RECENT_DAYS,
        ], $paginate);
    }
    public function getDeliveryManFeed(
        array $filters = [],
        array $paginate = []
    ): LengthAwarePaginator {
        return $this->feed([
            'target' => ($filters['is_ride'] ?? 0) == 1 ? 'rider' : 'deliveryman',
            'zone_ids' => [$filters['zone_id'] ?? null],
            'owner_column' => 'delivery_man_id',
            'owner_id' => $filters['delivery_man_id'] ?? null,
            'recent_days' => self::DELIVERY_MAN_RECENT_DAYS,
            'date_column' => 'created_at',
            'full_row' => true,
        ], $paginate);
    }
    public function getVendorFeed(
        array $filters = [],
        array $paginate = []
    ): LengthAwarePaginator {
        return $this->feed([
            'target' => 'store',
            'zone_ids' => [$filters['zone_id'] ?? null],
            'owner_column' => 'vendor_id',
            'owner_id' => $filters['vendor_id'] ?? null,
            'recent_days' => self::VENDOR_RECENT_DAYS,
            'date_column' => 'created_at',
            'full_row' => true,
        ], $paginate);
    }
    public function getAddData(array $input): array
    {
        if (array_key_exists('image', $input)) {
            $imageName = FileStorage::upload('notification/', ($input['image'] ?? null));
        } else {
            $imageName = null;
        }
        return [
            'title' => ($input['notification_title'] ?? null),
            'description' => ($input['description'] ?? null),
            'image' => $imageName,
            'tergat' => ($input['tergat'] ?? null),
            'status' => 1,
            'zone_id' => ($input['zone'] ?? null)=='all'?null:($input['zone'] ?? null),
        ];
    }
    public function getUpdateData(array $input, object $notification): array
    {
        if (array_key_exists('image', $input)) {
            $imageName = FileStorage::update('notification/', $notification->image, ($input['image'] ?? null));
        } elseif (($input['image_deleted'] ?? null) == 1) {
            $imageName = null;
        } else {
            $imageName = $notification['image'];
        }
        return [
            'title' => ($input['notification_title'] ?? null),
            'description' => ($input['description'] ?? null),
            'image' => $imageName,
            'tergat' => ($input['tergat'] ?? null),
            'status' => 1,
            'zone_id' => ($input['zone'] ?? null) == 'all' ? null : ($input['zone'] ?? null),
            'updated_at' => now(),
        ];
    }
    public function getTopic(array $input): string
    {
        $topicAllZone =[
            'customer'=>'all_zone_customer',
            'deliveryman'=>'all_zone_delivery_man',
            'rider'=>'all_zone_rider',
            'store'=>'all_zone_store',
        ];

        $topicZoneWise=[
            'customer'=>'zone_'.($input['zone'] ?? null).'_customer',
            'deliveryman'=>'zone_'.($input['zone'] ?? null).'_delivery_man_push',
            'rider'=>'zone_'.($input['zone'] ?? null).'_rider',
            'store'=>'zone_'.($input['zone'] ?? null).'_store',
        ];

        return ($input['zone'] ?? null) == 'all'?$topicAllZone[($input['tergat'] ?? null)]:$topicZoneWise[($input['tergat'] ?? null)];
    }
    public function getServicemanFeed(?int $zoneId, mixed $servicemanId, int $recentDays = self::SERVICEMAN_RECENT_DAYS): Collection
    {
        $cutoff = Carbon::today()->subDays($recentDays);

        $broadcast = Notification::withoutGlobalScope(ZoneScope::class)
            ->active()
            ->where('tergat', 'serviceman')
            ->where(fn ($query) => $query->whereNull('zone_id')->orWhere('zone_id', $zoneId))
            ->where('created_at', '>=', $cutoff)
            ->get();

        $broadcast->append('data');

        $personal = UserNotification::where('serviceman_id', $servicemanId)
            ->where('created_at', '>=', $cutoff)
            ->get();

        return $broadcast->merge($personal);
    }
    private function feed(array $spec, array $paginate): LengthAwarePaginator
    {
        $cutoff = Carbon::today()->subDays($spec['recent_days']);

        $page = $this->broadcastQuery($spec, $cutoff)
            ->union($this->personalQuery($spec, $cutoff))
            ->orderBy('feed_source')
            ->orderBy('id')
            ->paginate($this->pageSize($paginate), ['*'], 'page', $this->pageNumber($paginate));

        $page->setCollection($this->loadRows($page->getCollection(), $spec));

        return $page;
    }
    private function broadcastQuery(array $spec, Carbon $cutoff): mixed
    {
        $zoneIds = array_filter($spec['zone_ids'], fn ($zoneId) => $zoneId !== null);

        return Notification::withoutGlobalScope('translate')
            ->active()
            ->where('tergat', $spec['target'])
            ->where(fn ($query) => $query->whereNull('zone_id')->orWhereIn('zone_id', $zoneIds))
            ->where($spec['date_column'] ?? 'updated_at', '>=', $cutoff)
            ->selectRaw('id, ' . self::SOURCE_BROADCAST . ' as feed_source');
    }
    private function personalQuery(array $spec, Carbon $cutoff): mixed
    {
        return app(UserNotificationService::class)->feedQuery(
            $spec['owner_column'],
            $spec['owner_id'],
            $spec['date_column'] ?? 'updated_at',
            $cutoff,
            self::SOURCE_PERSONAL
        );
    }
    private function loadRows(mixed $rows, array $spec = []): mixed
    {
        $fullRow = $spec['full_row'] ?? false;
        $broadcastIds = [];
        $personalIds = [];

        foreach ($rows as $row) {
            if ((int) $row->feed_source === self::SOURCE_BROADCAST) {
                $broadcastIds[] = $row->id;
            } else {
                $personalIds[] = $row->id;
            }
        }

        $broadcasts = $broadcastIds
            ? Notification::withoutGlobalScope('translate')
                ->with(['storage' => fn ($query) => $query->select(STORAGE_RELATION_COLUMNS)])
                ->whereIn('id', $broadcastIds)
                ->select($fullRow ? ['*'] : self::BROADCAST_COLUMNS)
                ->get()
                ->each(fn ($row) => $fullRow ? $row->append('data') : $row)
                ->keyBy('id')
            : collect();

        $personal = $personalIds
            ? app(UserNotificationService::class)->getByIds($personalIds, $fullRow ? ['*'] : self::PERSONAL_COLUMNS)
            : collect();

        return $rows->map(fn ($row) => (int) $row->feed_source === self::SOURCE_BROADCAST
            ? $broadcasts->get($row->id)
            : $personal->get($row->id))->filter()->values();
    }
}
