<?php

namespace App\Services\Zone;

use App\Models\Area;
use App\Services\BaseService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

/**
 * Owns App\Models\Area and nothing else.
 *
 * Areas are geography, so every method here is keyed on `zone_id` alone — no moduleId, by
 * design (delivery-zone-suite-port.md §0.1). The delivery rule that *prices* an area carries
 * the module scope; the area itself does not.
 */
class AreaService extends BaseService
{
    public function create(array $data): Area
    {
        return Area::create([
            'zone_id' => $data['zone_id'],
            'name' => $data['name'],
            'display_name' => $data['display_name'] ?? null,
            'status' => $data['status'] ?? true,
        ]);
    }

    public function update(mixed $id, array $data): ?Area
    {
        $area = Area::find($id);

        if (! $area) {
            return null;
        }

        $area->update(array_filter([
            'zone_id' => $data['zone_id'] ?? null,
            'name' => $data['name'] ?? null,
            'display_name' => $data['display_name'] ?? null,
        ], fn ($value) => $value !== null));

        return $area;
    }

    /**
     * Deleting an area a live delivery rule prices would silently drop that charge to zero, so
     * the caller is told what it is about to break rather than finding out from an order.
     * Wrapped in a transaction because the charge rows go with it.
     */
    /**
     * The delivery rules that price this area, by name.
     *
     * Deleting an area a rule prices leaves a `delivery_rule_charges` row pointing at nothing:
     * the charge is never matched again, so the rule quietly stops pricing that area while its
     * setup table still lists a row for it. Named rather than counted so the admin is told which
     * rule to edit instead of hunting for it.
     *
     * @return array<int, string>
     */
    public function rulesPricingArea(mixed $id): array
    {
        return DB::table('delivery_rule_charges')
            ->join('delivery_rules', 'delivery_rules.id', '=', 'delivery_rule_charges.delivery_rule_id')
            ->where('delivery_rule_charges.area_id', $id)
            ->pluck('delivery_rules.name')
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    public function delete(mixed $id): bool
    {
        return DB::transaction(function () use ($id) {
            $area = Area::find($id);

            if (! $area) {
                return false;
            }

            $area->translations()->delete();

            return (bool) $area->delete();
        });
    }

    public function updateStatus(mixed $id, mixed $status): bool
    {
        $area = Area::find($id);

        if (! $area) {
            return false;
        }

        return $area->update(['status' => (bool) $status]);
    }

    public function find(mixed $id, array $with = []): ?Area
    {
        return Area::with($with)->find($id);
    }

    public function getList(
        array $filters = [],
        array $with = ['zone'],
        array $withCount = ['stores', 'deliveryMen'],
        array $paginate = ['per_page' => 25, 'page' => 1]
    ): LengthAwarePaginator {
        return $this->buildQuery($filters, $with, $withCount)
            ->paginate($this->pageSize($paginate), ['*'], 'page', $this->pageNumber($paginate));
    }

    /** For the export and for anything that needs the whole filtered set rather than a page. */
    public function getListData(array $filters = [], array $with = ['zone'], array $withCount = ['stores', 'deliveryMen']): Collection
    {
        return $this->buildQuery($filters, $with, $withCount)->get();
    }

    public function statusStatistics(array $filters = []): array
    {
        $base = fn () => $this->buildQuery($filters, [], []);

        return [
            'total' => $base()->count(),
            'active' => $base()->where('status', 1)->count(),
            'inactive' => $base()->where('status', 0)->count(),
        ];
    }

    /**
     * The active areas of one zone, for the coverage picker and for the delivery-rule form's
     * charge table. Ordered by name so the admin sees a stable list.
     */
    public function activeForZone(mixed $zoneId): Collection
    {
        return Area::active()->ofZone($zoneId)->orderBy('name')->get();
    }

    /**
     * Security (§5.4). A customer can post any area_id; without this they post the id of a
     * cheaper area in another zone and are charged its rate. Called on every path that accepts
     * a pick — place_order, checkout-summary, POS, Builder.
     */
    public function belongsToZone(mixed $zoneId, mixed $areaId): bool
    {
        if (empty($areaId)) {
            return false;
        }

        return Area::active()->ofZone($zoneId)->whereKey($areaId)->exists();
    }

    /**
     * The zone an area belongs to, or null if there is no such area.
     *
     * For telling a refused pick apart from a location this module does not serve — see
     * DeliveryRuleService::coverageSelectionError(). No `active()` filter: the question is which
     * zone the customer was pointing at, which a switched-off area answers just as well.
     */
    public function zoneIdFor(mixed $areaId): ?int
    {
        if (empty($areaId)) {
            return null;
        }

        $zoneId = Area::whereKey($areaId)->value('zone_id');

        return $zoneId === null ? null : (int) $zoneId;
    }

    private function buildQuery(array $filters, array $with, array $withCount): Builder
    {
        return Area::query()
            ->with($with)
            ->withCount($withCount)
            ->when(! empty($filters['zone_id']), fn ($q) => $q->where('zone_id', $filters['zone_id']))
            ->when(isset($filters['status']) && $filters['status'] !== '', fn ($q) => $q->where('status', (int) $filters['status']))
            ->when(! empty($filters['search']), function ($q) use ($filters) {
                $keys = explode(' ', (string) $filters['search']);
                $q->where(function ($sub) use ($keys) {
                    foreach ($keys as $key) {
                        $sub->orWhere('name', 'like', '%'.$key.'%')
                            ->orWhere('display_name', 'like', '%'.$key.'%');
                    }
                });
            })
            ->orderBy('id', 'desc');
    }
}
