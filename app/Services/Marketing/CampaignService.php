<?php

namespace App\Services\Marketing;

use App\Mail\CampaignRequestMail;
use App\Mail\VendorCampaignRequestMail;
use App\Models\Campaign;
use App\Services\BaseService;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use App\Services\Admin\AdminService;
use App\Support\Notification\SendNotification;
use Illuminate\Support\Facades\Log;

class CampaignService extends BaseService
{
    private const LIST_COLUMNS = [
        'id', 'title', 'description', 'slug', 'image', 'module_id',
        'start_date', 'end_date', 'start_time', 'end_time',
    ];

    public function runningQuery(array $zoneIds, mixed $moduleId, array $filters = []): mixed
    {
        return Campaign::with(['storage' => fn ($query) => $query->select(STORAGE_RELATION_COLUMNS)])
            ->whereHas('module.zones', fn ($query) => $query->whereIn('zones.id', $zoneIds))
            ->when($moduleId, function ($query) use ($moduleId, $zoneIds, $filters) {
                $query->module($moduleId);

                if (! ($filters['all_zone_service'] ?? false)) {
                    $query->whereHas('stores', fn ($sub) => $sub->whereIn('zone_id', $zoneIds));
                }
            })
            ->running()
            ->active();
    }

    public function getList(
        array $filters = [],
        array $with = [],
        array $withCount = [],
        bool $withTrashed = false,
        array $paginate = []
    ): LengthAwarePaginator {
        return $this->buildQuery($filters)
            ->with($with)
            ->withCount($withCount)
            ->paginate($this->pageSize($paginate), self::LIST_COLUMNS, 'page', $this->pageNumber($paginate));
    }

    public function find(mixed $id, array $filters = []): ?Campaign
    {
        $campaign = Campaign::translateOnly(['title', 'description'])
            ->withStorage()
            ->whereHas('module.zones', fn ($query) => $query->whereIn('zones.id', $filters['zone_ids'] ?? []))
            ->with(['stores' => fn ($query) => $this->constrainStores($query, $filters)])
            ->running()
            ->active()
            ->where(fn ($query) => $query->where('id', $id)->orWhere('slug', $id))
            ->first(self::LIST_COLUMNS);

        return $campaign;
    }

    public function getVendorList(array $filters = [], array $paginate = []): LengthAwarePaginator
    {
        return Campaign::withStorage()
            ->with(['stores' => fn ($query) => $query->select('stores.id')->withStorage()])
            ->module($filters['module_id'] ?? null)
            ->running()
            ->latest()
            ->paginate($this->pageSize($paginate), ['*'], 'page', $this->pageNumber($paginate));
    }

    public function updateStoreMembership(mixed $campaignId, mixed $store, bool $join): array
    {
        // Only a campaign of the store's own module, as the list it was picked from shows; the id
        // alone let a store join a running campaign in any module.
        $campaign = Campaign::where('status', 1)->where('module_id', $store?->module_id)->find($campaignId);

        if (! $campaign) {
            return ['status_code' => 404, 'code' => 'campaign', 'message' => 'Campaign not found or upavailable!'];
        }

        $join ? $campaign->stores()->attach($store) : $campaign->stores()->detach($store);
        $campaign->save();

        if ($join) {
            $this->notifyCampaignJoin($store);
        }

        return [
            'status_code' => 200,
            'message' => $join
                ? translate('messages.You are successfully joined to the campaign')
                : translate('messages.You are successfully removed from the campaign'),
        ];
    }

    private function buildQuery(array $filters): Builder
    {
        $zoneIds = $filters['zone_ids'] ?? [];

        return Campaign::translateOnly(['title', 'description'])
            ->withStorage()
            ->whereHas('module.zones', fn ($query) => $query->whereIn('zones.id', $zoneIds))
            ->when($filters['module_id'] ?? null, function ($query) use ($filters, $zoneIds) {
                $query->module($filters['module_id']);

                if (! ($filters['all_zone_service'] ?? false)) {
                    $query->whereHas('stores', fn ($q) => $q->whereIn('zone_id', $zoneIds));
                }
            })
            ->running()
            ->active();
    }

    private function constrainStores($query, array $filters): void
    {
        $zoneIds = $filters['zone_ids'] ?? [];
        $moduleId = $filters['module_id'] ?? null;

        $query->translateOnly(['name'])
            ->with([
                'storage' => fn ($q) => $q->select(STORAGE_RELATION_COLUMNS),
                'storeConfig' => fn ($q) => $q->select('store_id', 'verified_seller', 'extra_packaging_status', 'extra_packaging_amount'),
                'module' => fn ($q) => $q->withoutGlobalScope('translate')->select('id', 'module_type'),
                'discount' => fn ($q) => $q->validate(),
            ])
            ->withOpen($filters['longitude'] ?? 0, $filters['latitude'] ?? 0)
            ->active()
            ->where('campaign_status', 'confirmed')
            ->when($moduleId, function ($q) use ($moduleId) {
                $q->where('module_id', $moduleId)
                    ->whereHas('zone.modules', fn ($z) => $z->where('modules.id', $moduleId));
            })
            ->whereIn('zone_id', $zoneIds);
    }

    private function notifyCampaignJoin(mixed $store): void
    {
        if (! config('mail.status')) {
            return;
        }

        try {
            if (SendNotification::mailTemplateEnabled('campaign_request_mail_status_admin')
                && SendNotification::channelEnabled('admin', 'campaign_join_request', 'mail_status')) {
                SendNotification::mail(app(AdminService::class)->findSuperAdminEmail(), new CampaignRequestMail($store->name));
            }

            if (SendNotification::mailTemplateEnabled('campaign_request_mail_status_store')
                && SendNotification::channelEnabled('store', 'store_campaign_join_request', 'mail_status', $store->id)) {
                SendNotification::mail($store->vendor?->getRawOriginal('email'), new VendorCampaignRequestMail($store->name, 'pending'));
            }
        } catch (\Exception $exception) {
            Log::error('marketing.campaign_service.notify_campaign_join_failed', [
                'error' => $exception->getMessage(),
                'file' => $exception->getFile().':'.$exception->getLine(),
            ]);
        }
    }




}
