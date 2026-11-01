<?php

namespace App\Services\DeliveryMan;

use App\Models\Dimension;
use App\Models\DMVehicle;
use App\Services\BaseService;
use App\Services\System\DistanceService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class DmVehicleService extends BaseService
{
    public function getAddData(array $input): array
    {
        return [
            'type' => ($input['type'] ?? null)[array_search('default', ($input['lang'] ?? null))],
            'status' => 1,
            // 0.0, never null. S13 removed the Extra Charge input from the form and relaxed the
            // request rule to `nullable`, but `d_m_vehicles.extra_charges` is NOT NULL with no
            // default -- so every create threw "Column 'extra_charges' cannot be null" and no
            // vehicle category could be added at all. Zero is also the truthful value: the fee
            // engine stopped adding this charge in S13, and the API already answers 0.00.
            'extra_charges' => (float) ($input['extra_charges'] ?? 0),
            'starting_coverage_area' => ($input['starting_coverage_area'] ?? null),
            'maximum_coverage_area' => ($input['maximum_coverage_area'] ?? null),
        ];
    }

    public function getUpdateData(array $input): array
    {
        $data = $this->getAddData($input);
        unset($data['status']);

        return $data;
    }

    public function getActiveList(
        array $paginate = []
    ): LengthAwarePaginator {
        return DMVehicle::translateOnly(['type'])
            ->active()
            ->select('id', 'type', 'name')
            ->paginate($this->pageSize($paginate), ['*'], 'page', $this->pageNumber($paginate));
    }

    /**
     * The vehicle whose coverage band contains this distance.
     *
     * @param  mixed  $distance  KILOMETRES always (D2), whatever `distance_unit` says.
     *
     * §3.2 — the second and last place a measured distance meets a setup value. The bands
     * (`starting_coverage_area`, `maximum_coverage_area`) are stored exactly as typed and are
     * re-read in whatever unit the setting names; the distance is converted into that unit here,
     * as the first statement, so no comparison below can be reached with the wrong one.
     */
    public function coverageVehicle(mixed $distance): ?DMVehicle
    {
        $distance = app(DistanceService::class)->chargeable((float) $distance);

        return DMVehicle::active()
            ->where(function ($query) use ($distance) {
                $query->where('starting_coverage_area', '<=', $distance)
                    ->where('maximum_coverage_area', '>=', $distance)
                    ->orWhere(fn ($q) => $q->where('starting_coverage_area', '>=', $distance));
            })
            ->orderBy('starting_coverage_area')
            ->first(['id', 'extra_charges']);
    }

    /**
     * The vehicle extra for a distance — **always 0.00 since port doc A8**.
     *
     * The method and its endpoint survive because shipped apps call them and N9 forbids removing
     * a route; what they must not do is quote a charge nothing applies. Returning the stored
     * `extra_charges` here would put a number on the customer's screen that the fee engine no
     * longer adds — which is the "quotes one price, charges another" bug this port exists to
     * avoid, inverted.
     *
     * Vehicle categories themselves are alive and well: A10 keeps them as the routing key for
     * express orders. Only the money is gone.
     *
     * @param  mixed  $distance  KILOMETRES always.
     */
    public function coverageCharge(mixed $distance): float
    {
        return 0.0;
    }

    /**
     * The Vehicles Category list — one page, with the deliveryman count and the connected
     * dimension classes already loaded so the Blade never resolves a relation of its own.
     */
    public function getList(
        array $filters = [],
        array $paginate = ['per_page' => 25, 'page' => 1]
    ): LengthAwarePaginator {
        return $this->buildQuery($filters)
            ->with('dimensions:id,name')
            ->withCount('deliveryMen')
            ->paginate($this->pageSize($paginate), ['*'], 'page', $this->pageNumber($paginate));
    }

    /** Same rows, unpaginated — what the export walks. */
    public function getListData(array $filters = []): Collection
    {
        return $this->buildQuery($filters)->with('dimensions:id,name')->withCount('deliveryMen')->get();
    }

    /**
     * The edit screen must reach a category the RideShare global scope hides, or a row saved as
     * ride-only becomes uneditable rather than merely unlisted.
     */
    public function find(mixed $id, array $with = []): ?DMVehicle
    {
        return DMVehicle::withoutGlobalScope('delivery_only')->with($with)->find($id);
    }

    public function create(array $data): DMVehicle
    {
        return DB::transaction(function () use ($data) {
            $vehicle = DMVehicle::create($this->columns($data) + ['status' => 1]);
            $vehicle->dimensions()->sync($this->dimensionIds($data));

            return $vehicle;
        });
    }

    public function update(mixed $id, array $data): ?DMVehicle
    {
        return DB::transaction(function () use ($id, $data) {
            $vehicle = $this->find($id);

            if (! $vehicle) {
                return null;
            }

            $vehicle->update($this->columns($data));
            $vehicle->dimensions()->sync($this->dimensionIds($data));

            return $vehicle;
        });
    }

    public function updateStatus(mixed $id, mixed $status): bool
    {
        $vehicle = $this->find($id);

        if (! $vehicle) {
            return false;
        }

        return $vehicle->update(['status' => (int) (bool) $status]);
    }

    public function delete(mixed $id): bool
    {
        return DB::transaction(function () use ($id) {
            $vehicle = $this->find($id);

            if (! $vehicle) {
                return false;
            }

            $vehicle->dimensions()->detach();
            $vehicle->translations()->delete();

            return (bool) $vehicle->delete();
        });
    }

    /**
     * What still points at this category, by kind, so the screen can say why a delete is refused.
     *
     * Deliverymen first: a category is the vehicle a rider is registered with, and removing it
     * would leave `delivery_men.vehicle_id` pointing at nothing. The express filter is the second
     * holder — see AdditionalDeliveryChargeService::pairsBarredForExpress().
     *
     * @return array{delivery_men: int, express_setups: int}
     */
    public function dependants(mixed $id): array
    {
        return [
            'delivery_men' => DB::table('delivery_men')->where('vehicle_id', $id)->count(),
            'express_setups' => DB::table('additional_delivery_charge_vehicle')->where('d_m_vehicle_id', $id)->count(),
        ];
    }

    /** Active size classes for the "Dimension Connect" picker, smallest box first. */
    public function dimensionOptions(): Collection
    {
        return Dimension::active()->inSizeOrder()->get(['id', 'name']);
    }

    /** Only the columns this screen owns — never `status`, which has its own endpoint. */
    private function columns(array $data): array
    {
        return [
            'type' => $data['type'],
            'starting_coverage_area' => $data['starting_coverage_area'],
            'maximum_coverage_area' => $data['maximum_coverage_area'],
            'max_weight' => $data['max_weight'],
            // 0.0, never null — see getAddData(). The column is NOT NULL and the fee engine
            // stopped reading it in S13.
            'extra_charges' => (float) ($data['extra_charges'] ?? 0),
        ];
    }

    /** @return array<int, int> */
    private function dimensionIds(array $data): array
    {
        return array_values(array_unique(array_map('intval', (array) ($data['dimension_ids'] ?? []))));
    }

    private function buildQuery(array $filters): Builder
    {
        return DMVehicle::query()
            ->when(isset($filters['status']) && $filters['status'] !== '', fn ($q) => $q->where('status', (int) $filters['status']))
            ->when(! empty($filters['search']), function ($q) use ($filters) {
                $keys = explode(' ', (string) $filters['search']);
                $q->where(function ($sub) use ($keys) {
                    foreach ($keys as $key) {
                        $sub->orWhere('type', 'like', '%'.$key.'%');
                    }
                });
            })
            ->orderBy('starting_coverage_area');
    }
}
