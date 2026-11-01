<?php

namespace Modules\AI\app\Agents\Tools;

use Modules\AI\app\Agents\AiResponseContext;
use Modules\RideShare\Entities\FareManagement\RideFare;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;

class EstimateRideFareTool implements Tool
{
    /**
     * @param int[] $zoneIds Overlapping zones the customer falls inside.
     */
    public function __construct(
        private readonly AiResponseContext $context,
        private readonly array $zoneIds = [],
    ) {}

    public function description(): string
    {
        return 'Estimate a ride fare. If the customer gives an approximate distance ("about 10 km", "8 kilometres"), pass it as distance_km and the tool returns totals per vehicle category. If no distance is mentioned, pass null and the tool returns the per-km fare structure so you can ask the customer for the distance. Estimates exclude waiting, idle, surge, and tax — always relay that caveat in the response.';
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'distance_km' => $schema->number()
                ->description('Approximate trip distance in kilometres, as stated by the customer. Pass null if they have not given a distance — the tool will return the per-km structure so you can ask.')
                ->required()
                ->nullable(),
            'vehicle_category_name' => $schema->string()
                ->description('Optional filter — restrict the estimate to one category by name (e.g. "bike", "sedan"). Case-insensitive substring match. Pass null to estimate across all categories.')
                ->required()
                ->nullable(),
        ];
    }

    public function handle(Request $request): string
    {
        $this->context->recordTool('EstimateRideFareTool');

        if (empty($this->zoneIds)) {
            return 'Fare estimates need a delivery area first — please set your location.';
        }

        $args        = $request->all();
        $distanceKm  = ($args['distance_km'] ?? null) !== null ? (float) $args['distance_km'] : null;
        $categoryHit = isset($args['vehicle_category_name']) && $args['vehicle_category_name'] !== null
            ? trim((string) $args['vehicle_category_name'])
            : null;

        if ($distanceKm !== null && ($distanceKm <= 0 || $distanceKm > 200)) {
            return 'That distance looks off — ride estimates are valid for ~0–200 km. Please check the kilometres and try again.';
        }

        $rows = RideFare::whereIn('zone_id', $this->zoneIds)
            ->with('vehicleCategory:id,name,status')
            ->get(['id', 'vehicle_category_id', 'zone_id', 'base_fare', 'base_fare_per_km']);

        if ($rows->isEmpty()) {
            return 'Fare data isn\'t set up for your area yet — please contact support.';
        }

        if ($categoryHit !== null && $categoryHit !== '') {
            $needle = mb_strtolower($categoryHit);
            $rows   = $rows->filter(fn (RideFare $r) =>
                $r->vehicleCategory
                && mb_stripos((string) $r->vehicleCategory->getAttribute('name'), $needle) !== false
                && (int) $r->vehicleCategory->getAttribute('status') === 1
            );
            if ($rows->isEmpty()) {
                return 'No ride category matching "' . $categoryHit . '" is available in your area.';
            }
        } else {
            $rows = $rows->filter(fn (RideFare $r) =>
                $r->vehicleCategory && (int) $r->vehicleCategory->getAttribute('status') === 1
            );
        }

        $rows = $rows->unique('vehicle_category_id');

        if ($distanceKm === null) {
            $lines = $rows->map(function (RideFare $r) {
                $name  = $r->vehicleCategory?->getAttribute('name') ?? 'Unknown';
                $base  = round((float) $r->getAttribute('base_fare'), 2);
                $perKm = round((float) $r->getAttribute('base_fare_per_km'), 2);
                return '• ' . $name . ': base ' . $base . ' + ' . $perKm . '/km';
            })->values()->implode(PHP_EOL);

            return 'Fare structure in your area:' . PHP_EOL . $lines . PHP_EOL
                . 'About how many kilometres is the trip? I\'ll calculate the estimate.';
        }

        $lines = $rows->map(function (RideFare $r) use ($distanceKm) {
            $name  = $r->vehicleCategory?->getAttribute('name') ?? 'Unknown';
            $base  = (float) $r->getAttribute('base_fare');
            $perKm = (float) $r->getAttribute('base_fare_per_km');
            $total = round($base + ($perKm * $distanceKm), 2);
            return '• ' . $name . ': ' . $total
                . ' (base ' . round($base, 2) . ' + ' . round($distanceKm, 1) . '×' . round($perKm, 2) . ')';
        })->values()->implode(PHP_EOL);

        return 'Estimated fare for ~' . round($distanceKm, 1) . ' km in your area:' . PHP_EOL
            . $lines . PHP_EOL
            . 'Note: this excludes waiting time, idle time, surge, and tax — actual fare may differ.';
    }
}
