<?php

namespace App\Services\Parcel;

use App\Models\Weight;
use App\Services\BaseService;
use App\Services\Zone\DeliveryRuleService;
use App\Services\Zone\DeliveryRuleWeightChargeService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

/**
 * Owns App\Models\Weight and nothing else.
 *
 * Settings only. Nothing here prices an order — the charge a band attracts belongs to the
 * delivery rule and is deferred with the rest of the parcel tier
 * (delivery-zone-suite-parcel-deferred.md §6a).
 */
class WeightService extends BaseService
{
    public function create(array $data): Weight
    {
        return Weight::create([
            'name' => $data['name'],
            'from_weight' => $data['from_weight'],
            'to_weight' => $data['to_weight'],
            'status' => $data['status'] ?? true,
        ]);
    }

    public function update(mixed $id, array $data): ?Weight
    {
        $weight = Weight::find($id);

        if (! $weight) {
            return null;
        }

        $weight->update(array_filter([
            'name' => $data['name'] ?? null,
            'from_weight' => $data['from_weight'] ?? null,
            'to_weight' => $data['to_weight'] ?? null,
        ], fn ($value) => $value !== null));

        return $weight;
    }

    /** Transactional because the translation rows go with it, as areas and zip codes do. */
    /**
     * The delivery rules that price this weight class, by name.
     *
     * Deleting one a rule prices leaves a `delivery_rule_weight_charges` row pointing at nothing: the
     * charge is never matched again, so the rule quietly stops pricing that class while its setup
     * table still lists a row for it. Same guard as AreaService::rulesPricingArea().
     *
     * @return array<int, string>
     */
    public function rulesPricingWeight(mixed $id): array
    {
        return DB::table('delivery_rule_weight_charges')
            ->join('delivery_rules', 'delivery_rules.id', '=', 'delivery_rule_weight_charges.delivery_rule_id')
            ->where('delivery_rule_weight_charges.weight_id', $id)
            ->pluck('delivery_rules.name')
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    public function delete(mixed $id): bool
    {
        return DB::transaction(function () use ($id) {
            $weight = Weight::find($id);

            if (! $weight) {
                return false;
            }

            $weight->translations()->delete();

            return (bool) $weight->delete();
        });
    }

    public function updateStatus(mixed $id, mixed $status): bool
    {
        $weight = Weight::find($id);

        if (! $weight) {
            return false;
        }

        return $weight->update(['status' => (bool) $status]);
    }

    public function find(mixed $id, array $with = []): ?Weight
    {
        return Weight::with($with)->find($id);
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

    /** For the export and for anything needing the whole filtered set rather than a page. */
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
     * The active bands, ascending. This is what the delivery-rule wizard's Weight Rules step will
     * list once that lands — exposed now so the step has nothing to re-derive.
     */
    public function activeBands(): Collection
    {
        return Weight::active()->inBandOrder()->get();
    }

    /**
     * The bands a customer may choose from for one (zone, module) — the parcel checkout's picker.
     *
     * TWO gates, and BOTH must pass, or the answer is an empty list:
     *
     *   1. the ACTIVE delivery rule for this (zone, module) has `weight_charge_status` on. This
     *      is the rules-setup switch: a zone that does not price by weight has no band to offer,
     *      and offering one would put a picker on the checkout that changes nothing.
     *   2. the band's OWN `status` is on. A deactivated band is gone from every surface, and it
     *      stays gone here even where a rule still carries a charge row for it — the row outlives
     *      the deactivation, and honouring it would charge for a band no screen lists.
     *
     * An empty list is the ANSWER, not a missing one — the same contract
     * `DeliveryRuleService::coverageForZone()` states for coverage. `status` says which it is, so
     * a client renders no picker rather than treating an empty list as a failed call.
     *
     * `charge` is what the band ADDS under this rule (parcel brief §1, model (a)), so the picker
     * can show "1 - 2 kg (+20.00)" without a second call. A band the rule never priced carries
     * 0.00 rather than being dropped: it is selectable and adds nothing, which is exactly what an
     * unpriced area does (§5.3).
     *
     * @return array{status:bool, items:array<int, array<string,mixed>>}
     */
    public function bandsForZoneModule(mixed $zoneId, mixed $moduleId): array
    {
        $rule = app(DeliveryRuleService::class)->activeRule($zoneId, $moduleId);

        if (! $rule || ! $rule->weight_charge_status) {
            return ['status' => false, 'items' => []];
        }

        $charges = app(DeliveryRuleWeightChargeService::class)->keyedByWeight($rule->id);

        $items = $this->activeBands()
            ->map(fn (Weight $band) => [
                'id' => $band->id,
                'name' => $band->name,
                'from_weight' => (float) $band->from_weight,
                'to_weight' => (float) $band->to_weight,
                'charge' => (float) ($charges[$band->id] ?? 0),
            ])
            ->values()
            ->all();

        return ['status' => true, 'items' => $items];
    }

    /**
     * Does a proposed band overlap one that already exists?
     *
     * Bands must not overlap, or a 3 kg package matches two rows and the charge depends on
     * whichever the query happened to return first. Touching endpoints are fine — "0-2" and "2-4"
     * are how the design itself writes them — so the test is strict on both sides.
     */
    public function overlappingBandNames(float $from, float $to, mixed $exceptId = null): array
    {
        return Weight::query()
            ->when($exceptId, fn ($q) => $q->whereKeyNot($exceptId))
            ->where('from_weight', '<', $to)
            ->where('to_weight', '>', $from)
            ->pluck('name')
            ->all();
    }

    private function buildQuery(array $filters, array $with, array $withCount): Builder
    {
        return Weight::query()
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
            ->inBandOrder();
    }
}
