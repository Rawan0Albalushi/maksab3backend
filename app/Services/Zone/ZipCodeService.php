<?php

namespace App\Services\Zone;

use App\Models\ZipCode;
use App\Services\BaseService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

/**
 * Owns App\Models\ZipCode and nothing else.
 *
 * Same shape as AreaService — ZIP codes are geography too, so `zone_id` alone. The one
 * difference worth knowing: a code is unique WITHIN a zone, never globally. Two zones
 * legitimately share one where their coverage overlaps (§7).
 */
class ZipCodeService extends BaseService
{
    public function create(array $data): ZipCode
    {
        return ZipCode::create([
            'zone_id' => $data['zone_id'],
            'zip_code' => $data['zip_code'],
            'status' => $data['status'] ?? true,
        ]);
    }

    public function update(mixed $id, array $data): ?ZipCode
    {
        $zipCode = ZipCode::find($id);

        if (! $zipCode) {
            return null;
        }

        $zipCode->update(array_filter([
            'zone_id' => $data['zone_id'] ?? null,
            'zip_code' => $data['zip_code'] ?? null,
        ], fn ($value) => $value !== null));

        return $zipCode;
    }

    /**
     * The delivery rules that price this ZIP code, by name — see AreaService::rulesPricingArea()
     * for why deleting one out from under a rule is refused rather than allowed.
     *
     * @return array<int, string>
     */
    public function rulesPricingZipCode(mixed $id): array
    {
        return DB::table('delivery_rule_charges')
            ->join('delivery_rules', 'delivery_rules.id', '=', 'delivery_rule_charges.delivery_rule_id')
            ->where('delivery_rule_charges.zip_code_id', $id)
            ->pluck('delivery_rules.name')
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    public function delete(mixed $id): bool
    {
        return DB::transaction(function () use ($id) {
            $zipCode = ZipCode::find($id);

            if (! $zipCode) {
                return false;
            }

            return (bool) $zipCode->delete();
        });
    }

    public function updateStatus(mixed $id, mixed $status): bool
    {
        $zipCode = ZipCode::find($id);

        if (! $zipCode) {
            return false;
        }

        return $zipCode->update(['status' => (bool) $status]);
    }

    public function find(mixed $id, array $with = []): ?ZipCode
    {
        return ZipCode::with($with)->find($id);
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

    public function activeForZone(mixed $zoneId): Collection
    {
        return ZipCode::active()->ofZone($zoneId)->orderBy('zip_code')->get();
    }

    /** Security (§5.4) — see AreaService::belongsToZone(). */
    public function belongsToZone(mixed $zoneId, mixed $zipCodeId): bool
    {
        if (empty($zipCodeId)) {
            return false;
        }

        return ZipCode::active()->ofZone($zoneId)->whereKey($zipCodeId)->exists();
    }

    /** The zone a ZIP code belongs to — see AreaService::zoneIdFor(). */
    public function zoneIdFor(mixed $zipCodeId): ?int
    {
        if (empty($zipCodeId)) {
            return null;
        }

        $zoneId = ZipCode::whereKey($zipCodeId)->value('zone_id');

        return $zoneId === null ? null : (int) $zoneId;
    }

    /**
     * Uniqueness is per zone, so the check takes both. `$exceptId` lets an update ignore the
     * row it is editing.
     */
    public function existsInZone(mixed $zoneId, string $zipCode, mixed $exceptId = null): bool
    {
        return ZipCode::ofZone($zoneId)
            ->where('zip_code', $zipCode)
            ->when($exceptId, fn ($q) => $q->whereKeyNot($exceptId))
            ->exists();
    }

    private function buildQuery(array $filters, array $with, array $withCount): Builder
    {
        return ZipCode::query()
            ->with($with)
            ->withCount($withCount)
            ->when(! empty($filters['zone_id']), fn ($q) => $q->where('zone_id', $filters['zone_id']))
            ->when(isset($filters['status']) && $filters['status'] !== '', fn ($q) => $q->where('status', (int) $filters['status']))
            ->when(! empty($filters['search']), fn ($q) => $q->where('zip_code', 'like', '%'.$filters['search'].'%'))
            ->orderBy('id', 'desc');
    }
}
