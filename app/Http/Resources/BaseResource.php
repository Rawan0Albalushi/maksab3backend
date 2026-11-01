<?php

namespace App\Http\Resources;

use App\Services\Order\EtaService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Resources\MissingValue;

class BaseResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            //
        ];
    }

    public function render(): array
    {
        return $this->toArray(request());
    }

    public static function renderCollection(mixed $items): array
    {
        return static::collection($items)->toArray(request());
    }

    /**
     * A store's `delivery_time` as [min, max] MINUTES.
     *
     * This used to `explode('-')` and cast, which drops the unit suffix — so a store quoting
     * `2-3 hours` reported 2 and 3, and `3-5 days` reported 3 and 5. Thirty of this install's
     * ninety-two stores quote in hours or days, so a third of them were reporting a window off
     * by a factor of 60 or 1440 on every surface that shows one.
     *
     * It reads the suffix now, through the same parser the ETA uses (§11.3) rather than a second
     * copy of the rule. Parsing only — no query — so a resource calling it stays within rule 3.
     */
    protected function deliveryWindow(?string $deliveryTime): array
    {
        [$min, $max] = app(EtaService::class)->parseDeliveryTime($deliveryTime);

        // Zeroes rather than nulls, because that is the shape every caller already renders.
        return [(int) $min, (int) $max];
    }
}
