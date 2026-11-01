<?php

namespace App\Services\Parcel;

use App\Models\Dimension;
use App\Services\BaseService;
use App\Services\Zone\DeliveryRuleDimensionChargeService;
use App\Services\Zone\DeliveryRuleService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

/**
 * Owns App\Models\Dimension and nothing else.
 *
 * Settings only, same as WeightService — the charge a size class attracts lives on the delivery
 * rule and is deferred (delivery-zone-suite-parcel-deferred.md §6a).
 */
class DimensionService extends BaseService
{
    public function create(array $data): Dimension
    {
        return Dimension::create([
            'name' => $data['name'],
            'max_length' => $data['max_length'],
            'max_width' => $data['max_width'],
            'max_height' => $data['max_height'],
            'status' => $data['status'] ?? true,
        ]);
    }

    public function update(mixed $id, array $data): ?Dimension
    {
        $dimension = Dimension::find($id);

        if (! $dimension) {
            return null;
        }

        $dimension->update(array_filter([
            'name' => $data['name'] ?? null,
            'max_length' => $data['max_length'] ?? null,
            'max_width' => $data['max_width'] ?? null,
            'max_height' => $data['max_height'] ?? null,
        ], fn ($value) => $value !== null));

        return $dimension;
    }

    /**
     * The delivery rules that price this dimension class, by name.
     *
     * Deleting one a rule prices leaves a `delivery_rule_dimension_charges` row pointing at nothing: the
     * charge is never matched again, so the rule quietly stops pricing that class while its setup
     * table still lists a row for it. Same guard as AreaService::rulesPricingArea().
     *
     * @return array<int, string>
     */
    public function rulesPricingDimension(mixed $id): array
    {
        return DB::table('delivery_rule_dimension_charges')
            ->join('delivery_rules', 'delivery_rules.id', '=', 'delivery_rule_dimension_charges.delivery_rule_id')
            ->where('delivery_rule_dimension_charges.dimension_id', $id)
            ->pluck('delivery_rules.name')
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    public function delete(mixed $id): bool
    {
        return DB::transaction(function () use ($id) {
            $dimension = Dimension::find($id);

            if (! $dimension) {
                return false;
            }

            $dimension->translations()->delete();

            return (bool) $dimension->delete();
        });
    }

    public function updateStatus(mixed $id, mixed $status): bool
    {
        $dimension = Dimension::find($id);

        if (! $dimension) {
            return false;
        }

        return $dimension->update(['status' => (bool) $status]);
    }

    public function find(mixed $id, array $with = []): ?Dimension
    {
        return Dimension::with($with)->find($id);
    }

    public function getList(
        array $filters = [],
        array $with = [],
        array $withCount = [],
        array $paginate = ['per_page' => 25, 'page' => 1]
    ): LengthAwarePaginator {
        return $this->buildQuery($filters, $with, $withCount)
            ->paginate($this->pageSize($paginate), ['*'], 'page', $this->pageNumber($paginate));
    }

    public function getListData(array $filters = [], array $with = [], array $withCount = []): Collection
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
     * The active size classes, smallest box first. What the delivery-rule wizard's Dimension Rules
     * step will list once that lands.
     */
    public function activeSizes(): Collection
    {
        return Dimension::active()->inSizeOrder()->get();
    }

    /**
     * The size classes a customer may choose from for one (zone, module).
     *
     * Same two gates, same empty-list contract as `WeightService::bandsForZoneModule()` — read
     * that docblock; the reasoning is identical and is not repeated here. The only difference is
     * which switch is consulted: `dimension_charge_status` rather than `weight_charge_status`.
     * The two are independent, so a rule may price by weight and not by size, or the reverse.
     *
     * @return array{status:bool, items:array<int, array<string,mixed>>}
     */
    public function sizesForZoneModule(mixed $zoneId, mixed $moduleId): array
    {
        $rule = app(DeliveryRuleService::class)->activeRule($zoneId, $moduleId);

        if (! $rule || ! $rule->dimension_charge_status) {
            return ['status' => false, 'items' => []];
        }

        $charges = app(DeliveryRuleDimensionChargeService::class)->keyedByDimension($rule->id);

        $items = $this->activeSizes()
            ->map(fn (Dimension $size) => [
                'id' => $size->id,
                'name' => $size->name,
                'max_length' => (float) $size->max_length,
                'max_width' => (float) $size->max_width,
                'max_height' => (float) $size->max_height,
                'charge' => (float) ($charges[$size->id] ?? 0),
            ])
            ->values()
            ->all();

        return ['status' => true, 'items' => $items];
    }

    /**
     * Does a proposed size class share EXACT measurements with one that already exists?
     *
     * Not an overlap guard — DimensionAddRequest's docblock explains why size classes deliberately
     * nest (every Small also fits inside Large) and general overlap is the normal case. Two
     * classes with the SAME three measurements are a different problem: nothing about "the
     * smallest box it fits in" can break the tie between them, so a package legitimately matches
     * either, and which one prices it is arbitrary (TC_220). Strict equality only, on all three
     * dimensions at once.
     */
    public function identicalMeasurementNames(float $length, float $width, float $height, mixed $exceptId = null): array
    {
        return Dimension::query()
            ->when($exceptId, fn ($q) => $q->whereKeyNot($exceptId))
            ->where('max_length', $length)
            ->where('max_width', $width)
            ->where('max_height', $height)
            ->pluck('name')
            ->all();
    }

    private function buildQuery(array $filters, array $with, array $withCount): Builder
    {
        return Dimension::query()
            ->with($with)
            ->withCount($withCount)
            ->when(isset($filters['status']) && $filters['status'] !== '', fn ($q) => $q->where('status', (int) $filters['status']))
            ->when(! empty($filters['search']), function ($q) use ($filters) {
                $keys = explode(' ', (string) $filters['search']);
                $q->where(function ($sub) use ($keys) {
                    foreach ($keys as $key) {
                        $sub->orWhere('name', 'like', '%'.$key.'%');
                    }
                });
            })
            ->inSizeOrder();
    }
}
