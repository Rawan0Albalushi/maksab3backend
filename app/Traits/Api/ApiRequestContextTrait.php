<?php

namespace App\Traits\Api;

use App\Models\Store;
use App\Services\Zone\ZoneService;
use Illuminate\Http\Request;

trait ApiRequestContextTrait
{
    public function pageParams(Request $request): array
    {
        return ['per_page' => $this->perPage($request), 'page' => $this->page($request)];
    }

    protected function vendorStore(Request $request): mixed
    {
        return $request->input('vendor')?->stores[0] ?? null;
    }

    protected function vendorStoreId(Request $request): mixed
    {
        return $this->vendorStore($request)?->id;
    }

    protected function vendorId(Request $request): mixed
    {
        return $request->input('vendor')?->id;
    }

    protected function deliveryMan(): mixed
    {
        return auth('delivery_men')->user();
    }

    protected function deliveryManId(): mixed
    {
        return auth('delivery_men')->id();
    }

    protected function applyZoneIds(Request $request): array
    {
        $zoneIds = app(ZoneService::class)->resolveZoneIds(
            $request->hasHeader('zoneId') ? $request->header('zoneId') : null,
            $request->hasHeader('moduleId') ? $request->header('moduleId') : null,
        );

        $request->headers->set('zoneId', json_encode($zoneIds));

        return $zoneIds;
    }

    protected function zoneIds(Request $request): array
    {
        $decoded = json_decode((string) $request->header('zoneId'), true);

        return is_array($decoded) ? $decoded : [];
    }

    protected function headerModuleId(Request $request): ?int
    {
        $moduleId = $request->header('moduleId') ? getModuleId($request->header('moduleId')) : null;

        return is_numeric($moduleId) ? (int) $moduleId : null;
    }

    protected function isServiceModuleContext(): bool
    {
        $module = config('module.current_module_data');

        return service_addon_active()
            && $module
            && ($module['module_type'] ?? null) === 'service'
            && (int) ($module['status'] ?? 0) === 1;
    }

    protected function currentModuleId(): mixed
    {
        return config('module.current_module_data')['id'] ?? null;
    }

    protected function currentModuleServesAllZones(): mixed
    {
        return config('module.current_module_data')['all_zone_service'] ?? null;
    }

    protected function translationRows(Request $request): array
    {
        $raw = $request->input('translations');

        if (is_array($raw)) {
            return $raw;
        }

        $decoded = json_decode((string) $raw, true);

        return is_array($decoded) ? $decoded : [];
    }

    protected function orderOwner(Request $request): array
    {
        return [
            'user_id' => $request->user ? $request->user->id : $request->input('guest_id'),
            'is_guest' => $request->user ? 0 : 1,
        ];
    }

    protected function cartOwner(Request $request): array
    {
        return [
            'user_id' => $request->user ? $request->user->id : $request->input('guest_id'),
            'is_guest' => $request->user ? 0 : 1,
            'module_id' => getModuleId($request->header('moduleId')),
        ];
    }

    protected function cartStoreId(Request $request): ?int
    {
        $storeId = $request->input('store_id');

        if ($storeId === null || $storeId === '') {
            return null;
        }

        return is_numeric($storeId) ? (int) $storeId : Store::where('slug', $storeId)->value('id');
    }

    protected function apiContext(Request $request): array
    {
        return [
            'zone_ids' => $this->zoneIds($request),
            'zone_header' => $request->header('zoneId'),
            'module_id' => $this->currentModuleId(),
            'customer_id' => auth('api')->id(),
            'type' => $request->query('type', 'all'),
            'longitude' => $request->header('longitude'),
            'latitude' => $request->header('latitude'),
        ];
    }
}
