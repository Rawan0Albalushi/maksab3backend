<?php

namespace App\Http\Resources\DeliveryMan\Profile;

use App\Http\Resources\BaseResource;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;

class ProfileResource extends BaseResource
{
    private const DROPPED_RELATIONS = ['orders', 'rating', 'todaysorders', 'this_week_orders', 'wallet'];

    private const DROPPED_COUNTS = ['orders_count', 'todaysorders_count', 'this_week_orders_count'];

    private array $metrics = [];

    private array $extras = [];

    public static function withMetrics($resource, array $metrics = [], array $extras = []): self
    {
        $instance = new self($resource);
        $instance->metrics = $metrics;
        $instance->extras = $extras;

        return $instance;
    }

    public function toArray(Request $request): array
    {
        return array_merge(
            Arr::except($this->resource->toArray(), array_merge(self::DROPPED_RELATIONS, self::DROPPED_COUNTS)),
            $this->metrics,
            $this->extras
        );
    }
}
